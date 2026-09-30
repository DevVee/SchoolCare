<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * review-intake: review public health information form submissions.
 * Idempotent; grants only ever add. Administrator gets everything, nurse gets
 * review-intake, and any custom role that can already create patients gets it
 * too (approving a submission creates or updates a patient).
 */
return new class extends Migration
{
    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $guard = 'web';
        Permission::firstOrCreate(['name' => 'review-intake', 'guard_name' => $guard]);
        $registrar->forgetCachedPermissions();

        $superAdmin = config('clinovia.super_admin_role', 'administrator');

        foreach (Role::where('guard_name', $guard)->with('permissions')->get() as $role) {
            if ($role->name === $superAdmin) {
                $role->givePermissionTo(Permission::where('guard_name', $guard)->get());
                continue;
            }

            $held = $role->permissions->pluck('name')->all();
            if (in_array('review-intake', $held, true)) {
                continue;
            }

            if ($role->name === 'nurse' || in_array('create-patients', $held, true)) {
                $role->givePermissionTo('review-intake');
            }
        }

        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Irreversible by design: removing permissions could lock users out.
    }
};
