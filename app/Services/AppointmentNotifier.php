<?php

namespace App\Services;

use App\Models\Appointment;
use App\Notifications\AppointmentStatusNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Sends appointment notifications (SMS + optional email) for an event:
 * created | approved | rescheduled | cancelled | reminder.
 *
 * Never throws — a notification problem must not roll back or break the
 * appointment workflow. Every SMS attempt is visible in the SMS log.
 */
class AppointmentNotifier
{
    public const EVENTS = ['created', 'approved', 'rescheduled', 'cancelled', 'reminder'];

    public function __construct(private readonly SmsService $sms) {}

    /**
     * @param  array  $extra  extra template variables, e.g. ['reason' => '...']
     */
    public function notify(string $event, Appointment $appointment, array $extra = []): void
    {
        if (! in_array($event, self::EVENTS, true)) {
            return;
        }

        try {
            $appointment->loadMissing('patient');
            $patient = $appointment->patient;

            // Unlinked online request: notify the requester with what they typed.
            $recipient = $patient
                ? ['first' => $patient->first_name, 'full' => $patient->full_name, 'numbers' => [$patient->contact_number, $patient->guardian_contact], 'email' => $patient->email]
                : ['first' => strtok(trim((string) $appointment->requester_name), ' ') ?: '', 'full' => (string) $appointment->requester_name, 'numbers' => [$appointment->requester_contact], 'email' => $appointment->requester_email];
            if (! $patient && blank($appointment->requester_name)) {
                return;
            }

            if ($event === 'rescheduled' && $appointment->reminder_sent_at) {
                // New date/time → allow a fresh reminder.
                $appointment->forceFill(['reminder_sent_at' => null])->saveQuietly();
            }

            $vars = array_merge([
                'name'      => $recipient['first'],
                'full_name' => $recipient['full'],
                'date'      => $appointment->appointment_date?->format('F d, Y') ?? '',
                'time'      => $appointment->appointment_time
                    ? Carbon::parse($appointment->appointment_time)->format('h:i A')
                    : '',
                'purpose'   => (string) $appointment->purpose,
                'reason'    => (string) ($appointment->cancelled_reason ?: 'Not specified'),
            ], array_filter($extra, fn ($v) => $v !== null && $v !== ''));

            $number = $this->sms->pickNumber(...$recipient['numbers']);

            $this->sms->notify("appointment_{$event}", $number, $vars, $appointment, $recipient['full']);

            if (settings('notify_email_appointments', false) && filled($recipient['email'])
                && filter_var($recipient['email'], FILTER_VALIDATE_EMAIL)) {
                // Sent now (not queued) so it goes out without a queue worker.
                Notification::route('mail', [$recipient['email'] => $recipient['full']])
                    ->notifyNow(new AppointmentStatusNotification($appointment, $event));
            }
        } catch (\Throwable $e) {
            Log::warning("Appointment notification [{$event}] failed", [
                'appointment_id' => $appointment->id,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    /** True when the date or time of the appointment changed in the last save. */
    public static function wasRescheduled(Appointment $appointment): bool
    {
        return $appointment->wasChanged('appointment_date') || $appointment->wasChanged('appointment_time');
    }
}
