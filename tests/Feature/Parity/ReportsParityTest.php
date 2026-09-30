<?php

namespace Tests\Feature\Parity;

use App\Models\DispensingRecord;
use App\Models\Medicine;
use App\Models\PatientLog;
use App\Services\ReportService;
use Tests\Feature\Clinical\ClinicalTestCase;

class ReportsParityTest extends ClinicalTestCase
{
    public function test_monthly_report_uses_structured_reasons_severity_and_visit_medicines(): void
    {
        $date = '2026-09-10';
        PatientLog::factory()->create(['log_date' => $date, 'reasons' => ['Fever', 'Headache'], 'severity' => 'Mild', 'chief_complaint' => null]);
        PatientLog::factory()->create(['log_date' => $date, 'reasons' => ['Fever'], 'other_reason' => 'Rash', 'severity' => 'Severe']);
        $legacy = PatientLog::factory()->create(['log_date' => $date, 'chief_complaint' => ' fever ', 'severity' => null]);

        $medicine = Medicine::factory()->create();
        DispensingRecord::factory()->create([
            'medicine_id' => $medicine->id, 'patient_id' => $legacy->patient_id, 'patient_log_id' => $legacy->id,
            'quantity' => 4, 'dispensed_at' => $date.' 10:00:00',
        ]);

        $r = app(ReportService::class)->monthlyReport(2026, 9);

        $this->assertSame('Fever', $r['topReasons']->first()->reason);
        $this->assertSame(3, $r['topReasons']->first()->total); // two structured + one legacy free text
        $this->assertSame(1, $r['topReasons']->firstWhere('reason', 'Rash')->total);

        $this->assertSame(1, $r['bySeverity']->firstWhere('severity', 'Mild')->total);
        $this->assertSame(1, $r['bySeverity']->firstWhere('severity', 'Severe')->total);
        $this->assertSame(0, $r['bySeverity']->firstWhere('severity', 'Moderate')->total);
        $this->assertSame(1, $r['bySeverity']->firstWhere('label', 'Not recorded')->total);

        $this->assertSame(['units' => 4, 'visits' => 1], $r['visitMedicines']);
        $this->assertSame($medicine->id, $r['topMedicines']->first()->medicine_id);

        $this->actingAs($this->admin)->get(route('reports.monthly', ['year' => 2026, 'month' => 9]))
            ->assertOk()->assertSee('Visits by severity');
    }
}
