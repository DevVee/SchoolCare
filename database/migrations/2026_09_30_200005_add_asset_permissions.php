<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Asset inventory permissions.
 *
 * Idempotent (firstOrCreate, grants only add): administrator gets both,
 * nurse gets view-assets. Other roles are left for admins to decide.
 */
return new class extends Migration
{
    public function up(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $guard = 'web';

        foreach (['view-assets', 'manage-assets'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
        }

        $registrar->forgetCachedPermissions();

        $superAdmin = config('clinovia.super_admin_role', 'administrator');

        if ($admin = Role::where('guard_name', $guard)->where('name', $superAdmin)->first()) {
            $admin->givePermissionTo(['view-assets', 'manage-assets']);
        }

        if ($nurse = Role::where('guard_name', $guard)->where('name', 'nurse')->first()) {
            if (! $nurse->hasPermissionTo('view-assets')) {
                $nurse->givePermissionTo('view-assets');
            }
        }

        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Irreversible by design: removing permissions could lock users out.
    }
};
