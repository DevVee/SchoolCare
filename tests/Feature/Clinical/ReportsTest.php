<?php

namespace Tests\Feature\Clinical;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Consultation;
use App\Models\DispensingRecord;
use App\Models\Patient;
use App\Models\PatientLog;
use App\Services\ReportService;

class ReportsTest extends ClinicalTestCase
{
    public function test_appointments_report_date_range_is_inclusive(): void
    {
        $first = Appointment::factory()->create(['appointment_date' => '2026-09-01']);
        $last  = Appointment::factory()->create(['appointment_date' => '2026-09-30']);
        Appointment::factory()->create(['appointment_date' => '2026-10-01']);
        Appointment::factory()->create(['appointment_date' => '2026-08-31']);

        $report = app(ReportService::class)->appointmentsReport('2026-09-01', '2026-09-30');

        $this->assertEqualsCanonicalizing([$first->id, $last->id], $report['appointments']->pluck('id')->all());
    }

    public function test_medicine_usage_includes_the_last_day(): void
    {
        DispensingRecord::factory()->create(['dispensed_at' => '2026-09-30 16:45:00', 'quantity' => 3]);

        $report = app(ReportService::class)->medicineUsageReport('2026-09-01', '2026-09-30');

        $this->assertSame(3, (int) $report['totalDispensed']);
    }

    public function test_monthly_report_counts_clinic_visits_and_sscms_sections(): void
    {
        $p1 = Patient::factory()->create(['category' => 'college']);
        $p2 = Patient::factory()->create(['category' => 'teacher']);

        PatientLog::factory()->count(3)->create(['patient_id' => $p1->id, 'log_date' => '2026-09-30', 'chief_complaint' => 'Headache']);
        PatientLog::factory()->create(['patient_id' => $p2->id, 'log_date' => '2026-09-01', 'chief_complaint' => ' headache ']);
        PatientLog::factory()->create(['patient_id' => $p2->id, 'log_date' => '2026-10-01']); // outside month
        Consultation::factory()->create(['patient_id' => $p1->id, 'visit_date' => '2026-09-15']);
        Appointment::factory()->status('no_show')->create(['appointment_date' => '2026-09-30']);

        $r = app(ReportService::class)->monthlyReport(2026, 9);

        $this->assertSame(4, $r['totalVisits']);
        $this->assertSame(2, $r['totalVisitPatients']);
        $this->assertSame(1, $r['totalConsultations']);
        $this->assertSame(3, $r['visitsByDay'][30]);
        $this->assertSame('Headache', $r['topReasons']->first()->reason);
        $this->assertSame(4, $r['topReasons']->first()->total);
        $this->assertSame($p1->id, $r['topPatients']->first()->patient->id);
        $this->assertSame(3, $r['byCategory']->firstWhere('category', 'college')->total);
        $this->assertSame(1, $r['appointmentStatus']->firstWhere('status', 'no_show')->total);
        $this->assertArrayHasKey('lowStock', $r['stockAlerts']);
    }

    public function test_annual_report_includes_visits(): void
    {
        PatientLog::factory()->create(['log_date' => '2026-12-31']);
        PatientLog::factory()->create(['log_date' => '2026-01-01']);

        $r = app(ReportService::class)->annualReport(2026);

        $this->assertSame(2, $r['totalVisits']);
        $this->assertSame(1, $r['monthlyData'][11]['visits']);
    }

    public function test_report_pages_render(): void
    {
        PatientLog::factory()->create(['log_date' => today()->toDateString()]);

        foreach (['reports.daily', 'reports.monthly', 'reports.annual', 'reports.medicine-usage', 'reports.inventory', 'reports.appointments'] as $name) {
            $this->actingAs($this->admin)->get(route($name))->assertOk();
        }

        $this->actingAs($this->admin)->get(route('reports.monthly', ['month' => 13]))->assertSessionHasErrors('month');
    }

    public function test_exports_are_audited_and_include_visits(): void
    {
        $patient = Patient::factory()->create(['last_name' => 'Csvperson']);
        PatientLog::factory()->create(['patient_id' => $patient->id, 'log_date' => '2026-09-10']);

        $response = $this->actingAs($this->admin)
            ->get(route('reports.export', ['type' => 'daily', 'date' => '2026-09-10', 'format' => 'csv']));

        $response->assertOk();
        $this->assertStringContainsString('Csvperson', $response->streamedContent());

        $this->assertDatabaseHas('audit_logs', ['action' => 'exported', 'module' => 'reports']);

        $this->actingAs($this->admin)
            ->get(route('reports.export', ['type' => 'monthly', 'year' => 2026, 'month' => 9, 'format' => 'pdf']))
            ->assertOk();
        $this->assertSame(2, AuditLog::where('action', 'exported')->count());
    }
}
