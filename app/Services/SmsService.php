<?php

namespace App\Services;

use App\Jobs\SendSmsJob;
use App\Models\Appointment;
use App\Models\PatientLog;
use App\Models\SmsLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * SMS via Semaphore (https://semaphore.co).
 *
 *  send()    — synchronous; used by manual send and the settings test button.
 *  notify()  — automatic workflow notifications; checks the master switch and
 *              the per-event toggle, renders the template and queues SendSmsJob.
 *  deliver() — performs the HTTP call and records the outcome on the SmsLog.
 *
 * Every attempt is written to sms_logs with status pending|sent|failed|skipped.
 */
class SmsService
{
    public const API_URL = 'https://api.semaphore.co/api/v4/messages';

    // ─── Configuration ───────────────────────────────────────────────────────

    public function enabled(): bool
    {
        return (bool) settings('sms_enabled', false);
    }

    public function apiKeyConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    public function senderName(): string
    {
        $name = trim((string) settings('sms_sender_name', ''));

        return $name !== '' ? $name : (string) config('semaphore.sender_name', '');
    }

    public function eventEnabled(string $event): bool
    {
        $toggle = config("settings.sms_events.{$event}.toggle");

        return $toggle ? (bool) settings($toggle, true) : true;
    }

    private function apiKey(): string
    {
        return trim((string) config('semaphore.api_key', ''));
    }

    // ─── Numbers & templates ─────────────────────────────────────────────────

    /**
     * Normalize a Philippine mobile number to 639XXXXXXXXX.
     * Accepts 09XXXXXXXXX, 9XXXXXXXXX, 639XXXXXXXXX and +639XXXXXXXXX, with
     * spaces, dashes, dots or parentheses. Returns null when invalid.
     */
    public function normalizeNumber(?string $number): ?string
    {
        if ($number === null) {
            return null;
        }

        $digits = preg_replace('/[\s\-\.\(\)]/', '', trim($number));
        $digits = ltrim($digits, '+');

        if (! ctype_digit($digits)) {
            return null;
        }

        return match (true) {
            (bool) preg_match('/^09\d{9}$/', $digits)  => '63'.substr($digits, 1),
            (bool) preg_match('/^9\d{9}$/', $digits)   => '63'.$digits,
            (bool) preg_match('/^639\d{9}$/', $digits) => $digits,
            default                                     => null,
        };
    }

    /** Values available in every template. */
    public function globals(): array
    {
        return [
            'clinic'         => (string) settings('clinic_name', ''),
            'clinic_contact' => (string) settings('clinic_contact', ''),
            'app'            => (string) (settings('app_name') ?: config('app.name')),
        ];
    }

    /**
     * Render a template setting with {placeholders}. An empty stored template
     * falls back to the default in config/settings.php. Unknown placeholders
     * are left untouched.
     */
    public function render(string $templateKey, array $vars = []): string
    {
        $template = trim((string) settings($templateKey, ''));
        if ($template === '') {
            $template = (string) (settings()->definition($templateKey)['default'] ?? '');
        }

        $vars = array_merge($this->globals(), $vars);

        return preg_replace_callback('/\{([a-z_]+)\}/i', function ($m) use ($vars) {
            return array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : $m[0];
        }, $template);
    }

    // ─── Sending ─────────────────────────────────────────────────────────────

    /**
     * Send immediately and return the log. Respects the master switch unless
     * $force is true (settings test button).
     */
    public function send(
        string $number,
        string $message,
        ?string $recipientName = null,
        mixed $reference = null,
        ?string $event = null,
        bool $force = false,
    ): SmsLog {
        $log = $this->createLog($number, $message, $recipientName, $reference, $event);

        if (! $force && ! $this->enabled()) {
            return $this->skip($log, 'SMS is turned off (Settings → Notifications → Enable SMS).');
        }

        $normalized = $this->normalizeNumber($number);
        if (! $normalized) {
            return $this->fail($log, "Invalid mobile number \"{$number}\".");
        }

        $log->update(['recipient_number' => $normalized]);

        return $this->deliver($log);
    }

    /**
     * Automatic notification for a workflow event (see settings.sms_events).
     * Returns the SmsLog (pending → delivered by SendSmsJob), a skipped/failed
     * log explaining why nothing was sent, or null for an unknown event.
     */
    public function notify(
        string $event,
        ?string $number,
        array $vars = [],
        ?Model $reference = null,
        ?string $recipientName = null,
    ): ?SmsLog {
        $cfg = config("settings.sms_events.{$event}");
        if (! $cfg) {
            Log::warning("SmsService::notify called with unknown event [{$event}]");

            return null;
        }

        $message = $this->render($cfg['template'], $vars);
        $log     = $this->createLog((string) $number, $message, $recipientName, $reference, $event);

        if (! $this->enabled()) {
            return $this->skip($log, 'SMS is turned off (master switch).');
        }

        if (! $this->eventEnabled($event)) {
            return $this->skip($log, "\"{$cfg['label']}\" SMS is turned off in Settings → Notifications.");
        }

        if (blank($number)) {
            return $this->skip($log, 'No mobile number on file.');
        }

        $normalized = $this->normalizeNumber($number);
        if (! $normalized) {
            return $this->fail($log, "Invalid mobile number \"{$number}\".");
        }

        $log->update(['recipient_number' => $normalized]);

        try {
            SendSmsJob::dispatch($log->id)->afterCommit();
        } catch (\Throwable $e) {
            // Queue unavailable — never break the calling workflow.
            Log::error('Could not queue SMS', ['sms_log_id' => $log->id, 'error' => $e->getMessage()]);
            $this->fail($log, 'Could not queue SMS: '.Str::limit($e->getMessage(), 200));
        }

        return $log->refresh();
    }

    /**
     * Call Semaphore for a pending log and record the outcome.
     * With $throwOnTransient, connection errors / 5xx rethrow so the queued
     * job can retry (the log stays pending with the last error).
     */
    public function deliver(SmsLog $log, bool $throwOnTransient = false): SmsLog
    {
        if (! $this->apiKeyConfigured()) {
            return $this->fail($log, 'Semaphore API key not configured (SEMAPHORE_API_KEY).');
        }

        $log->increment('attempts');

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout(15)
                ->post(self::API_URL, [
                    'apikey'     => $this->apiKey(),
                    'number'     => $log->recipient_number,
                    'message'    => $log->message,
                    'sendername' => $this->senderName(),
                ]);
        } catch (ConnectionException $e) {
            $error = 'Could not reach Semaphore: '.Str::limit($e->getMessage(), 200);
            if ($throwOnTransient) {
                $log->update(['error_message' => $error]);
                throw $e;
            }

            return $this->fail($log, $error);
        } catch (\Throwable $e) {
            // TLS/certificate problems and other transport errors surface as
            // Guzzle RequestExceptions rather than ConnectionException.
            Log::error('SMS delivery error', ['sms_log_id' => $log->id, 'error' => $e->getMessage()]);
            $error = str_contains($e->getMessage(), 'SSL certificate')
                ? 'Secure connection to Semaphore failed (server certificate could not be verified).'
                : 'Could not send through Semaphore: '.Str::limit($e->getMessage(), 200);
            if ($throwOnTransient) {
                $log->update(['error_message' => $error]);
                throw $e;
            }

            return $this->fail($log, $error);
        }

        $body = $response->json();

        if ($response->serverError() && $throwOnTransient) {
            $log->update(['error_message' => "Semaphore HTTP {$response->status()} (will retry)", 'api_response' => $this->safeBody($body, $response->body())]);
            throw new \RuntimeException("Semaphore HTTP {$response->status()}");
        }

        [$ok, $messageId, $error] = $this->parseResponse($response->status(), $body, $response->body());

        if (! $ok) {
            $log->api_response = $this->safeBody($body, $response->body());

            return $this->fail($log, $error);
        }

        $log->update([
            'status'              => 'sent',
            'sent_at'             => now(),
            'api_response'        => $this->safeBody($body, $response->body()),
            'provider_message_id' => $messageId,
            'error_message'       => null,
        ]);

        $this->afterSent($log);

        return $log;
    }

    public function fail(SmsLog $log, string $reason): SmsLog
    {
        $log->status        = 'failed';
        $log->error_message = Str::limit($reason, 1000);
        $log->save();

        return $log;
    }

    // ─── Workflow helpers ────────────────────────────────────────────────────

    /** Guardian visit notice for a clinic log entry (form checkbox + setting). */
    public function sendClinicLogNotice(PatientLog $log): ?SmsLog
    {
        return $this->notifyGuardian('clinic_log', $log, Carbon::parse($log->time_in));
    }

    /**
     * Guardian discharge notice for a clinic log entry. Call when a logged
     * patient is discharged (time_out recorded / disposition finalised).
     */
    public function sendDischargeNotice(PatientLog $log): ?SmsLog
    {
        $time = $log->time_out ? Carbon::parse($log->time_out) : now();

        return $this->notifyGuardian('clinic_discharge', $log, $time);
    }

    /** @deprecated use AppointmentNotifier::notify('approved', $appointment) */
    public function sendAppointmentApproval(Appointment $appointment): ?SmsLog
    {
        app(AppointmentNotifier::class)->notify('approved', $appointment);

        return null;
    }

    /** @deprecated use AppointmentNotifier::notify('cancelled', $appointment) */
    public function sendAppointmentCancellation(Appointment $appointment, string $reason = ''): ?SmsLog
    {
        app(AppointmentNotifier::class)->notify('cancelled', $appointment, ['reason' => $reason]);

        return null;
    }

    /** First valid mobile number among the candidates (raw value kept if none is valid). */
    public function pickNumber(?string ...$candidates): ?string
    {
        $candidates = array_values(array_filter($candidates, fn ($n) => filled($n)));
        foreach ($candidates as $n) {
            if ($this->normalizeNumber($n)) {
                return $n;
            }
        }

        return $candidates[0] ?? null;
    }

    // ─── Internals ───────────────────────────────────────────────────────────

    private function notifyGuardian(string $event, PatientLog $log, Carbon $time): ?SmsLog
    {
        $log->loadMissing('patient');
        $patient = $log->patient;
        if (! $patient) {
            return null;
        }

        $number = $this->pickNumber($patient->guardian_contact, $patient->contact_number);

        return $this->notify($event, $number, [
            'guardian'    => $patient->guardian_name ?: 'Parent/Guardian',
            'name'        => $patient->first_name,
            'full_name'   => $patient->full_name,
            'date'        => $log->log_date?->format('F d, Y') ?? now()->format('F d, Y'),
            'time'        => $time->format('h:i A'),
            // Structured reasons (+ "Other"), else the free-text complaint.
            'complaint'   => $log->complaint_summary,
            'treatment'   => $log->treatment ?: 'Attended by clinic staff',
            'disposition' => $log->disposition_label ?? '',
        ], $log, $patient->guardian_name ?: $patient->full_name);
    }

    private function createLog(string $number, string $message, ?string $name, mixed $reference, ?string $event): SmsLog
    {
        return SmsLog::create([
            'recipient_number' => Str::limit($number, 20, ''),
            'recipient_name'   => $name ? Str::limit($name, 250, '') : null,
            'message'          => $message,
            'event'            => $event,
            'status'           => 'pending',
            'reference_id'     => $reference?->getKey(),
            'reference_type'   => $reference ? $reference->getMorphClass() : null,
            'created_by'       => auth()->id(),
        ]);
    }

    private function skip(SmsLog $log, string $reason): SmsLog
    {
        $log->update(['status' => 'skipped', 'error_message' => $reason]);

        return $log;
    }

    /**
     * Semaphore returns a JSON list of message objects on success:
     *   [{"message_id":123,"recipient":"639…","status":"Pending",…}]
     * and an object/list of validation errors otherwise, e.g.
     *   {"number":["The number format is invalid."]}
     *
     * @return array{0:bool,1:?string,2:string}
     */
    private function parseResponse(int $status, mixed $body, string $raw): array
    {
        if ($status >= 400) {
            return [false, null, "Semaphore HTTP {$status}: ".$this->flattenError($body, $raw)];
        }

        if (is_array($body) && array_is_list($body) && isset($body[0]) && is_array($body[0]) && array_key_exists('message_id', $body[0])) {
            $providerStatus = strtolower((string) ($body[0]['status'] ?? ''));
            if (in_array($providerStatus, ['failed', 'refunded'], true)) {
                return [false, (string) $body[0]['message_id'], 'Semaphore reported status "'.$body[0]['status'].'".'];
            }

            return [true, (string) $body[0]['message_id'], ''];
        }

        return [false, null, 'Semaphore rejected the message: '.$this->flattenError($body, $raw)];
    }

    private function flattenError(mixed $body, string $raw): string
    {
        if (is_array($body)) {
            $messages = [];
            array_walk_recursive($body, function ($v) use (&$messages) {
                if (is_string($v) && $v !== '') {
                    $messages[] = $v;
                }
            });
            if ($messages) {
                return Str::limit(implode(' ', $messages), 500);
            }
        }

        return $raw !== '' ? Str::limit(strip_tags($raw), 300) : 'Unexpected empty response.';
    }

    private function safeBody(mixed $body, string $raw): array
    {
        return is_array($body) ? $body : ['raw' => Str::limit($raw, 1000)];
    }

    private function afterSent(SmsLog $log): void
    {
        if ($log->event === 'clinic_log' && $log->reference_type === (new PatientLog)->getMorphClass()) {
            PatientLog::withoutTimestamps(
                fn () => PatientLog::whereKey($log->reference_id)->update(['sms_sent' => true])
            );
        }
    }
}
