<?php

namespace Tests\Feature\Clinical;

use App\Models\Consultation;
use App\Models\DispensingRecord;
use App\Models\Patient;
use App\Models\PatientLog;

class UserDeletionKeepsClinicalHistoryTest extends ClinicalTestCase
{
    public function test_deleting_a_nurse_keeps_their_consultations_logs_and_dispensing(): void
    {
        $nurse   = $this->userWithRole('nurse');
        $patient = Patient::factory()->create();

        $consultation = Consultation::factory()->create(['patient_id' => $patient->id, 'nurse_id' => $nurse->id]);
        $log          = PatientLog::factory()->create(['patient_id' => $patient->id, 'logged_by' => $nurse->id]);
        $dispensed    = DispensingRecord::factory()->create(['patient_id' => $patient->id, 'dispensed_by' => $nurse->id]);

        $nurse->delete();

        $this->assertDatabaseHas('consultations', ['id' => $consultation->id, 'nurse_id' => null, 'deleted_at' => null]);
        $this->assertDatabaseHas('patient_logs', ['id' => $log->id, 'logged_by' => null]);
        $this->assertDatabaseHas('dispensing_records', ['id' => $dispensed->id, 'dispensed_by' => null]);

        $this->actingAs($this->admin)
            ->get(route('consultations.show', $consultation))
            ->assertOk()
            ->assertSee('Deleted user');

        $this->actingAs($this->admin)
            ->get(route('consultations.index'))
            ->assertOk()
            ->assertSee('Deleted user');
    }

    public function test_patient_with_clinical_records_cannot_be_hard_deleted(): void
    {
        $patient = Patient::factory()->create();
        Consultation::factory()->create(['patient_id' => $patient->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $patient->forceDelete();
    }
}
