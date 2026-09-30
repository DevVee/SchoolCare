<?php

namespace Tests\Feature\Parity;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\PatientIntakeSubmission;

class PublicHealthFormTest extends ParityTestCase
{
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'category'       => 'junior_high',
            'first_name'     => 'Paolo',
            'last_name'      => 'Villanueva',
            'sex'            => 'male',
            'birthdate'      => '2012-05-10',
            'student_id'     => 'JH-500',
            'year_level'     => 'Grade 8',
            'section'        => 'Section A',
            'contact_number' => '0917 123 4567',
            'guardian_name'  => 'Rhea Villanueva',
            'guardian_contact' => '+639181234567',
            'allergies'      => 'Peanuts',
            'current_medications' => 'Inhaler as needed',
            'consent'        => '1',
        ];
    }

    public function test_form_is_404_when_disabled(): void
    {
        $this->get(route('public.health-form.create'))->assertNotFound();
        $this->post(route('public.health-form.store'), $this->payload())->assertNotFound();
        $this->assertSame(0, PatientIntakeSubmission::count());
    }

    public function test_submission_is_stored_pending_without_creating_a_patient(): void
    {
        settings()->set('public_intake_enabled', true);

        $this->get(route('public.health-form.create'))->assertOk()->assertSee('Student Health Information Form');

        $this->post(route('public.health-form.store'), $this->payload())
            ->assertRedirect(route('public.health-form.thanks'));

        $s = PatientIntakeSubmission::sole();
        $this->assertSame('pending', $s->status);
        $this->assertSame('09171234567', $s->payload['contact_number'], 'Mobile numbers are normalised');
        $this->assertSame('09181234567', $s->payload['guardian_contact']);
        $this->assertNotNull($s->consent_at);
        $this->assertSame(0, Patient::count());

        $this->get(route('public.health-form.thanks'))->assertOk()->assertSee('Form received');
    }

    public function test_consent_honeypot_and_validation(): void
    {
        settings()->set('public_intake_enabled', true);

        $this->post(route('public.health-form.store'), $this->payload(['consent' => null]))->assertSessionHasErrors('consent');
        $this->post(route('public.health-form.store'), $this->payload(['year_level' => '3rd Year']))->assertSessionHasErrors('year_level');
        $this->post(route('public.health-form.store'), $this->payload(['contact_number' => '555']))->assertSessionHasErrors('contact_number');
        $this->post(route('public.health-form.store'), $this->payload(['website' => 'spam']))->assertRedirect(route('public.health-form.thanks'));

        $this->assertSame(0, PatientIntakeSubmission::count());
    }

    public function test_approve_creates_a_patient(): void
    {
        settings()->set('public_intake_enabled', true);
        $this->post(route('public.health-form.store'), $this->payload());
        $s = PatientIntakeSubmission::sole();

        $this->actingAs($this->admin)->get(route('patients.intake.index'))->assertOk()->assertSee('Paolo');
        $this->actingAs($this->admin)->get(route('patients.intake.show', $s))->assertOk()->assertSee('Villanueva');

        $this->actingAs($this->admin)->post(route('patients.intake.approve', $s))->assertRedirect();

        $patient = Patient::sole();
        $this->assertSame('JH-500', $patient->student_id);
        $this->assertSame('Inhaler as needed', $patient->current_medications);
        $this->assertSame('approved', $s->fresh()->status);
        $this->assertSame($patient->id, $s->fresh()->patient_id);
        $this->assertTrue(AuditLog::where('module', 'patient-intake')->where('action', 'approved')->exists());

        // Cannot be approved twice.
        $this->actingAs($this->admin)->post(route('patients.intake.approve', $s))->assertStatus(409);
        $this->assertSame(1, Patient::count());
    }

    public function test_merge_updates_only_the_chosen_fields_of_the_match(): void
    {
        settings()->set('public_intake_enabled', true);
        $existing = Patient::factory()->create([
            'category' => 'junior_high', 'first_name' => 'Paolo', 'last_name' => 'Villanueva',
            'student_id' => 'JH-500', 'allergies' => null, 'contact_number' => '09990000000',
        ]);
        $this->post(route('public.health-form.store'), $this->payload());
        $s = PatientIntakeSubmission::sole();

        $page = $this->actingAs($this->admin)->get(route('patients.intake.show', $s))->assertOk();
        $this->assertTrue($page->viewData('matches')->contains('id', $existing->id));

        $this->actingAs($this->admin)->post(route('patients.intake.merge', $s), [
            'patient_id' => $existing->id,
            'take'       => ['allergies', 'current_medications'],
        ])->assertRedirect(route('patients.show', $existing));

        $existing->refresh();
        $this->assertSame('Peanuts', $existing->allergies);
        $this->assertSame('Inhaler as needed', $existing->current_medications);
        $this->assertSame('09990000000', $existing->contact_number, 'Unticked fields keep the existing value');
        $this->assertSame(1, Patient::count());
        $this->assertSame('approved', $s->fresh()->status);
    }

    public function test_reject_and_queue_authorization(): void
    {
        settings()->set('public_intake_enabled', true);
        $this->post(route('public.health-form.store'), $this->payload());
        $s = PatientIntakeSubmission::sole();

        $viewer = $this->userWithRole('viewer');
        $this->actingAs($viewer)->get(route('patients.intake.index'))->assertForbidden();
        $this->actingAs($viewer)->post(route('patients.intake.approve', $s))->assertForbidden();

        $nurse = $this->userWithRole('nurse');
        $this->actingAs($nurse)->get(route('patients.intake.index'))->assertOk();

        $this->actingAs($nurse)->post(route('patients.intake.reject', $s), [])->assertSessionHasErrors('review_note');
        $this->actingAs($nurse)->post(route('patients.intake.reject', $s), ['review_note' => 'Test entry'])
            ->assertRedirect(route('patients.intake.index'));

        $this->assertSame('rejected', $s->fresh()->status);
        $this->assertSame(0, Patient::count());
    }
}
