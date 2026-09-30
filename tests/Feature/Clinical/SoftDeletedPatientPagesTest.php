<?php

namespace Tests\Feature\Clinical;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\DispensingRecord;
use App\Models\Patient;
use App\Models\PatientLog;

class SoftDeletedPatientPagesTest extends ClinicalTestCase
{
    public function test_pages_render_for_records_of_an_archived_patient(): void
    {
        $patient = Patient::factory()->create(['first_name' => 'Archie', 'last_name' => 'Vedpatient']);

        $appointment  = Appointment::factory()->create(['patient_id' => $patient->id, 'appointment_date' => today()->toDateString()]);
        $consultation = Consultation::factory()->create(['patient_id' => $patient->id, 'nurse_id' => $this->admin->id]);
        $log          = PatientLog::factory()->create(['patient_id' => $patient->id, 'logged_by' => $this->admin->id]);
        $dispensing   = DispensingRecord::factory()->create(['patient_id' => $patient->id, 'dispensed_by' => $this->admin->id]);

        $patient->delete(); // soft delete (archive)

        $pages = [
            route('appointments.index'),
            route('appointments.show', $appointment),
            route('appointments.edit', $appointment),
            route('consultations.index'),
            route('consultations.show', $consultation),
            route('consultations.edit', $consultation),
            route('patient-logs.index'),
            route('patient-logs.show', $log),
            route('patient-logs.edit', $log),
            route('dispensing.index'),
            route('dispensing.show', $dispensing),
            route('dashboard'),
            route('reports.daily'),
            route('reports.monthly'),
            route('reports.annual'),
            route('reports.appointments', ['from' => today()->toDateString(), 'to' => today()->toDateString()]),
            route('patients.show', $patient->id),
            route('patients.index', ['is_active' => 'archived']),
        ];

        foreach ($pages as $url) {
            $response = $this->actingAs($this->admin)->get($url);
            $this->assertSame(200, $response->status(), "GET {$url} returned {$response->status()}");
        }

        $this->actingAs($this->admin)
            ->get(route('appointments.show', $appointment))
            ->assertSee('Archived');

        $this->actingAs($this->admin)
            ->get(route('patients.index', ['is_active' => 'archived']))
            ->assertSee('Vedpatient');
    }

    public function test_archived_patient_cannot_receive_new_records(): void
    {
        $patient = Patient::factory()->create();
        $patient->delete();

        $this->actingAs($this->admin)
            ->post(route('appointments.store'), [
                'patient_id'       => $patient->id,
                'appointment_date' => today()->addDays(2)->toDateString(),
                'appointment_time' => '09:00:00',
                'purpose'          => 'Check',
            ])
            ->assertSessionHasErrors('patient_id');
    }

    public function test_archived_patient_can_be_restored(): void
    {
        $patient = Patient::factory()->create();
        $patient->delete();

        $this->actingAs($this->admin)
            ->patch(route('patients.restore', $patient->id))
            ->assertRedirect();

        $this->assertFalse($patient->fresh()->trashed());
    }

    public function test_unchecked_active_switch_deactivates_patient(): void
    {
        $patient = Patient::factory()->create(['is_active' => true]);

        $payload = [
            'category'   => $patient->category,
            'first_name' => $patient->first_name,
            'last_name'  => $patient->last_name,
            'sex'        => $patient->sex,
            'birthdate'  => $patient->birthdate->toDateString(),
            'is_active'  => '0', // hidden input sent when the switch is unchecked
        ];

        $this->actingAs($this->admin)
            ->put(route('patients.update', $patient), $payload)
            ->assertSessionHasNoErrors();

        $this->assertFalse($patient->fresh()->is_active);
    }
}
