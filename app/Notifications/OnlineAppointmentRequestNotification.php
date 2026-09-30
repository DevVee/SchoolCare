<?php

namespace App\Notifications;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email to the clinic when someone requests an appointment on the website.
 * Sent by PublicAppointmentController when Settings > Notifications >
 * "Email: new online request to the clinic" is on, to the address set there
 * (or the clinic email).
 */
class OnlineAppointmentRequestNotification extends Notification
{
    use Queueable;

    public function __construct(public Appointment $appointment) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $a    = $this->appointment;
        $date = $a->appointment_date?->format('l, F j, Y');
        $time = $a->appointment_time ? Carbon::parse($a->appointment_time)->format('g:i A') : '';

        $mail = (new MailMessage)
            ->subject('New online appointment request: '.$a->requester_name)
            ->greeting('New appointment request')
            ->line("{$a->requester_name} asked for an appointment on {$date} at {$time}. It is waiting for your review.")
            ->line('**Reason:** '.$a->purpose);

        if ($a->provider) {
            $mail->line('**With:** '.$a->provider);
        }
        if ($a->requester_category_label) {
            $mail->line('**Category:** '.$a->requester_category_label.($a->requester_school_line ? ' ('.$a->requester_school_line.')' : ''));
        }
        $mail->line('**Mobile:** '.$a->requester_contact);

        return $mail
            ->action('Review the request', route('appointments.show', $a))
            ->line($a->patient_id ? 'It was linked to the matching patient record.' : 'Link it to a patient record before you approve it.');
    }
}
