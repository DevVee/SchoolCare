<?php

namespace Tests\Feature\Settings;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Notifications\AppointmentStatusNotification;
use App\Notifications\InviteUserNotification;
use App\Services\AppointmentNotifier;
use App\Services\SettingsService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;
use Tests\TestCase;

class EmailNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_email_links_to_the_invitation_page(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        InviteUserNotification::sendTo($user);

        Notification::assertSentTo($user, InviteUserNotification::class, function ($n) use ($user) {
            $mail = $n->toMail($user);

            return str_contains($mail->subject, "You're invited to SchoolCare")
                && str_contains($mail->actionUrl, '/invitation/'.$n->token)
                && str_contains(implode(' ', $mail->outroLines), '3 days');
        });
    }

    public function test_brevo_mailer_uses_the_brevo_api_transport(): void
    {
        config(['services.brevo.key' => 'xkeysib-test']);

        $this->assertInstanceOf(BrevoApiTransport::class, Mail::mailer('brevo')->getSymfonyTransport());
    }

    public function test_emails_use_the_clinic_branding(): void
    {
        app(SettingsService::class)->setMany([
            'brand_primary_color' => '#0F766E',
            'clinic_name'         => 'Sunrise Clinic',
            'clinic_address'      => 'Balayan, Batangas',
            'clinic_contact'      => '0917 000 0000',
        ]);
        $user = User::factory()->create();

        $html = (string) (new InviteUserNotification('token123'))->toMail($user)->render();

        $this->assertStringContainsString('#0F766E', $html);       // accent bar + button
        $this->assertStringContainsString('Sunrise Clinic', $html); // sign-off + footer
        $this->assertStringContainsString('Balayan, Batangas', $html);
        $this->assertStringContainsString('0917 000 0000', $html);
        $this->assertStringNotContainsString('Laravel', $html);
        // No clinic email yet: no Reply-To, so the footer asks people not to reply.
        $this->assertNull(\App\Support\MailBrand::replyTo());
        $this->assertStringContainsString('Please do not reply', $html);

        app(SettingsService::class)->setMany(['clinic_email' => 'clinic@sunrise.test']);
        $html = (string) (new InviteUserNotification('token123'))->toMail($user)->render();

        $this->assertSame('clinic@sunrise.test', \App\Support\MailBrand::replyTo());
        $this->assertStringContainsString('clinic@sunrise.test', $html);
        $this->assertStringNotContainsString('Please do not reply', $html);
    }

    public function test_appointment_email_only_when_enabled_and_patient_has_email(): void
    {
        Notification::fake();
        $patient = Patient::factory()->create(['email' => 'pat@example.com']);
        $appointment = Appointment::factory()->create(['patient_id' => $patient->id]);

        app(AppointmentNotifier::class)->notify('approved', $appointment);
        Notification::assertNothingSent();

        app(SettingsService::class)->setMany(['notify_email_appointments' => true, 'clinic_name' => 'Sunrise Clinic']);
        app(AppointmentNotifier::class)->notify('approved', $appointment);

        Notification::assertSentOnDemand(AppointmentStatusNotification::class, function ($n, $channels, $notifiable) {
            return $notifiable->routes['mail'] === ['pat@example.com' => $n->appointment->patient->full_name]
                && $n->event === 'approved'
                && str_contains($n->toMail($notifiable)->subject, 'Sunrise Clinic');
        });
    }

    public function test_password_reset_email_is_branded(): void
    {
        app(SettingsService::class)->setMany(['app_name' => 'CareDesk']);
        $user = User::factory()->create();

        $mail = (new ResetPassword('token123'))->toMail($user);

        $this->assertStringContainsString('CareDesk', $mail->subject);
        $this->assertStringContainsString('token123', $mail->actionUrl);
    }
}
