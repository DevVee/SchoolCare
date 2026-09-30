<?php

namespace Tests\Feature\Parity;

use App\Jobs\SendSmsJob;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\PatientLog;
use App\Models\SmsLog;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Clinical\ClinicalTestCase;

class DischargeTest extends ClinicalTestCase
{
    private function inClinicLog(array $attrs = []): PatientLog
    {
        $patient = Patient::factory()->create([
            'first_name' => 'Juana', 'last_name' => 'Incliniccase',
            'guardian_name' => 'Mrs. Cruz', 'guardian_contact' => '09175550000',
        ]);

        return PatientLog::factory()->create($attrs + [
            'patient_id' => $patient->id, 'log_date' => today()->toDateString(),
            'time_in' => now()->subMinutes(30)->format('H:i'), 'time_out' => null,
            'disposition' => 'rest_in_clinic', 'severity' => 'Mild', 'reasons' => ['Headache'],
        ]);
    }

    public function test_in_clinic_patients_show_on_logbook_and_dashboard(): void
    {
        $this->inClinicLog();
        PatientLog::factory()->create(['time_out' => '10:00', 'patient_id' => Patient::factory()->create(['last_name' => 'Gonehomecase'])->id]);

        foreach ([route('patient-logs.index'), route('dashboard')] as $url) {
            $this->actingAs($this->admin)->get($url)
                ->assertOk()
                ->assertSee('Currently in clinic')
                ->assertSee('Incliniccase');
        }

        $this->assertSame(1, PatientLog::inClinic()->count());
    }

    public function test_discharge_sets_time_out_disposition_and_queues_guardian_sms_when_enabled(): void
    {
        Queue::fake();
        config(['semaphore.api_key' => 'test-key']);
        app(SettingsService::class)->setMany(['sms_enabled' => true, 'notify_sms_clinic_discharge' => true]);

        $log = $this->inClinicLog();

        $this->actingAs($this->admin)
            ->patch(route('patient-logs.discharge', $log), ['disposition' => 'sent_home', 'notify_guardian' => '1'])
            ->assertSessionHas('success');

        $log->refresh();
        $this->assertNotNull($log->time_out);
        $this->assertSame('sent_home', $log->disposition);
        $this->assertSame(0, PatientLog::inClinic()->count());

        $sms = SmsLog::where('event', 'clinic_discharge')->firstOrFail();
        $this->assertSame('pending', $sms->status);
        $this->assertSame('639175550000', $sms->recipient_number);
        $this->assertSame($log->id, (int) $sms->reference_id);
        Queue::assertPushed(SendSmsJob::class);

        // Audited through the patient log observer.
        $this->assertTrue(AuditLog::where('module', 'patient_logs')->where('action', 'updated')->exists());
    }

    public function test_discharge_sms_is_skipped_when_turned_off(): void
    {
        Queue::fake();
        app(SettingsService::class)->setMany(['sms_enabled' => true, 'notify_sms_clinic_discharge' => false]);

        $log = $this->inClinicLog();
        $this->actingAs($this->admin)->patch(route('patient-logs.discharge', $log))->assertSessionHas('success');

        $this->assertNotNull($log->fresh()->time_out);
        $this->assertSame('skipped', SmsLog::where('event', 'clinic_discharge')->value('status'));
        Queue::assertNotPushed(SendSmsJob::class);
    }

    public function test_staff_can_discharge_nurse_only_and_double_discharge_is_rejected(): void
    {
        $log = $this->inClinicLog();

        // staff: view-patient-logs only
        $this->actingAs($this->userWithRole('staff'))
            ->patch(route('patient-logs.discharge', $log))
            ->assertForbidden();
        $this->assertNull($log->fresh()->time_out);

        $nurse = $this->userWithRole('nurse');
        $this->actingAs($nurse)->patch(route('patient-logs.discharge', $log), ['notify_guardian' => '0'])->assertSessionHas('success');
        $firstOut = $log->fresh()->time_out;
        $this->assertNotNull($firstOut);

        $this->actingAs($nurse)->patch(route('patient-logs.discharge', $log))->assertSessionHas('error');
        $this->assertSame($firstOut, $log->fresh()->time_out);
    }
}
