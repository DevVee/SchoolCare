<?php

namespace Tests\Feature\Settings;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Notifications\AppointmentStatusNotification;
use App\Notifications\WelcomeUserNotification;
use App\Services\AppointmentNotifier;
use App\Services\SettingsService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_email_helper_respects_setting(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->assertTrue(WelcomeUserNotification::sendTo($user));
        Notification::assertSentTo($user, WelcomeUserNotification::class, function ($n) use ($user) {
            $mail = $n->toMail($user);

            return str_contains($mail->subject, 'Welcome to SSCMS')
                && str_contains($mail->actionUrl, '/reset-password/');
        });

        app(SettingsService::class)->setMany(['notify_email_user_created' => false]);
        $other = User::factory()->create();
        $this->assertFalse(WelcomeUserNotification::sendTo($other));
        Notification::assertNotSentTo($other, WelcomeUserNotification::class);
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
