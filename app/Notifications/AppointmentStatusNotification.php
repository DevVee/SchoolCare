<?php

namespace App\Notifications;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email to a patient about their appointment. Sent (queued) by
 * AppointmentNotifier to Notification::route('mail', $patient->email) when
 * the "Email: appointment updates" setting is on.
 */
class AppointmentStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public Appointment $appointment,
        public string $event,           // created | approved | rescheduled | cancelled | reminder
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $a       = $this->appointment->loadMissing('patient');
        $clinic  = settings('clinic_name') ?: config('app.name');
        $date    = $a->appointment_date?->format('F d, Y');
        $time    = $a->appointment_time ? Carbon::parse($a->appointment_time)->format('h:i A') : '';
        // An online request not linked to a patient yet: the name typed on the form.
        $name    = $a->patient?->first_name ?: (strtok(trim((string) $a->requester_name), ' ') ?: 'there');

        [$subject, $intro] = match ($this->event) {
            'created'     => ['Appointment request received', "We received your appointment request for {$date} at {$time}. It is pending approval."],
            'approved'    => ['Appointment approved',         "Your appointment on {$date} at {$time} has been approved. Please arrive 10 minutes early."],
            'rescheduled' => ['Appointment rescheduled',      "Your appointment has been moved to {$date} at {$time}."],
            'cancelled'   => ['Appointment cancelled',        "Your appointment on {$date} has been cancelled.".($a->cancelled_reason ? " Reason: {$a->cancelled_reason}." : '')],
            'reminder'    => ['Appointment reminder',         "This is a reminder of your appointment on {$date} at {$time}."],
            default       => ['Appointment update',           "There is an update to your appointment on {$date} at {$time}."],
        };

        $mail = (new MailMessage)
            ->subject("{$clinic}: {$subject}")
            ->greeting("Hello {$name},")
            ->line($intro);

        if ($a->purpose) {
            $mail->line("**Purpose:** {$a->purpose}");
        }

        if ($contact = settings('clinic_contact')) {
            $mail->line("Questions? Contact the clinic at {$contact}.");
        }

        return $mail;
    }
}
