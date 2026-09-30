<?php

namespace Tests\Feature\Parity;

use App\Models\Appointment;
use App\Models\Consultation;
use App\Models\Patient;
use App\Models\PatientLog;
use App\Models\SpecialistVisit;

class ParityPagesTest extends ParityTestCase
{
    public function test_calendar_pages_render(): void
    {
        $patient = Patient::factory()->create();
        Appointment::factory()->create(['patient_id' => $patient->id, 'appointment_date' => today()->toDateString(), 'status' => 'approved']);
        Appointment::factory()->create(['patient_id' => null, 'source' => 'online', 'requester_name' => 'Online Person', 'appointment_date' => today()->toDateString()]);
        PatientLog::factory()->create(['patient_id' => $patient->id, 'log_date' => today()->toDateString()]);
        SpecialistVisit::create([
            'type' => 'Dentist', 'specialist_name' => 'Dr. Tooth', 'visit_date' => today()->toDateString(),
            'start_time' => '08:00:00', 'end_time' => '12:00:00',
        ]);

        $this->actingAs($this->admin)->get(route('appointments.calendar'))->assertOk()->assertSee('Dentist');
        $this->actingAs($this->admin)->get(route('appointments.calendar', ['month' => '2026-01']))->assertOk()->assertSee('January 2026');
        $this->actingAs($this->admin)->get(route('appointments.calendar', ['month' => 'garbage']))->assertOk();
        $this->actingAs($this->admin)->get(route('appointments.today'))->assertOk()->assertSee('Online Person')->assertSee('Dr. Tooth');
        $this->actingAs($this->admin)->get(route('patient-logs.calendar'))->assertOk();
        $this->actingAs($this->admin)->get(route('patient-logs.calendar', ['category' => 'college']))->assertOk();
        $this->actingAs($this->admin)->get(route('specialist-visits.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('appointments.index', ['source' => 'online']))->assertOk()->assertSee('Online request');
    }

    public function test_forms_render(): void
    {
        $visit = SpecialistVisit::create([
            'type' => 'Doctor', 'specialist_name' => 'Dr. House', 'visit_date' => $this->nextWeekday()->toDateString(),
            'start_time' => '13:00:00', 'end_time' => '15:00:00', 'capacity' => 5,
        ]);
        $slot = \App\Models\AppointmentTimeSlot::first();
        $online = Appointment::factory()->create(['patient_id' => null, 'source' => 'online', 'requester_name' => 'Web User', 'requester_contact' => '09170000000']);
        $staffAppt = Appointment::factory()->create(['specialist_visit_id' => $visit->id]);

        $this->actingAs($this->admin)->get(route('specialist-visits.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('specialist-visits.edit', $visit))->assertOk()->assertSee('Dr. House');
        $this->actingAs($this->admin)->get(route('specialist-visits.show', $visit))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.appointment-slots.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.appointment-slots.edit', $slot))->assertOk();
        $this->actingAs($this->admin)->get(route('appointments.create', ['specialist_visit_id' => $visit->id]))->assertOk()->assertSee('Dr. House');
        $this->actingAs($this->admin)->get(route('appointments.edit', $online))->assertOk()->assertSee('Not linked yet');
        $this->actingAs($this->admin)->get(route('appointments.edit', $staffAppt))->assertOk();
        $this->actingAs($this->admin)->get(route('appointments.show', $staffAppt))->assertOk()->assertSee('Dr. House');
        $this->actingAs($this->admin)->get(route('patients.intake.index'))->assertOk()->assertSee(route('public.health-form.create'));
        $this->actingAs($this->admin)->get(route('patients.promote.form'))->assertOk();

        // An unlinked request can be edited without choosing a patient.
        $this->actingAs($this->admin)->put(route('appointments.update', $online), [
            'patient_id' => '', 'appointment_date' => $online->appointment_date->toDateString(),
            'appointment_time' => '09:00:00', 'purpose' => 'Updated purpose',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Updated purpose', $online->fresh()->purpose);
        $this->assertNull($online->fresh()->patient_id);
    }

    public function test_calendars_respect_permissions(): void
    {
        $noLogs = $this->userWithPermissions(['view-appointments']);
        $this->actingAs($noLogs)->get(route('appointments.calendar'))->assertOk();
        $this->actingAs($noLogs)->get(route('patient-logs.calendar'))->assertForbidden();
    }

    public function test_patient_pages_handle_missing_birthdate_and_new_fields(): void
    {
        $patient = Patient::factory()->create([
            'birthdate' => null, 'student_id' => 'ID-77', 'guardian_facebook' => 'fb.com/guardian',
            'pediatrician_name' => 'Dr. Kid', 'other_contact' => '09998887777', 'category' => 'alumni',
            'year_level' => 'Alumni', 'section' => 'Alumni',
        ]);
        PatientLog::factory()->create([
            'patient_id' => $patient->id,
            'log_date' => today()->subDays(3)->toDateString(),
            'chief_complaint' => 'Headache, Fever',
            'vital_signs' => ['temperature' => 38.2, 'blood_pressure' => '120/80', 'pulse' => 90],
        ]);
        PatientLog::factory()->create([
            'patient_id' => $patient->id,
            'log_date' => today()->subDay()->toDateString(),
            'chief_complaint' => 'Headache',
            'vital_signs' => ['temperature' => 37.1, 'blood_pressure' => '110/70', 'pulse' => 80],
        ]);
        Consultation::factory()->create(['patient_id' => $patient->id]);

        $this->actingAs($this->admin)->get(route('patients.index'))->assertOk()->assertSee('Age not recorded');
        $this->actingAs($this->admin)->get(route('patients.show', $patient))->assertOk()
            ->assertSee('Not recorded')->assertSee('fb.com/guardian')->assertSee('Dr. Kid')
            ->assertSee(route('patients.history', $patient->id))
            ->assertSee(route('patients.health-report', $patient->id));
        $this->actingAs($this->admin)->get(route('patients.edit', $patient))->assertOk()->assertSee('ID-77');
        $this->actingAs($this->admin)->get(route('patients.create'))->assertOk()->assertSee('academicFields', false);

        $report = $this->actingAs($this->admin)->get(route('patients.health-report', $patient))->assertOk()
            ->assertSee('Health Report Card')->assertSee('Print');
        $this->assertSame(2, $report->viewData('summary')['total_visits']);
        $this->assertArrayHasKey('Headache', $report->viewData('reasons'));
        $this->assertSame(2, $report->viewData('reasons')['Headache']);
        $this->assertCount(2, $report->viewData('vitals')['rows']);

        $pdf = $this->actingAs($this->admin)->get(route('patients.health-report.pdf', $patient))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));

        $this->actingAs($this->admin)->get(route('patients.history', $patient))->assertOk()
            ->assertSee('Visit history')->assertSee('Clinic visit')->assertSee('Consultation');

        // Archived patients keep a working report and history.
        $patient->delete();
        $this->actingAs($this->admin)->get(route('patients.health-report', $patient->id))->assertOk();
        $this->actingAs($this->admin)->get(route('patients.history', $patient->id))->assertOk();
    }

    public function test_health_report_requires_view_permission(): void
    {
        $patient = Patient::factory()->create();
        $this->actingAs($this->userWithPermissions(['view-appointments']))
            ->get(route('patients.health-report', $patient))
            ->assertForbidden();
    }

    public function test_patient_form_validates_category_dependent_levels_and_new_fields(): void
    {
        $base = [
            'category' => 'junior_high', 'first_name' => 'Lia', 'last_name' => 'Ramos', 'sex' => 'other',
        ];

        // Birthdate optional, "Other" sex allowed, Alumni available.
        $this->actingAs($this->admin)->post(route('patients.store'), $base + [
            'year_level' => 'Grade 8', 'section' => 'Section B', 'student_id' => 'JHS-1',
            'guardian_facebook' => 'fb.com/lia.mom', 'pediatrician_name' => 'Dr. P', 'pediatrician_contact' => '0917',
            'other_contact' => '0918', 'current_medications' => 'None',
        ])->assertRedirect();
        $lia = Patient::where('student_id', 'JHS-1')->firstOrFail();
        $this->assertNull($lia->birthdate);
        $this->assertSame('fb.com/lia.mom', $lia->guardian_facebook);

        // A college year level is not valid for Junior High.
        $this->actingAs($this->admin)->post(route('patients.store'), $base + ['year_level' => '1st Year', 'student_id' => 'JHS-2'])
            ->assertSessionHasErrors('year_level');

        // Duplicate student ID is refused.
        $this->actingAs($this->admin)->post(route('patients.store'), $base + ['student_id' => 'JHS-1'])
            ->assertSessionHasErrors('student_id');

        // A legacy value already on the record stays valid on edit.
        $legacy = Patient::factory()->create(['category' => 'junior_high', 'year_level' => 'Grade 7 - Rizal']);
        $this->actingAs($this->admin)->put(route('patients.update', $legacy), [
            'category' => 'junior_high', 'first_name' => 'Old', 'last_name' => 'Value', 'sex' => 'male',
            'year_level' => 'Grade 7 - Rizal', 'is_active' => '1',
        ])->assertSessionHasNoErrors();
    }
}
