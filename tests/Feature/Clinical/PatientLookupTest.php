<?php

namespace Tests\Feature\Clinical;

use App\Models\Patient;
use App\Models\PatientLog;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Patient picker search (GET patients/lookup) and the searchable patient field
 * on the Log a visit form.
 */
class PatientLookupTest extends ClinicalTestCase
{
    private function userWithPermissions(array $permissions): User
    {
        $role = Role::create(['name' => 'custom-'.uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function lookup(?User $user, string $q)
    {
        $request = $user ? $this->actingAs($user) : $this;

        return $request->getJson(route('patients.lookup', ['q' => $q]));
    }

    public function test_guests_cannot_search(): void
    {
        $this->lookup(null, 'juan')->assertUnauthorized();
    }

    public function test_users_without_patient_or_logbook_access_are_forbidden(): void
    {
        $user = $this->userWithPermissions(['view-medicines']);

        $this->lookup($user, 'juan')->assertForbidden();
    }

    public function test_logbook_staff_without_patient_list_access_can_search(): void
    {
        Patient::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);

        $this->lookup($this->userWithPermissions(['create-patient-logs']), 'juan')
            ->assertOk()
            ->assertJsonPath('results.0.label', 'Juan Dela Cruz');

        $this->lookup($this->userWithPermissions(['view-patients']), 'juan')
            ->assertOk()
            ->assertJsonCount(1, 'results');
    }

    public function test_results_show_school_placement_or_category(): void
    {
        $college = Patient::factory()->create([
            'first_name' => 'Maria', 'middle_name' => 'Santos', 'last_name' => 'Reyes', 'category' => 'college',
            'program_strand' => 'BSN', 'year_level' => '2nd Year', 'section' => 'A',
        ]);
        Patient::factory()->create([
            'first_name' => 'Marco', 'last_name' => 'Reyes', 'category' => 'junior_high',
            'year_level' => 'Grade 7', 'section' => 'Rizal',
        ]);
        Patient::factory()->create([
            'first_name' => 'Mario', 'last_name' => 'Reyes', 'category' => 'teacher',
        ]);

        $response = $this->lookup($this->admin, 'reyes')->assertOk()->assertJsonCount(3, 'results');

        $byName = collect($response->json('results'))->keyBy('label');
        $this->assertSame('BSN, 2nd Year, A', $byName['Maria S. Reyes']['detail']);
        $this->assertSame('Grade 7, Rizal', $byName['Marco Reyes']['detail']);
        $this->assertSame('Teacher', $byName['Mario Reyes']['detail']);
        $this->assertSame($college->patient_number, $byName['Maria S. Reyes']['meta']);
        $this->assertSame($college->id, $byName['Maria S. Reyes']['id']);
        $this->assertFalse($response->json('more'));
    }

    public function test_every_word_matches_a_name_part_in_any_order_and_case(): void
    {
        $juan = Patient::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);
        Patient::factory()->create(['first_name' => 'Juana', 'last_name' => 'Santos']);

        $this->lookup($this->admin, 'CRUZ juan')
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.id', $juan->id);

        $this->lookup($this->admin, 'Dela Cruz, Juan')
            ->assertJsonCount(1, 'results');
    }

    public function test_matches_patient_number_and_school_id(): void
    {
        $patient = Patient::factory()->create(['first_name' => 'Ana', 'last_name' => 'Lim', 'student_id' => 'S-2024-0417']);

        $this->lookup($this->admin, '2024-0417')->assertJsonPath('results.0.id', $patient->id);
        $this->lookup($this->admin, $patient->patient_number)->assertJsonPath('results.0.id', $patient->id);
    }

    public function test_inactive_and_archived_patients_are_left_out(): void
    {
        Patient::factory()->create(['first_name' => 'Pedro', 'last_name' => 'Active']);
        Patient::factory()->inactive()->create(['first_name' => 'Pedro', 'last_name' => 'Inactive']);
        Patient::factory()->create(['first_name' => 'Pedro', 'last_name' => 'Archived'])->delete();

        $this->lookup($this->admin, 'pedro')
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.label', 'Pedro Active');
    }

    public function test_short_queries_return_nothing_and_results_are_capped(): void
    {
        Patient::factory()->count(25)->create(['last_name' => 'Garcia']);

        $this->lookup($this->admin, 'g')->assertOk()->assertJsonCount(0, 'results');

        $this->lookup($this->admin, 'garcia')
            ->assertOk()
            ->assertJsonCount(20, 'results')
            ->assertJsonPath('more', true);
    }

    public function test_log_form_prefills_the_patient_from_the_link_and_from_edit(): void
    {
        $patient = Patient::factory()->create([
            'first_name' => 'Liza', 'last_name' => 'Soberano', 'category' => 'senior_high',
            'program_strand' => 'STEM', 'year_level' => 'Grade 11', 'section' => 'Einstein',
        ]);

        $this->actingAs($this->admin)
            ->get(route('patient-logs.create', ['patient_id' => $patient->id]))
            ->assertOk()
            ->assertSee('role="combobox"', false)
            ->assertSee('name="patient_id" value="'.$patient->id.'"', false)
            ->assertSee('Liza Soberano')
            ->assertSee('STEM, Grade 11, Einstein');

        // An archived patient stays selected on their existing visit.
        $log = PatientLog::factory()->create(['patient_id' => $patient->id, 'logged_by' => $this->admin->id]);
        $patient->delete();

        $this->actingAs($this->admin)
            ->get(route('patient-logs.edit', $log))
            ->assertOk()
            ->assertSee('name="patient_id" value="'.$patient->id.'"', false)
            ->assertSee($patient->patient_number.', archived');

        // ...but is not pre-selected on a new visit.
        $this->actingAs($this->admin)
            ->get(route('patient-logs.create', ['patient_id' => $patient->id]))
            ->assertOk()
            ->assertSee('name="patient_id" value=""', false);
    }

    public function test_log_form_keeps_the_patient_after_a_validation_error(): void
    {
        $patient = Patient::factory()->create(['first_name' => 'Carlo', 'last_name' => 'Aquino']);

        $this->actingAs($this->admin)
            ->from(route('patient-logs.create'))
            ->post(route('patient-logs.store'), ['patient_id' => $patient->id])
            ->assertRedirect(route('patient-logs.create'))
            ->assertSessionHasErrors('log_date');

        $this->actingAs($this->admin)
            ->get(route('patient-logs.create'))
            ->assertSee('name="patient_id" value="'.$patient->id.'"', false)
            ->assertSee('Carlo Aquino');
    }
}
