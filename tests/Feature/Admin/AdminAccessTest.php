<?php

namespace Tests\Feature\Admin;

use App\Models\Appointment;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function userWithPermissions(array $permissions, string $roleName = 'custom'): User
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_role_without_admin_permissions_gets_403_on_every_admin_route(): void
    {
        $viewer = $this->userWithRole('viewer');
        $target = $this->userWithRole('staff');
        $role   = Role::findByName('staff');

        $requests = [
            ['get',    route('admin.users.index')],
            ['get',    route('admin.users.create')],
            ['get',    route('admin.users.show', $target)],
            ['get',    route('admin.users.edit', $target)],
            ['post',   route('admin.users.store')],
            ['put',    route('admin.users.update', $target)],
            ['delete', route('admin.users.destroy', $target)],
            ['patch',  route('admin.users.toggle-active', $target)],
            ['post',   route('admin.users.reset-password', $target)],
            ['post',   route('admin.users.resend-invitation', $target)],
            ['get',    route('admin.roles.index')],
            ['get',    route('admin.roles.create')],
            ['post',   route('admin.roles.store')],
            ['get',    route('admin.roles.edit', $role)],
            ['put',    route('admin.roles.update', $role)],
            ['delete', route('admin.roles.destroy', $role)],
            ['get',    '/admin/settings'],
            ['get',    route('admin.audit-logs.index')],
            ['get',    route('admin.audit-logs.export')],
        ];

        foreach ($requests as [$method, $url]) {
            $this->actingAs($viewer)->{$method}($url)
                ->assertForbidden();
        }

        $this->assertTrue($target->fresh()->is_active);
        $this->assertNotNull(Role::findByName('staff'));
    }

    public function test_view_users_permission_allows_listing_but_not_mutating(): void
    {
        $user   = $this->userWithPermissions(['view-users']);
        $target = $this->userWithRole('staff');

        $this->actingAs($user)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.users.show', $target))->assertOk();

        $this->actingAs($user)->get(route('admin.users.create'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.users.edit', $target))->assertForbidden();
        $this->actingAs($user)->patch(route('admin.users.toggle-active', $target))->assertForbidden();
        $this->actingAs($user)->post(route('admin.users.reset-password', $target))->assertForbidden();
        $this->actingAs($user)->delete(route('admin.users.destroy', $target))->assertForbidden();

        // No access to other admin areas.
        $this->actingAs($user)->get(route('admin.roles.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.audit-logs.index'))->assertForbidden();
    }

    public function test_custom_role_with_manage_settings_reaches_settings_only(): void
    {
        $user = $this->userWithPermissions(['manage-settings']);

        $status = $this->actingAs($user)->get('/admin/settings')->getStatusCode();
        $this->assertLessThan(400, $status, 'manage-settings should reach the settings area');

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.roles.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.audit-logs.index'))->assertForbidden();
    }

    public function test_view_audit_logs_permission_reaches_logs_and_export(): void
    {
        $user = $this->userWithPermissions(['view-audit-logs']);

        $this->actingAs($user)->get(route('admin.audit-logs.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.audit-logs.index', ['user_id' => $user->id]))->assertOk();

        $response = $this->actingAs($user)->get(route('admin.audit-logs.export'));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_administrator_without_any_permission_rows_is_not_locked_out(): void
    {
        $admin = $this->userWithRole('administrator');
        Role::findByName('administrator')->syncPermissions([]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($admin)->get(route('admin.roles.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.audit-logs.index'))->assertOk();
    }

    public function test_gate_bypass_does_not_bypass_policy_state_checks(): void
    {
        $admin = $this->userWithRole('administrator');

        $pending   = new Appointment(['status' => 'pending']);
        $completed = new Appointment(['status' => 'completed']);

        // Plain permission checks always pass for the super-admin …
        $this->assertTrue($admin->can('update-appointments'));
        $this->assertTrue($admin->can('some-permission-that-does-not-exist'));

        // … but model policies with state rules still apply.
        $this->assertTrue($admin->can('update', $pending));
        $this->assertFalse($admin->can('update', $completed));
        $this->assertFalse($admin->can('approve', $completed));
        $this->assertFalse($admin->can('complete', $pending));
    }

    public function test_complete_and_no_show_use_complete_appointments_permission(): void
    {
        $user     = $this->userWithPermissions(['view-appointments', 'approve-appointments']);
        $approved = new Appointment(['status' => 'approved']);

        $this->assertFalse($user->can('complete', $approved));
        $this->assertFalse($user->can('markNoShow', $approved));

        $user->roles->first()->givePermissionTo('complete-appointments');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        $this->assertTrue($user->can('complete', $approved));
        $this->assertTrue($user->can('markNoShow', $approved));
    }

    public function test_administrator_role_cannot_be_stripped_or_deleted(): void
    {
        $admin     = $this->userWithRole('administrator');
        $adminRole = Role::findByName('administrator');

        $this->actingAs($admin)
            ->put(route('admin.roles.update', $adminRole), ['permissions' => ['view-patients']])
            ->assertRedirect(route('admin.roles.index'));

        $this->assertSame(Permission::count(), $adminRole->fresh()->permissions()->count());

        $this->actingAs($admin)->delete(route('admin.roles.destroy', $adminRole))->assertRedirect();
        $this->assertNotNull(Role::where('name', 'administrator')->first());
    }

    public function test_system_roles_and_roles_with_users_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole('administrator');

        $this->actingAs($admin)->delete(route('admin.roles.destroy', Role::findByName('viewer')));
        $this->assertNotNull(Role::where('name', 'viewer')->first());

        $custom = Role::create(['name' => 'pharmacist', 'guard_name' => 'web']);
        User::factory()->create()->assignRole($custom);

        $this->actingAs($admin)->delete(route('admin.roles.destroy', $custom))->assertSessionHas('error');
        $this->assertNotNull(Role::where('name', 'pharmacist')->first());

        $empty = Role::create(['name' => 'temp-role', 'guard_name' => 'web']);
        $this->actingAs($admin)->delete(route('admin.roles.destroy', $empty))->assertSessionHas('success');
        $this->assertNull(Role::where('name', 'temp-role')->first());
    }

    public function test_role_update_rejects_unknown_permissions_and_is_audited(): void
    {
        $admin = $this->userWithRole('administrator');
        $staff = Role::findByName('staff');

        $this->actingAs($admin)
            ->put(route('admin.roles.update', $staff), ['permissions' => ['not-a-permission']])
            ->assertSessionHasErrors('permissions.0');

        $this->actingAs($admin)
            ->put(route('admin.roles.update', $staff), ['permissions' => ['view-patients', 'view-patient-logs']])
            ->assertRedirect(route('admin.roles.index'));

        $this->assertEqualsCanonicalizing(
            ['view-patients', 'view-patient-logs'],
            $staff->fresh()->permissions->pluck('name')->all()
        );
        $this->assertDatabaseHas('audit_logs', ['module' => 'roles', 'action' => 'updated']);
    }

    public function test_role_create_and_edit_pages_render_matrix(): void
    {
        $admin = $this->userWithRole('administrator');

        $this->actingAs($admin)->get(route('admin.roles.create'))
            ->assertOk()->assertSee('permissionMatrix', false)->assertSee('restore-patients', false);

        $this->actingAs($admin)->get(route('admin.roles.edit', Role::findByName('nurse')))
            ->assertOk()->assertSee('view-patient-logs', false);

        $this->actingAs($admin)->get(route('admin.roles.edit', Role::findByName('administrator')))
            ->assertOk()->assertSee('All permissions');
    }

    public function test_manage_users_without_admin_role_cannot_touch_administrators(): void
    {
        $manager = $this->userWithPermissions(['view-users', 'manage-users']);
        $admin   = $this->userWithRole('administrator');

        $this->actingAs($manager)->get(route('admin.users.edit', $admin))->assertForbidden();
        $this->actingAs($manager)->patch(route('admin.users.toggle-active', $admin))->assertForbidden();
        $this->actingAs($manager)->post(route('admin.users.reset-password', $admin))->assertForbidden();
        $this->actingAs($manager)->post(route('admin.users.resend-invitation', $admin))->assertForbidden();

        $this->actingAs($manager)->post(route('admin.users.store'), [
            'name'      => 'Sneaky',
            'email'     => 'sneaky@example.com',
            'role'      => 'administrator',
            'is_active' => '1',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_sidebar_shows_only_permitted_admin_links(): void
    {
        $user = $this->userWithPermissions(['view-audit-logs']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('admin.audit-logs.index'), false)
            ->assertDontSee(route('admin.users.index'), false)
            ->assertDontSee(route('admin.roles.index'), false);
    }
}
