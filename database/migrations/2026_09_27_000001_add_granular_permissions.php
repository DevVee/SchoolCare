<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Introduces granular permissions and carries existing access forward.
 *
 * Idempotent: permissions are firstOrCreate'd and grants only ever add.
 * Each new permission is granted to every role that already holds one of
 * its "legacy equivalent" permissions, so no user loses (or silently gains)
 * access on upgrade. The administrator role receives every permission.
 */
return new class extends Migration
{
    /** new permission => legacy permissions that imply it */
    private array $map = [
        'restore-patients'         => ['delete-patients'],
        'import-patients'          => ['create-patients'],
        'export-patients'          => ['export-reports'],
        'view-patient-logs'        => ['view-consultations'],
        'create-patient-logs'      => ['create-consultations'],
        'update-patient-logs'      => ['update-consultations'],
        'delete-patient-logs'      => ['delete-consultations'],
        'complete-appointments'    => ['approve-appointments'],
        'cancel-appointments'      => ['approve-appointments'],
        'manage-appointment-slots' => [], // administrator only
        'dispose-medicines'        => ['update-medicines'],
        'view-users'               => ['manage-users'],
        'manage-landing'           => ['manage-settings'],
    ];

    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $guard = 'web';

        foreach (array_keys($this->map) as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
        }

        // Anything else the catalogue defines (keeps fresh installs complete).
        foreach ((array) config('permissions.modules', []) as $module) {
            $names = array_merge(array_values($module['actions'] ?? []), array_keys($module['other'] ?? []));
            foreach ($names as $name) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
            }
        }

        $registrar->forgetCachedPermissions();

        $superAdmin = config('clinovia.super_admin_role', 'administrator');

        foreach (Role::where('guard_name', $guard)->with('permissions')->get() as $role) {
            if ($role->name === $superAdmin) {
                $role->givePermissionTo(Permission::where('guard_name', $guard)->get());
                continue;
            }

            $held  = $role->permissions->pluck('name')->all();
            $grant = [];

            foreach ($this->map as $new => $legacy) {
                if (! in_array($new, $held, true) && array_intersect($legacy, $held)) {
                    $grant[] = $new;
                }
            }

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
