<?php

namespace App\Services\Coco\Actions;

use App\Models\Patient;
use App\Models\User;
use App\Services\Coco\CocoRefusal;
use App\Services\Coco\PatientFinder;
use App\Services\SmsService;

/**
 * One text message to one person: a patient, a patient's guardian, or a
 * number the user typed. Sent with SmsService::send(), like Send a text
 * (SmsController), so it respects the SMS switch and lands in the SMS log.
 */
class SendSms extends CocoAction
{
    /** At most 3 text messages long (about 450 characters). */
    public const MAX_PARTS = 3;

    public function __construct(
        private readonly SmsService $sms,
        private readonly PatientFinder $finder,
    ) {}

    public function type(): string { return 'send_sms'; }

    public function title(): string { return 'Send SMS'; }

    public function icon(): string { return 'chat-dots'; }

    public function group(): string { return 'messages'; }

    public function allowedFor(User $user): bool
    {
        return $user->can('send-sms');
    }

    public function tool(): array
    {
        return $this->makeTool(
            'Prepare ONE text message (SMS) to ONE person, as a card the user can edit. Sent only if the user taps Confirm.',
            [
                'to'             => ['type' => 'string', 'enum' => ['patient', 'guardian', 'number'], 'description' => 'patient or guardian: the number is taken from the patient record, never ask for it. number: a number the user typed.'],
                'patient_id'     => ['type' => 'integer'],
                'patient_name'   => ['type' => 'string', 'description' => 'Name or patient number as the user wrote it, when there is no id.'],
                'phone'          => ['type' => 'string', 'description' => 'When to is number.'],
                'recipient_name' => ['type' => 'string', 'description' => 'When to is number: whose number.'],
                'message'        => ['type' => 'string', 'description' => 'The exact text.'],
            ],
            ['to', 'message'],
        );
    }

    public function propose(array $args, User $user): array
    {
        $to      = in_array($args['to'] ?? null, ['patient', 'guardian', 'number'], true) ? $args['to'] : (filled($args['phone'] ?? null) ? 'number' : 'patient');
        $message = $this->plain($this->str($args, 'message', 2000));
        $this->checkMessage($message);

        if ($to === 'number') {
            $number = $this->sms->normalizeNumber($this->str($args, 'phone', 30));
            $this->need($number !== null, 'That is not a valid Philippine mobile number. Ask the user for a number like 09171234567.');

            return [
                'payload'    => ['to' => 'number', 'number' => $number, 'name' => $this->str($args, 'recipient_name', 100), 'message' => $message, 'candidates' => ['number']],
                'candidates' => [],
            ];
        }

        $this->need($user->can('view-patients'), 'This user cannot see patient records, so the message can only go to a number they type.');

        $patients = $this->finder->resolve($args['patient_id'] ?? null, $args['patient_name'] ?? null);
        $usable   = $patients->filter(fn (Patient $p) => $this->numberFor($p, $to) !== null);

        if ($usable->isEmpty()) {
            $whose = $to === 'guardian' ? 'guardian mobile number' : 'mobile number of their own';
            throw CocoRefusal::because($patients->count() === 1
                ? "This patient has no valid {$whose} on file. The user can add it on the patient record, or give a number to text."
                : "None of the {$patients->count()} matching patients has a valid {$whose} on file.");
        }

        return [
            'payload'    => ['to' => $to, 'message' => $message, 'candidates' => $usable->map(fn (Patient $p) => (string) $p->id)->values()->all()],
            'candidates' => $patients->map(fn (Patient $p) => [
                'id'       => (string) $p->id,
                'label'    => $p->full_name,
                'detail'   => PatientFinder::describe($p),
                'meta'     => $this->candidateMeta($p, $to),
                'disabled' => $this->numberFor($p, $to) === null,
            ])->values()->all(),
        ];
    }

    public function plan(array $payload, ?string $choice, array $edits, User $user): array
    {
        $this->need($this->allowedFor($user), 'You do not have permission to send text messages.');

        $message = $this->plain(array_key_exists('message', $edits) && $edits['message'] !== null ? (string) $edits['message'] : (string) $payload['message']);
        $this->checkMessage($message);

        $to      = (string) $payload['to'];
        $patient = null;

        if ($to === 'number') {
            $number    = $this->sms->normalizeNumber((string) $payload['number']);
            $this->need($number !== null, 'The number is not a valid mobile number.');
            $recipient = filled($payload['name'] ?? null) ? (string) $payload['name'] : 'the number you gave';
            $logName   = filled($payload['name'] ?? null) ? (string) $payload['name'] : null;
        } else {
            $this->need($user->can('view-patients'), 'You do not have permission to see patient records.');
            $patient = $this->finder->find($this->chosen($payload, $choice));
            $this->need($patient !== null, 'That patient record is no longer active.');

            $number = $this->numberFor($patient, $to);
            $this->need($number !== null, $to === 'guardian'
                ? "{$patient->full_name} has no valid guardian mobile number on file."
                : "{$patient->full_name} has no valid mobile number on file.");

            $recipient = $to === 'guardian'
                ? (filled($patient->guardian_name) ? "{$patient->guardian_name} (guardian of {$patient->full_name})" : "{$patient->full_name}'s guardian")
                : $patient->full_name;
            $logName = $to === 'guardian' ? ($patient->guardian_name ?: "Guardian of {$patient->full_name}") : $patient->full_name;
        }

        $parts = self::parts($message);
        $notes = [];
        if (! $this->sms->enabled()) {
            $notes[] = 'Text messages are turned off in Settings. If you confirm, the message is only listed in the SMS log as Skipped.';
        } elseif (! $this->sms->apiKeyConfigured()) {
            $notes[] = 'The SMS provider is not connected, so the message cannot be delivered.';
        }

        return [
            'summary'  => "Send SMS to {$recipient}",
            'fields'   => [
                ['label' => 'To', 'value' => $recipient, 'recipient' => true],
                ['label' => 'Number', 'value' => $this->finder->maskPhone($number), 'recipient' => true],
            ],
            'editable' => ['kind' => 'sms', 'message' => $message, 'max' => 160 * self::MAX_PARTS],
            'notes'    => $notes,
            // For execute()
            'number'     => $number,
            'message'    => $message,
            'parts'      => $parts,
            'recipient'  => $recipient,
            'log_name'   => $logName,
            'patient_id' => $patient?->id,
        ];
    }

    public function execute(array $plan, User $user): array
    {
        $patient = $plan['patient_id'] ? Patient::find($plan['patient_id']) : null;

        $log = $this->sms->send(
            number: $plan['number'],
            message: $plan['message'],
            recipientName: $plan['log_name'],
            reference: $patient,
            event: 'assistant',
        );

        $url = $user->can('view-sms') ? route('sms.index') : null;

        return match ($log->status) {
            'sent'  => ['ok' => true, 'text' => "Sent to {$plan['recipient']}. ".self::credits($plan['parts']).'.', 'url' => $url],
            default => ['ok' => false, 'text' => 'Not sent: '.rtrim((string) ($log->error_message ?: 'the SMS provider did not accept it'), '.').'.', 'url' => $url],
        };
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function numberFor(Patient $patient, string $to): ?string
    {
        return $this->sms->normalizeNumber($to === 'guardian' ? $patient->guardian_contact : $patient->contact_number);
    }

    private function candidateMeta(Patient $patient, string $to): string
    {
        $number = $this->numberFor($patient, $to);

        if ($to === 'guardian') {
            $who = 'Guardian'.(filled($patient->guardian_name) ? ": {$patient->guardian_name}" : '');

            return $number ? "{$who}, ".$this->finder->maskPhone($number) : 'No guardian mobile number on file';
        }

        return $number ? 'Mobile: '.$this->finder->maskPhone($number) : 'No mobile number on file';
    }

    private function checkMessage(string $message): void
    {
        $this->need(mb_strlen($message) >= 2, 'The message is empty. Write the text to send.');
        $parts = self::parts($message);
        $this->need($parts <= self::MAX_PARTS, "The message is too long: it would be {$parts} text messages. Keep it to ".self::MAX_PARTS.' or fewer (about 450 characters).');
    }

    /** Text messages needed: 160 characters in one (153 each when split), 70 and 67 when it has characters outside the GSM set. */
    public static function parts(string $text): int
    {
        $basic = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
        $extended = '^{}\\[~]|€';

        $units = 0;
        $gsm   = true;
        foreach (mb_str_split($text) as $char) {
            if (mb_strpos($basic, $char) !== false) {
                $units++;
            } elseif (mb_strpos($extended, $char) !== false) {
                $units += 2;
            } else {
                $gsm = false;
                break;
            }
        }

        if (! $gsm) {
            $units = mb_strlen($text);
            // Characters outside the basic plane count as two UCS-2 units.
            $units += preg_match_all('/[\x{10000}-\x{10FFFF}]/u', $text);
        }

        [$single, $multi] = $gsm ? [160, 153] : [70, 67];

        return $units <= $single ? 1 : (int) ceil($units / $multi);
    }

    /** "1 SMS credit", "2 SMS credits" */
    public static function credits(int $parts): string
    {
        return $parts.' SMS credit'.($parts === 1 ? '' : 's');
    }
}
