<?php

namespace Tests\Feature\Parity;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ParityPermissionMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function run_migration(string $file): void
    {
        $migration = require database_path('migrations/'.$file);
        $migration->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_specialist_and_intake_grants_are_idempotent_and_additive(): void
    {
        Permission::firstOrCreate(['name' => 'view-appointments', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'create-patients', 'guard_name' => 'web']);

        $admin  = Role::create(['name' => 'administrator', 'guard_name' => 'web']);
        $nurse  = Role::create(['name' => 'nurse', 'guard_name' => 'web']);
        $staff  = Role::create(['name' => 'staff', 'guard_name' => 'web']);
        $custom = Role::create(['name' => 'front-desk', 'guard_name' => 'web']);
        $none   = Role::create(['name' => 'cleaner', 'guard_name' => 'web']);
        $custom->givePermissionTo(['view-appointments', 'create-patients']);

        foreach ([1, 2] as $run) {
            $this->run_migration('2026_10_01_300005_add_specialist_visit_permissions.php');
            $this->run_migration('2026_10_01_300007_add_review_intake_permission.php');
        }

        $perms = fn (Role $r) => $r->fresh()->permissions->pluck('name')->sort()->values()->all();

        $this->assertContains('manage-specialist-visits', $perms($admin));
        $this->assertContains('review-intake', $perms($admin));
        $this->assertSame(['manage-specialist-visits', 'review-intake', 'view-specialist-visits'], $perms($nurse));
        $this->assertSame(['view-specialist-visits'], $perms($staff));
        $this->assertSame(['create-patients', 'review-intake', 'view-appointments', 'view-specialist-visits'], $perms($custom));
        $this->assertSame([], $perms($none));
    }
}
