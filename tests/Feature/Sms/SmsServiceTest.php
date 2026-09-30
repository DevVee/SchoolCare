<?php

namespace Tests\Feature\Sms;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\PatientLog;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\AppointmentNotifier;
use App\Services\SettingsService;
use App\Services\SmsService;
use Database\Seeders\AppointmentTimeSlotSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SmsServiceTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.semaphore.co/*';

    protected function setUp(): void
    {
        parent::setUp();

        config(['semaphore.api_key' => 'test-key', 'semaphore.sender_name' => 'ENVSENDER']);
    }

    private function sms(): SmsService
    {
        return app(SmsService::class);
    }

    private function enableSms(array $extra = []): void
    {
        app(SettingsService::class)->setMany(['sms_enabled' => true] + $extra);
    }

    private function fakeSuccess(): void
    {
        Http::fake([self::API => Http::response([[
            'message_id' => 12345, 'recipient' => '639171234567', 'status' => 'Pending',
        ]], 200)]);
    }

    // ─── Master switch / toggles ─────────────────────────────────────────────

    public function test_master_switch_off_logs_skipped_and_sends_nothing(): void
    {
        Http::fake();

        $log = $this->sms()->notify('appointment_approved', '09171234567', ['name' => 'Ana']);

        $this->assertSame('skipped', $log->status);
        $this->assertStringContainsString('turned off', $log->error_message);
        Http::assertNothingSent();

        // Manual send also respects the switch…
        $manual = $this->sms()->send('09171234567', 'Hello there');
        $this->assertSame('skipped', $manual->status);
        Http::assertNothingSent();
    }

    public function test_test_send_bypasses_master_switch(): void
    {
        $this->fakeSuccess();

        $log = $this->sms()->send('09171234567', 'Test', force: true, event: 'test');

        $this->assertSame('sent', $log->status);
        Http::assertSentCount(1);
    }

    public function test_disabled_event_is_skipped(): void
    {
        Http::fake();
        $this->enableSms(['notify_sms_appointment_created' => false]);

        $log = $this->sms()->notify('appointment_created', '09171234567', []);

        $this->assertSame('skipped', $log->status);
        Http::assertNothingSent();
    }

    // ─── Numbers ─────────────────────────────────────────────────────────────

    public static function numbers(): array
    {
        return [
            'local 09'         => ['09171234567', '639171234567'],
            'with spaces'      => ['0917 123 4567', '639171234567'],
            'with dashes'      => ['0917-123-4567', '639171234567'],
            'plus 63'          => ['+639171234567', '639171234567'],
            '63 no plus'       => ['639171234567', '639171234567'],
            'bare 9'           => ['9171234567', '639171234567'],
            'too short'        => ['0917123456', null],
            'landline'         => ['028123456', null],
            'letters'          => ['09abc234567', null],
            'foreign'          => ['+14155552671', null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_number_normalization(string $input, ?string $expected): void
    {
        $this->assertSame($expected, $this->sms()->normalizeNumber($input));
    }

    public function test_invalid_number_is_logged_as_failed_without_http(): void
    {
        Http::fake();
        $this->enableSms();

        $log = $this->sms()->notify('appointment_approved', '12345', ['name' => 'Ana']);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('Invalid mobile number', $log->error_message);
        Http::assertNothingSent();
    }

    public function test_missing_number_is_skipped(): void
    {
        Http::fake();
        $this->enableSms();

        $log = $this->sms()->notify('appointment_approved', null, ['name' => 'Ana']);

        $this->assertSame('skipped', $log->status);
        Http::assertNothingSent();
    }

    // ─── Templates ───────────────────────────────────────────────────────────

    public function test_template_placeholders_and_globals_are_rendered(): void
    {
        app(SettingsService::class)->setMany([
            'clinic_name'           => 'Sunrise Clinic',
            'clinic_contact'        => '0917 000 1111',
            'app_name'              => 'CareDesk',
            'sms_template_approval' => 'Hi {name}, {date} at {time}. {clinic} ({clinic_contact}) - {app} {unknown}',
        ]);

        $text = $this->sms()->render('sms_template_approval', ['name' => 'Ana', 'date' => 'Oct 1', 'time' => '9:00 AM']);

        $this->assertSame('Hi Ana, Oct 1 at 9:00 AM. Sunrise Clinic (0917 000 1111) - CareDesk {unknown}', $text);
    }

    public function test_empty_template_falls_back_to_default(): void
    {
        app(SettingsService::class)->setMany(['sms_template_reminder' => '']);

        $text = $this->sms()->render('sms_template_reminder', ['name' => 'Ana', 'date' => 'Oct 1', 'time' => '9:00 AM']);

        $this->assertStringStartsWith('Reminder: Dear Ana, you have an appointment at', $text);
    }

    // ─── Provider responses ──────────────────────────────────────────────────

    public function test_successful_send_uses_normalized_number_and_sender_setting(): void
    {
        $this->fakeSuccess();
        $this->enableSms(['sms_sender_name' => 'MYCLINIC']);

        $log = $this->sms()->notify('appointment_approved', '0917-123-4567', ['name' => 'Ana']);

        $this->assertSame('sent', $log->status);
        $this->assertSame('12345', $log->provider_message_id);
        $this->assertSame('639171234567', $log->recipient_number);
        $this->assertSame(1, $log->attempts);

        Http::assertSent(fn (Request $r) => $r['number'] === '639171234567'
            && $r['sendername'] === 'MYCLINIC'
            && $r['apikey'] === 'test-key');
    }

    public function test_sender_falls_back_to_env_when_setting_empty(): void
    {
        $this->fakeSuccess();
        $this->enableSms(['sms_sender_name' => '']);

        $this->sms()->notify('appointment_approved', '09171234567', []);

        Http::assertSent(fn (Request $r) => $r['sendername'] === 'ENVSENDER');
    }

    public function test_provider_error_body_is_logged_as_failed(): void
    {
        Http::fake([self::API => Http::response(['number' => ['The number format is invalid.']], 200)]);
        $this->enableSms();

        $log = $this->sms()->notify('appointment_approved', '09171234567', []);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('The number format is invalid.', $log->error_message);
        $this->assertNull($log->sent_at);
    }

    public function test_provider_http_error_is_logged_as_failed(): void
    {
        Http::fake([self::API => Http::response(['apikey' => ['The selected apikey is invalid.']], 401)]);
        $this->enableSms();

        $log = $this->sms()->notify('appointment_approved', '09171234567', []);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('HTTP 401', $log->error_message);
    }

    public function test_provider_failed_status_is_logged_as_failed(): void
    {
        Http::fake([self::API => Http::response([['message_id' => 9, 'status' => 'Failed']], 200)]);
        $this->enableSms();

        $log = $this->sms()->notify('appointment_approved', '09171234567', []);

        $this->assertSame('failed', $log->status);
    }

    public function test_missing_api_key_is_logged_as_failed(): void
    {
        Http::fake();
        config(['semaphore.api_key' => '']);
        $this->enableSms();

        $log = $this->sms()->notify('appointment_approved', '09171234567', []);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('API key not configured', $log->error_message);
        Http::assertNothingSent();
    }

    // ─── Workflows ───────────────────────────────────────────────────────────

    public function test_appointment_booking_approval_and_reschedule_send_sms(): void
    {
        $this->fakeSuccess();
        $this->enableSms();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(AppointmentTimeSlotSeeder::class);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('administrator');
        $patient = Patient::factory()->create(['contact_number' => '09171234567']);

        $date = now()->addDays(2);
        while ($date->isWeekend()) {
            $date->addDay();
        }
        $slot = \App\Models\AppointmentTimeSlot::where('is_active', true)->value('slot_time');

        $this->actingAs($admin)->post(route('appointments.store'), [
            'patient_id'       => $patient->id,
            'appointment_date' => $date->toDateString(),
            'appointment_time' => $slot,
            'purpose'          => 'Check-up',
        ])->assertSessionHasNoErrors();

        $appointment = Appointment::latest('id')->first();
        $this->assertDatabaseHas('sms_logs', ['event' => 'appointment_created', 'status' => 'sent', 'reference_id' => $appointment->id]);

        $this->actingAs($admin)->patch(route('appointments.approve', $appointment))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sms_logs', ['event' => 'appointment_approved', 'status' => 'sent', 'reference_id' => $appointment->id]);

        $newDate = $date->copy()->addDay();
        while ($newDate->isWeekend()) {
            $newDate->addDay();
        }
        $this->actingAs($admin)->put(route('appointments.update', $appointment), [
            'patient_id'       => $patient->id,
            'appointment_date' => $newDate->toDateString(),
            'appointment_time' => $slot,
            'purpose'          => 'Check-up',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sms_logs', ['event' => 'appointment_rescheduled', 'reference_id' => $appointment->id]);
    }

    public function test_daily_appointment_limit_is_enforced(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(AppointmentTimeSlotSeeder::class);
        app(SettingsService::class)->setMany(['max_daily_appointments' => 1]);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('administrator');

        $date = now()->addDays(3)->toDateString();
        Appointment::factory()->create(['appointment_date' => $date, 'status' => 'approved']);
        $slot = \App\Models\AppointmentTimeSlot::where('is_active', true)->orderByDesc('slot_time')->value('slot_time');

        $this->actingAs($admin)->post(route('appointments.store'), [
            'patient_id'       => Patient::factory()->create()->id,
            'appointment_date' => $date,
            'appointment_time' => $slot,
            'purpose'          => 'Check-up',
        ])->assertSessionHasErrors('appointment_date');
    }

    public function test_guardian_clinic_log_and_discharge_notices(): void
    {
        $this->fakeSuccess();
        $this->enableSms();

        $patient = Patient::factory()->create([
            'contact_number'   => null,
            'guardian_name'    => 'Mrs. Cruz',
            'guardian_contact' => '+63 917 555 0000',
        ]);
        $log = PatientLog::factory()->create(['patient_id' => $patient->id, 'sms_sent' => false]);

        $sms = $this->sms()->sendClinicLogNotice($log);
        $this->assertSame('sent', $sms->status);
        $this->assertSame('639175550000', $sms->recipient_number);
        $this->assertStringContainsString('Mrs. Cruz', $sms->message);
        $this->assertTrue($log->fresh()->sms_sent);

        $discharge = $this->sms()->sendDischargeNotice($log);
        $this->assertSame('clinic_discharge', $discharge->event);
        $this->assertSame('sent', $discharge->status);

        // Turning the discharge toggle off skips it.
        app(SettingsService::class)->setMany(['notify_sms_clinic_discharge' => false]);
        $this->assertSame('skipped', $this->sms()->sendDischargeNotice($log)->status);
    }

    public function test_reminder_command_sends_once_per_appointment(): void
    {
        $this->fakeSuccess();
        $this->enableSms(['reminder_hours_before' => 24]);

        $soon = now()->addHours(3);
        $due = Appointment::factory()->create([
            'status'           => 'approved',
            'appointment_date' => $soon->toDateString(),
            'appointment_time' => $soon->format('H:i:00'),
        ]);
        // Outside the window and not approved: never reminded.
        Appointment::factory()->create(['status' => 'approved', 'appointment_date' => now()->addDays(5)->toDateString()]);
        Appointment::factory()->create(['status' => 'pending', 'appointment_date' => $soon->toDateString(), 'appointment_time' => $soon->format('H:i:00')]);

        $this->artisan('appointments:send-reminders')->assertSuccessful();
        $this->artisan('appointments:send-reminders')->assertSuccessful();

        $this->assertSame(1, SmsLog::where('event', 'appointment_reminder')->count());
        $this->assertDatabaseHas('sms_logs', ['event' => 'appointment_reminder', 'reference_id' => $due->id, 'status' => 'sent']);
        $this->assertNotNull($due->fresh()->reminder_sent_at);
        Http::assertSentCount(1);

        // Rescheduling clears the flag so a new reminder can go out.
        app(AppointmentNotifier::class)->notify('rescheduled', $due->fresh());
        $this->assertNull($due->fresh()->reminder_sent_at);
    }

    public function test_reminder_command_does_nothing_when_sms_is_off(): void
    {
        Http::fake();

        $soon = now()->addHours(2);
        $appointment = Appointment::factory()->create([
            'status'           => 'approved',
            'appointment_date' => $soon->toDateString(),
            'appointment_time' => $soon->format('H:i:00'),
        ]);

        $this->artisan('appointments:send-reminders')->assertSuccessful();

        $this->assertNull($appointment->fresh()->reminder_sent_at);
        $this->assertSame(0, SmsLog::count());
        Http::assertNothingSent();
    }
}
