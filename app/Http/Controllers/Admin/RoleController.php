<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role & permission management, driven by config/permissions.php.
 *
 * - The super-admin role always holds every permission; it cannot be
 *   edited, stripped or deleted (prevents administrator lock-out).
 * - System roles (config('schoolcare.system_roles')) cannot be deleted.
 * - Roles that still have users cannot be deleted.
 */
class RoleController extends Controller
{
    public function index()
    {
        $this->authorize('manage-roles');

        $roles = Role::with('permissions')
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return view('admin.roles.index', [
            'roles'          => $roles,
            'superAdminRole' => PermissionCatalog::superAdminRole(),
            'systemRoles'    => config('schoolcare.system_roles', []),
        ]);
    }

    public function create()
    {
        $this->authorize('manage-roles');

        return view('admin.roles.create', $this->matrixData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-roles');

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:50', 'unique:roles,name', 'regex:/^[a-z0-9_\-]+$/'],
            'permissions'   => ['array'],
            'permissions.*' => ['string', Rule::in($this->knownPermissions())],
            'icon'          => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9\-]+$/'],
        ]);

        $role = Role::create([
            'name'       => $data['name'],
            'guard_name' => config('permissions.guard', 'web'),
            'icon'       => $data['icon'] ?? null,
        ]);

        $perms = array_values(array_unique($data['permissions'] ?? []));
        $role->syncPermissions($perms);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        AuditLogService::log(
            'created',
            'roles',
            "Created role: {$role->name} with " . count($perms) . ' permission(s)',
            null,
            ['name' => $role->name, 'permissions' => $perms]
        );

        return redirect()->route('admin.roles.index')
            ->with('success', "Role '{$role->name}' created successfully.");
    }

    public function edit(Role $role)
    {
        $this->authorize('manage-roles');

        return view('admin.roles.edit', $this->matrixData() + [
            'role'         => $role->load('permissions'),
            'isSuperAdmin' => PermissionCatalog::isSuperAdminRole($role->name),
            'isSystem'     => PermissionCatalog::isSystemRole($role->name),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('manage-roles');

        $data = $request->validate([
            'permissions'   => ['array'],
            'permissions.*' => ['string', Rule::in($this->knownPermissions())],
            'icon'          => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9\-]+$/'],
        ]);

        // Super-admin: permissions are fixed (always all). Only the icon may change.
        if (PermissionCatalog::isSuperAdminRole($role->name)) {
            $role->givePermissionTo(Permission::where('guard_name', $role->guard_name)->get());
            if (array_key_exists('icon', $data) && $data['icon']) {
                $role->icon = $data['icon'];
                $role->save();
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return redirect()->route('admin.roles.index')
                ->with('success', "The '{$role->name}' role always has every permission; only its icon was updated.");
        }

        $before = $role->permissions->pluck('name')->sort()->values()->all();
        $after  = collect($data['permissions'] ?? [])->unique()->sort()->values()->all();

        $role->syncPermissions($after);
        if (! empty($data['icon'])) {
            $role->icon = $data['icon'];
        }
        $role->save();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $added   = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));

        AuditLogService::log(
            'updated',
            'roles',
            "Updated role '{$role->name}' permissions (+" . count($added) . ' / -' . count($removed) . ')',
            ['permissions' => $before, 'removed' => $removed],
            ['permissions' => $after, 'added' => $added]
        );

        return redirect()->route('admin.roles.index')
            ->with('success', "Role '{$role->name}' permissions updated.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('manage-roles');

        if (PermissionCatalog::isSystemRole($role->name)) {
            return back()->with('error', 'System roles cannot be deleted.');
        }

        $userCount = $role->users()->count();
        if ($userCount > 0) {
            return back()->with('error',
                "Role '{$role->name}' is assigned to {$userCount} user(s). Reassign them to another role before deleting it."
            );
        }

        $name  = $role->name;
        $perms = $role->permissions->pluck('name')->all();
        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        AuditLogService::log('deleted', 'roles', "Deleted role: {$name}", ['name' => $name, 'permissions' => $perms]);

        return redirect()->route('admin.roles.index')
            ->with('success', "Role '{$name}' deleted.");
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Permission names that exist both in the catalogue and in the database. */
    private function knownPermissions(): array
    {
        return Permission::whereIn('name', PermissionCatalog::all())->pluck('name')->all();
    }

    private function matrixData(): array
    {
        return [
            'modules' => PermissionCatalog::modules(),
            'columns' => PermissionCatalog::columns(),
            // Only render checkboxes for permissions that actually exist.
            'existingPermissions' => Permission::pluck('name')->all(),
        ];
    }
}
