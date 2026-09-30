<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Specialist visit permissions (idempotent, grants only ever add):
 *   administrator  every permission
 *   nurse          view-specialist-visits + manage-specialist-visits
 *   staff, viewer  view-specialist-visits
 * Custom roles that can view appointments also get view-specialist-visits so
 * the appointments calendar keeps showing specialist days for them.
 */
return new class extends Migration
{
    private array $grants = [
        'nurse'  => ['view-specialist-visits', 'manage-specialist-visits'],
        'staff'  => ['view-specialist-visits'],
        'viewer' => ['view-specialist-visits'],
    ];

    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $guard = 'web';

        foreach (['view-specialist-visits', 'manage-specialist-visits'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
        }

        $registrar->forgetCachedPermissions();

        $superAdmin = config('clinovia.super_admin_role', 'administrator');

        foreach (Role::where('guard_name', $guard)->with('permissions')->get() as $role) {
            if ($role->name === $superAdmin) {
                $role->givePermissionTo(Permission::where('guard_name', $guard)->get());
                continue;
            }

            $held  = $role->permissions->pluck('name')->all();
            $grant = $this->grants[$role->name]
                ?? (in_array('view-appointments', $held, true) ? ['view-specialist-visits'] : []);
            $grant = array_values(array_diff($grant, $held));

            if ($grant) {
                $role->givePermissionTo($grant);
            }
        }

        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Irreversible by design: removing permissions could lock users out.
    }
};
