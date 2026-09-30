<?php

namespace Tests\Feature\Clinical;

use App\Models\AuditLog;
use App\Models\Consultation;
use App\Models\DispensingRecord;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\PatientLog;
use App\Models\User;
use App\Services\Patients\HealthReportService;

/**
 * The printed health record (patients/pdf/health-report via PatientHealthReportController::pdf):
 * a plain official document, one A4 page for a typical patient and never more than two,
 * named after the school and clinic, never the product.
 */
class HealthRecordPdfTest extends ClinicalTestCase
{
    use InspectsPdf;

    private User $nurse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nurse = User::factory()->create(['name' => 'Maria Santos']);
        settings()->setMany([
            'app_name'    => 'SchoolCare',
            'org_name'    => 'Mabini High School',
            'clinic_name' => 'Mabini Health Office',
        ]);
    }

    public function test_a_typical_record_is_one_page_named_after_the_school_and_clinic(): void
    {
        $patient = $this->student(['allergies' => 'Penicillin']);
        $this->visits($patient, 4);

        $response = $this->actingAs($this->admin)->get(route('patients.health-report.pdf', $patient))->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('attachment; filename=health-record-', $response->headers->get('Content-Disposition'));

        $pdf = $response->getContent();
        $text = $this->pdfText($pdf);
        $this->assertSame(1, $this->pdfPageCount($pdf));
        foreach (['Mabini High School', 'Mabini Health Office', 'STUDENT HEALTH RECORD', 'CONFIDENTIAL', 'I. IDENTIFICATION',
            'II. MEDICAL PROFILE', 'III. CLINIC VISIT HISTORY', 'IV. CERTIFICATION', 'Penicillin', 'Visit 001', 'Visit 004',
            'Record No. '.$patient->patient_number, 'Signature over printed name'] as $expected) {
            $this->assertStringContainsString($expected, $text);
        }
        $this->assertStringContainsString('This is a true copy of the health record kept by', $text);
        $this->assertStringNotContainsString('SchoolCare', $text);
        $this->assertStringNotContainsString('earlier visit', $text);
        $this->assertStringNotContainsString('Page 1 of 1', $text); // page numbers are off by default for health records
    }

    public function test_a_long_history_stays_within_two_pages_and_counts_the_earlier_visits(): void
    {
        settings()->setMany(['print_page_numbers_health' => true]);
        $patient = $this->student();
        $this->visits($patient, 60);

        $pdf = $this->actingAs($this->admin)->get(route('patients.health-report.pdf', $patient))->assertOk()->getContent();
        $text = $this->pdfText($pdf);

        $this->assertSame(2, $this->pdfPageCount($pdf));
        $this->assertMatchesRegularExpression('/^and (\d+) earlier visits on file\.$/m', $text);
        preg_match('/^and (\d+) earlier visits on file\.$/m', $text, $m);
        $shown = 60 - (int) $m[1];

        // The newest visits are listed, the older ones counted; both pages carry the table header.
        $this->assertGreaterThan(12, $shown);
        $this->assertMatchesRegularExpression('/^Visit 001$/m', $text);
        $this->assertMatchesRegularExpression('/^Visit '.sprintf('%03d', $shown).'$/m', $text);
        $this->assertDoesNotMatchRegularExpression('/^Visit '.sprintf('%03d', $shown + 1).'$/m', $text);
        $this->assertSame(2, substr_count($text, 'III. CLINIC VISIT HISTORY'));

        // Page numbers (Settings > Printing) and the certification on the last page.
        $this->assertStringContainsString('Page 1 of 2', $text);
        $this->assertStringContainsString('Page 2 of 2', $text);
        $this->assertStringContainsString('IV. CERTIFICATION', $text);
    }

    public function test_the_record_is_a_plain_form_with_blank_boxes_for_missing_details(): void
    {
        $student = $this->student(['allergies' => 'Penicillin (hives)', 'middle_name' => null, 'email' => null]);
        $html = $this->recordHtml($student);

        $this->assertStringContainsString('Student Health Record', $html);
        $this->assertStringContainsString('Student ID', $html);
        $this->assertStringContainsString('Course, program or strand', $html);
        $this->assertStringContainsString('<div class="val strong">Penicillin (hives)</div>', $html); // allergies stand out
        $this->assertStringContainsString('<div class="lbl">Middle name</div><div class="val">&nbsp;</div>', $html);
        $this->assertStringContainsString('No clinic visits on record.', $html);
        foreach (['N/A', 'Not recorded', 'Not set', 'Unknown', '<canvas', 'badge', 'background'] as $clutter) {
            $this->assertStringNotContainsString($clutter, $html);
        }

        $teacher = Patient::factory()->create([
            'category' => 'teacher', 'student_id' => 'EMP-01', 'allergies' => null,
            'year_level' => null, 'section' => null, 'program_strand' => null,
        ]);
        $html = $this->recordHtml($teacher);
        $this->assertStringContainsString('Individual Health Record', $html);
        $this->assertStringContainsString('ID No.', $html);
        $this->assertStringNotContainsString('Course, program or strand', $html);
        $this->assertStringContainsString('<div class="val none">None recorded</div>', $html);
    }

    public function test_history_lists_visits_consultations_and_medicines_newest_first(): void
    {
        $patient = $this->student();
        $medicine = Medicine::factory()->create(['name' => 'Paracetamol 500 mg', 'unit' => 'tablet']);
        $log = PatientLog::factory()->create([
            'patient_id' => $patient->id, 'logged_by' => $this->nurse->id, 'log_date' => now()->subDays(3)->toDateString(),
            'chief_complaint' => 'Fever', 'treatment' => 'Rest in clinic', 'disposition' => 'rest_in_clinic',
            'vital_signs' => ['temperature' => 38.2, 'blood_pressure' => '110/70'],
        ]);
        DispensingRecord::factory()->create(['patient_id' => $patient->id, 'patient_log_id' => $log->id, 'medicine_id' => $medicine->id, 'quantity' => 2, 'dispensed_by' => $this->nurse->id]);
        Consultation::factory()->create([
            'patient_id' => $patient->id, 'nurse_id' => $this->nurse->id, 'visit_date' => now()->subDay()->toDateString(),
            'chief_complaint' => 'Recurring headaches', 'diagnosis' => 'Migraine',
        ]);

        $html = $this->recordHtml($patient);

        $this->assertStringContainsString('Consultation', $html);
        $this->assertStringContainsString('Diagnosis: Migraine.', $html);
        $this->assertStringContainsString('Rest in clinic. Given Paracetamol 500 mg 2 tablets.', $html); // "Rest in Clinic" outcome not repeated
        $this->assertStringNotContainsString('Rest in Clinic.', $html);
        $this->assertLessThan(strpos($html, 'Fever'), strpos($html, 'Recurring headaches')); // newest first
        $this->assertStringContainsString('38.2 °C', $html); // latest vital signs
        $this->assertStringContainsString('110/70 mmHg', $html);
    }

    public function test_print_opens_the_same_pdf_in_the_browser(): void
    {
        $patient = $this->student();

        $this->actingAs($this->admin)->get(route('patients.health-report', $patient))->assertOk()
            ->assertSee(route('patients.health-report.pdf', ['patient' => $patient->id, 'inline' => 1]), false)
            ->assertSee('target="_blank"', false)
            ->assertDontSee('window.print()', false);

        $response = $this->get(route('patients.health-report.pdf', ['patient' => $patient->id, 'inline' => 1]))->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline; filename=health-record-', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Opened the health record PDF to print', AuditLog::latest('id')->first()->description);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function student(array $overrides = []): Patient
    {
        return Patient::factory()->create(array_merge([
            'category' => 'junior_high', 'student_id' => '2026-0045', 'year_level' => 'Grade 8', 'section' => 'Rizal',
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'blood_type' => 'O+',
        ], $overrides));
    }

    /** $count clinic visits, "Visit 001" the newest, each about two lines long in the history table. */
    private function visits(Patient $patient, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            PatientLog::factory()->create([
                'patient_id'      => $patient->id,
                'logged_by'       => $this->nurse->id,
                'log_date'        => now()->subDays($i * 3)->toDateString(),
                'chief_complaint' => sprintf('Visit %03d', $i),
                'assessment'      => 'Tension headache, no fever; advised rest and fluids.',
                'treatment'       => 'Observed in the clinic',
                'disposition'     => 'returned_to_class',
            ]);
        }
    }

    private function recordHtml(Patient $patient): string
    {
        $this->actingAs($this->admin);

        return view('patients.pdf.health-report', app(HealthReportService::class)->build($patient))->render();
    }
}
