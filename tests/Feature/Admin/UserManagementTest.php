<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = $this->userWithRole('administrator');
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function insertSession(User $user): void
    {
        DB::table('sessions')->insert([
            'id'            => 'sess-' . $user->id,
            'user_id'       => $user->id,
            'ip_address'    => '127.0.0.1',
            'user_agent'    => 'phpunit',
            'payload'       => base64_encode('x'),
            'last_activity' => time(),
        ]);
    }

    public function test_toggle_active_deactivates_and_ends_sessions(): void
    {
        config(['session.driver' => 'database']);
        $nurse = $this->userWithRole('nurse');
        $this->insertSession($nurse);

        $this->actingAs($this->admin)
            ->patch(route('admin.users.toggle-active', $nurse))
            ->assertSessionHas('success');

        $this->assertFalse($nurse->fresh()->is_active);
        $this->assertDatabaseMissing('sessions', ['user_id' => $nurse->id]);
        $this->assertDatabaseHas('audit_logs', ['module' => 'users', 'action' => 'updated']);

        // And back on again.
        $this->actingAs($this->admin)->patch(route('admin.users.toggle-active', $nurse));
        $this->assertTrue($nurse->fresh()->is_active);
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $nurse = $this->userWithRole('nurse', ['is_active' => false]);

        $this->post('/login', ['email' => $nurse->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_cannot_deactivate_or_delete_self(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('admin.users.toggle-active', $this->admin))
            ->assertSessionHas('error');
        $this->assertTrue($this->admin->fresh()->is_active);

        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $this->admin))
            ->assertSessionHas('error');
        $this->assertNotNull($this->admin->fresh());
    }

    public function test_last_active_administrator_guards(): void
    {
        $this->assertTrue($this->admin->isLastActiveAdmin());

        // Cannot demote or deactivate yourself via the edit form.
        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $this->admin), [
                'name' => $this->admin->name, 'email' => $this->admin->email,
                'role' => 'nurse', 'is_active' => '1',
            ])->assertSessionHasErrors('role');
        $this->assertTrue($this->admin->fresh()->hasRole('administrator'));

        // Profile self-deletion is blocked for the last administrator.
        $this->actingAs($this->admin)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeletion', 'password');
        $this->assertNotNull($this->admin->fresh());

        // With a second active admin, the first is no longer the last one.
        $second = $this->userWithRole('administrator');
        $this->assertFalse($this->admin->fresh()->isLastActiveAdmin());

        // Deactivating the second admin works; it then counts as inactive.
        $this->actingAs($this->admin)->patch(route('admin.users.toggle-active', $second));
        $this->assertFalse($second->fresh()->is_active);
        $this->assertTrue($this->admin->fresh()->isLastActiveAdmin());
    }

    public function test_user_owning_clinical_records_cannot_be_deleted(): void
    {
        $nurse = $this->userWithRole('nurse');

        $categoryId = DB::table('medicine_categories')->insertGetId([
            'name' => 'Analgesics', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('medicines')->insert([
            'name' => 'Paracetamol', 'category_id' => $categoryId, 'unit' => 'tablet',
            'created_by' => $nurse->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $nurse))
            ->assertSessionHas('error');
        $this->assertNotNull($nurse->fresh());

        $staff = $this->userWithRole('staff');
        $this->actingAs($this->admin)
            ->delete(route('admin.users.destroy', $staff))
            ->assertRedirect(route('admin.users.index'));
        $this->assertNull($staff->fresh());
    }

    public function test_reset_password_forces_password_change(): void
    {
        config(['session.driver' => 'database']);
        $nurse = $this->userWithRole('nurse');
        $this->insertSession($nurse);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.users.reset-password', $nurse))
            ->assertRedirect(route('admin.users.show', $nurse))
            ->assertSessionHas('temp_password');

        $temporary = $response->getSession()->get('temp_password');
        $nurse->refresh();

        $this->assertTrue($nurse->must_change_password);
        $this->assertTrue(Hash::check($temporary, $nurse->password));
        $this->assertDatabaseMissing('sessions', ['user_id' => $nurse->id]);

        config(['session.driver' => 'array']);

        // Every page except the profile redirects until the password is changed.
        $this->actingAs($nurse)->get(route('dashboard'))->assertRedirect(route('profile.edit'));
        $this->actingAs($nurse)->get(route('patients.index'))->assertRedirect(route('profile.edit'));
        $this->actingAs($nurse)->get(route('profile.edit'))->assertOk()->assertSee('Password change required');

        $this->actingAs($nurse)->put(route('profile.password'), [
            'current_password'      => $temporary,
            'password'              => 'N3w!SecurePass',
            'password_confirmation' => 'N3w!SecurePass',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($nurse->fresh()->must_change_password);
        $this->actingAs($nurse->fresh())->get(route('dashboard'))->assertOk();
    }

    public function test_create_user_respects_unchecked_active_switch(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name'                  => 'Inactive Nurse',
            'email'                 => 'inactive@example.com',
            'password'              => 'Str0ng!Passw0rd',
            'password_confirmation' => 'Str0ng!Passw0rd',
            'role'                  => 'nurse',
            'is_active'             => '0',
            'must_change_password'  => '1',
        ])->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'inactive@example.com')->firstOrFail();
        $this->assertFalse($user->is_active);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue($user->hasRole('nurse'));
    }

    public function test_weak_password_rejected_for_admin_created_user(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name'                  => 'Weak',
            'email'                 => 'weak@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
            'role'                  => 'nurse',
        ])->assertSessionHasErrors('password');
    }

    public function test_update_can_deactivate_user_via_unchecked_switch(): void
    {
        $nurse = $this->userWithRole('nurse');

        $this->actingAs($this->admin)->put(route('admin.users.update', $nurse), [
            'name' => $nurse->name, 'email' => $nurse->email, 'role' => 'nurse', 'is_active' => '0',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertFalse($nurse->fresh()->is_active);
    }

    public function test_index_search_is_grouped_with_filters(): void
    {
        $this->userWithRole('nurse', ['name' => 'Alice Active', 'email' => 'alice.a@example.com']);
        $this->userWithRole('viewer', ['name' => 'Alice Inactive', 'email' => 'alice.i@example.com', 'is_active' => false]);

        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['search' => 'Alice', 'status' => '1']))
            ->assertOk()
            ->assertSee('Alice Active')
            ->assertDontSee('Alice Inactive');

        $this->actingAs($this->admin)
            ->get(route('admin.users.index', ['search' => 'alice', 'role' => 'viewer']))
            ->assertOk()
            ->assertSee('Alice Inactive')
            ->assertDontSee('Alice Active');
    }

    public function test_create_and_edit_forms_render(): void
    {
        $nurse = $this->userWithRole('nurse');

        $this->actingAs($this->admin)->get(route('admin.users.create'))
            ->assertOk()->assertSee('name="is_active" value="0"', false);

        $this->actingAs($this->admin)->get(route('admin.users.edit', $nurse))
            ->assertOk()->assertSee('name="is_active" value="0"', false);
    }

    public function test_show_page_lists_activity_and_links_to_audit_log(): void
    {
        $nurse = $this->userWithRole('nurse');

        $this->actingAs($this->admin)
            ->get(route('admin.users.show', $nurse))
            ->assertOk()
            ->assertSee(route('admin.audit-logs.index', ['user_id' => $nurse->id]), false);
    }

    public function test_login_records_last_login_ip(): void
    {
        $nurse = $this->userWithRole('nurse');

        $this->post('/login', ['email' => $nurse->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));

        $nurse->refresh();
        $this->assertNotNull($nurse->last_login_at);
        $this->assertSame('127.0.0.1', $nurse->last_login_ip);
    }
}
