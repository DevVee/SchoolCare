<?php

namespace Tests\Feature\Admin;

use App\Support\PermissionCatalog;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_catalogue_permissions_and_default_roles(): void
    {
        $this->seed(RolePermissionSeeder::class);

        foreach (PermissionCatalog::all() as $name) {
            $this->assertDatabaseHas('permissions', ['name' => $name, 'guard_name' => 'web']);
        }

        foreach (['administrator', 'nurse', 'staff', 'viewer'] as $role) {
            $this->assertNotNull(Role::where('name', $role)->first(), "Role {$role} missing");
        }

        $this->assertSame(Permission::count(), Role::findByName('administrator')->permissions()->count());
        $this->assertEqualsCanonicalizing(
            PermissionCatalog::defaultsFor('staff'),
            Role::findByName('staff')->permissions->pluck('name')->all()
        );
    }

    public function test_default_role_sets_only_reference_catalogue_permissions(): void
    {
        $all = PermissionCatalog::all();

        foreach (array_keys(config('permissions.default_roles')) as $role) {
            $this->assertSame([], array_values(array_diff(PermissionCatalog::defaultsFor($role), $all)), "Unknown permission in {$role} defaults");
        }
    }

    public function test_rerunning_seeder_keeps_custom_edits(): void
    {
        $this->seed(RolePermissionSeeder::class);

        // Admin customises built-in and custom roles.
        $staff = Role::findByName('staff');
        $staff->revokePermissionTo('create-appointments');
        $staff->givePermissionTo('view-medicines');

        $custom = Role::create(['name' => 'pharmacist', 'guard_name' => 'web']);
        $custom->givePermissionTo(['view-medicines', 'dispose-medicines']);

        Role::findByName('viewer')->syncPermissions([]);

        // A permission added outside the catalogue (e.g. by a later migration).
        Permission::create(['name' => 'extra-permission', 'guard_name' => 'web']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $staff = Role::findByName('staff')->load('permissions');
        $this->assertFalse($staff->permissions->contains('name', 'create-appointments'));
        $this->assertTrue($staff->permissions->contains('name', 'view-medicines'));

        $this->assertEqualsCanonicalizing(
            ['view-medicines', 'dispose-medicines'],
            Role::findByName('pharmacist')->permissions->pluck('name')->all()
        );

        $this->assertCount(0, Role::findByName('viewer')->permissions);

        // Administrator is always topped up with everything.
        $this->assertTrue(Role::findByName('administrator')->hasPermissionTo('extra-permission'));
        $this->assertSame(Permission::count(), Role::findByName('administrator')->permissions()->count());
    }

    public function test_granular_permission_migration_maps_legacy_permissions(): void
    {
        // Simulate a pre-upgrade database: only legacy permissions exist.
        $legacy = [
            'delete-patients', 'create-patients', 'view-consultations', 'create-consultations',
            'update-consultations', 'approve-appointments', 'update-medicines', 'manage-users',
            'manage-settings', 'export-reports', 'view-patients',
        ];

        Permission::query()->delete();
        Role::query()->delete();
        foreach ($legacy as $name) {
            Permission::create(['name' => $name, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = Role::create(['name' => 'administrator', 'guard_name' => 'web']);
        $nurse = Role::create(['name' => 'nurse', 'guard_name' => 'web']);
        $nurse->givePermissionTo(['view-consultations', 'create-consultations', 'update-consultations', 'approve-appointments', 'create-patients', 'update-medicines']);
        $viewer = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
        $viewer->givePermissionTo(['view-patients', 'view-consultations']);

        $migration = require database_path('migrations/2026_09_27_000001_add_granular_permissions.php');
        $migration->up();
        $migration->up(); // idempotent

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $nursePerms = $nurse->fresh()->permissions->pluck('name')->all();
        foreach (['view-patient-logs', 'create-patient-logs', 'update-patient-logs', 'complete-appointments', 'cancel-appointments', 'import-patients', 'dispose-medicines'] as $p) {
            $this->assertContains($p, $nursePerms);
        }
        foreach (['delete-patient-logs', 'restore-patients', 'view-users', 'manage-landing', 'manage-appointment-slots', 'export-patients'] as $p) {
            $this->assertNotContains($p, $nursePerms);
        }

        $viewerPerms = $viewer->fresh()->permissions->pluck('name')->all();
        $this->assertContains('view-patient-logs', $viewerPerms);
        $this->assertNotContains('create-patient-logs', $viewerPerms);

        $this->assertSame(Permission::count(), $admin->fresh()->permissions()->count());
        $this->assertTrue($admin->fresh()->hasPermissionTo('manage-appointment-slots'));
    }
}
