<?php

namespace App\Support;

use Illuminate\Support\Arr;

/**
 * Read-only accessor for config/permissions.php.
 */
class PermissionCatalog
{
    /** @return array<string, array{label:string, icon:string, actions:array<string,string>, other:array<string,string>}> */
    public static function modules(): array
    {
        return config('permissions.modules', []);
    }

    /** @return array<string, string> column key => heading */
    public static function columns(): array
    {
        return config('permissions.columns', []);
    }

    /** Every permission name defined in the catalogue. */
    public static function all(): array
    {
        $names = [];

        foreach (static::modules() as $module) {
            foreach ($module['actions'] ?? [] as $name) {
                $names[] = $name;
            }
            foreach (array_keys($module['other'] ?? []) as $name) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /** Default permission set for a role name (empty when none defined). */
    public static function defaultsFor(string $role): array
    {
        return Arr::wrap(config("permissions.default_roles.{$role}", []));
    }

    /** Human label for a permission name, e.g. "Patients: Edit". */
    public static function label(string $permission): string
    {
        $columns = static::columns();

        foreach (static::modules() as $module) {
            foreach ($module['actions'] ?? [] as $column => $name) {
                if ($name === $permission) {
                    return $module['label'] . ': ' . ($columns[$column] ?? ucfirst($column));
                }
            }
            if (isset($module['other'][$permission])) {
                return $module['label'] . ': ' . $module['other'][$permission];
            }
        }

        return ucwords(str_replace('-', ' ', $permission));
    }

    public static function superAdminRole(): string
    {
        return config('clinovia.super_admin_role', 'administrator');
    }

    public static function isSuperAdminRole(string $role): bool
    {
        return $role === static::superAdminRole();
    }

    public static function isSystemRole(string $role): bool
    {
        return in_array($role, config('clinovia.system_roles', []), true)
            || static::isSuperAdminRole($role);
    }
}
