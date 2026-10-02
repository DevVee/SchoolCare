<?php

namespace App\Services\Coco\Actions;

use App\Mail\AssistantMessageMail;
use App\Models\Patient;
use App\Models\User;
use App\Services\Coco\CocoRefusal;
use App\Services\Coco\PatientFinder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * One email to one person: a patient (the email on their record), a staff
 * member (their account email) or an address the user typed. Sent through the
 * app's mailer (Brevo in production) with the branded layout.
 */
class SendEmail extends CocoAction
{
    public const MAX_SUBJECT = 150;
    public const MAX_BODY    = 5000;

    public function __construct(private readonly PatientFinder $finder) {}

    public function type(): string { return 'send_email'; }

    public function title(): string { return 'Send email'; }

    public function icon(): string { return 'envelope'; }

    public function group(): string { return 'messages'; }

    /** The app's messaging permission ("Send SMS") also covers email. */
    public function allowedFor(User $user): bool
    {
        return $user->can('send-sms');
    }

    public function tool(): array
    {
        return $this->makeTool(
            'Prepare ONE email to ONE person, as a card the user can edit. Sent only if the user taps Confirm. Guardians have no email on file: text them instead.',
            [
                'to'           => ['type' => 'string', 'enum' => ['patient', 'staff', 'address'], 'description' => 'address: an email address the user typed.'],
                'patient_id'   => ['type' => 'integer'],
                'patient_name' => ['type' => 'string', 'description' => 'Name or patient number as the user wrote it, when there is no id.'],
                'staff_name'   => ['type' => 'string', 'description' => 'When to is staff.'],
                'email'        => ['type' => 'string', 'description' => 'When to is address.'],
                'subject'      => ['type' => 'string'],
                'body'         => ['type' => 'string', 'description' => 'Plain text with greeting and sign-off, no Markdown.'],
            ],
            ['to', 'subject', 'body'],
        );
    }

    public function propose(array $args, User $user): array
    {
        $to      = in_array($args['to'] ?? null, ['patient', 'staff', 'address', 'guardian'], true) ? $args['to'] : 'patient';
        $subject = $this->plain($this->str($args, 'subject', 500));
        $body    = $this->plain($this->str($args, 'body', 20000));
        $this->checkText($subject, $body);

        $this->need($to !== 'guardian', 'Guardians have no email address on file. Offer to text the guardian instead.');

        if ($to === 'address') {
            $email = $this->str($args, 'email', 200);
            $this->need(filter_var($email, FILTER_VALIDATE_EMAIL) !== false, 'That is not a valid email address. Ask the user to check it.');

            return [
                'payload'    => ['to' => 'address', 'email' => $email, 'subject' => $subject, 'body' => $body, 'candidates' => ['address']],
                'candidates' => [],
            ];
        }

        if ($to === 'staff') {
            $name  = $this->str($args, 'staff_name', 100);
            $staff = $name === '' ? collect() : $this->finder->staff($name);
            $this->need($staff->isNotEmpty(), 'No active staff account matches "'.$name.'". Ask the user for the full name or the email address.');

            return [
                'payload'    => ['to' => 'staff', 'subject' => $subject, 'body' => $body, 'candidates' => $staff->map(fn (User $u) => (string) $u->id)->values()->all()],
                'candidates' => $staff->map(fn (User $u) => [
                    'id' => (string) $u->id, 'label' => $u->name, 'detail' => Str::headline((string) $u->role_name), 'meta' => PatientFinder::maskEmail($u->email),
                ])->values()->all(),
            ];
        }

        $this->need($user->can('view-patients'), 'This user cannot see patient records, so the email can only go to an address they type.');
        $patients = $this->finder->resolve($args['patient_id'] ?? null, $args['patient_name'] ?? null);
        $usable   = $patients->filter(fn (Patient $p) => $this->validEmail($p->email));

        if ($usable->isEmpty()) {
            throw CocoRefusal::because($patients->count() === 1
                ? 'This patient has no email address on file. Offer to send a text message instead.'
                : "None of the {$patients->count()} matching patients has an email address on file.");
        }

        return [
            'payload'    => ['to' => 'patient', 'subject' => $subject, 'body' => $body, 'candidates' => $usable->map(fn (Patient $p) => (string) $p->id)->values()->all()],
            'candidates' => $patients->map(fn (Patient $p) => [
                'id'       => (string) $p->id,
                'label'    => $p->full_name,
                'detail'   => PatientFinder::describe($p),
                'meta'     => $this->validEmail($p->email) ? PatientFinder::maskEmail($p->email) : 'No email on file',
                'disabled' => ! $this->validEmail($p->email),
            ])->values()->all(),
        ];
    }

    public function plan(array $payload, ?string $choice, array $edits, User $user): array
    {
        $this->need($this->allowedFor($user), 'You do not have permission to send messages.');

        $subject = $this->plain(isset($edits['subject']) ? (string) $edits['subject'] : (string) $payload['subject']);
        $body    = $this->plain(isset($edits['message']) ? (string) $edits['message'] : (string) $payload['body']);
        $this->checkText($subject, $body);

        switch ($payload['to']) {
            case 'address':
                $email = (string) $payload['email'];
                $name  = $email;
                break;

            case 'staff':
                $staff = User::query()->where('is_active', true)->find($this->chosen($payload, $choice));
                $this->need($staff !== null, 'That staff account is no longer active.');
                [$email, $name] = [(string) $staff->email, (string) $staff->name];
                break;

            default:
                $this->need($user->can('view-patients'), 'You do not have permission to see patient records.');
                $patient = $this->finder->find($this->chosen($payload, $choice));
                $this->need($patient !== null, 'That patient record is no longer active.');
                $this->need($this->validEmail($patient->email), "{$patient->full_name} has no email address on file.");
                [$email, $name] = [(string) $patient->email, $patient->full_name];
        }

        $this->need($this->validEmail($email), 'The email address is not valid.');

        $notes = [];
        if ($this->logOnly()) {
            $notes[] = 'Email sending is not set up on the server, so the email would only be saved to the system log.';
        }

        return [
            'summary'  => "Send email to {$name}: {$this->limit($subject, 60)}",
            'fields'   => [
                ['label' => 'To', 'value' => $name, 'recipient' => true],
                ['label' => 'Email', 'value' => PatientFinder::maskEmail($email), 'recipient' => true],
            ],
            'editable' => ['kind' => 'email', 'subject' => $subject, 'message' => $body, 'max' => self::MAX_BODY],
            'notes'    => $notes,
            'email'    => $email,
            'name'     => $name,
            'subject'  => $subject,
            'body'     => $body,
        ];
    }

    public function execute(array $plan, User $user): array
    {
        try {
            Mail::to($plan['email'], $plan['name'])->send(new AssistantMessageMail($plan['subject'], $plan['body'], (string) $user->name));
        } catch (\Throwable $e) {
            Log::warning('Assistant email failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'text' => 'Not sent: the email service did not accept it. '.Str::limit($e->getMessage(), 150)];
        }

        return [
            'ok'   => true,
            'text' => "Emailed {$plan['name']}.".($this->logOnly() ? ' Email sending is not set up, so it was saved to the system log instead.' : ''),
        ];
    }

    private function checkText(string $subject, string $body): void
    {
        $this->need($subject !== '', 'The email needs a subject.');
        $this->need(mb_strlen($subject) <= self::MAX_SUBJECT, 'The subject is too long. Keep it under '.self::MAX_SUBJECT.' characters.');
        $this->need(mb_strlen($body) >= 2, 'The email is empty. Write the text to send.');
        $this->need(mb_strlen($body) <= self::MAX_BODY, 'The email is too long. Keep it under '.self::MAX_BODY.' characters.');
    }

    private function validEmail(?string $email): bool
    {
        return filled($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** MAIL_MAILER=log or array: nothing is delivered. */
    private function logOnly(): bool
    {
        $mailer = (string) config('mail.default');

        return in_array((string) config("mail.mailers.{$mailer}.transport", $mailer), ['log', 'array'], true);
    }
}
