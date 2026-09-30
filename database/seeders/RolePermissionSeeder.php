<?php

namespace Database\Seeders;

use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;

/**
 * Non-destructive role/permission bootstrap, driven by config/permissions.php.
 *
 * Safe to run on every production boot:
 *   - creates any permission missing from the database;
 *   - creates missing default roles and applies their default permission set
 *     ONLY when the role is created for the first time (admin edits survive);
 *   - the super-admin role is always topped up with every permission.
 * Nothing is ever revoked or deleted.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $guard = config('permissions.guard', 'web');

        foreach (PermissionCatalog::all() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
        }

        $registrar->forgetCachedPermissions();

        // Super-admin: always holds everything (givePermissionTo only adds).
        $admin = Role::firstOrCreate(['name' => PermissionCatalog::superAdminRole(), 'guard_name' => $guard]);
        $admin->givePermissionTo(Permission::where('guard_name', $guard)->get());

        // Other built-in roles: defaults only on first creation.
        foreach (array_keys(config('permissions.default_roles', [])) as $roleName) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => $guard]);

            if ($role->wasRecentlyCreated) {
                $role->givePermissionTo(PermissionCatalog::defaultsFor($roleName));
            }
        }

        $registrar->forgetCachedPermissions();

        $this->command?->info('Roles and permissions synchronised (non-destructive).');
    }
}
