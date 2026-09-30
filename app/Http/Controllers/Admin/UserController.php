<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Route group requires view-users; every mutating action additionally
 * requires manage-users. Only super-admins may create, modify or remove
 * super-admin accounts (prevents privilege escalation via manage-users).
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view-users');

        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status');

        $users = User::with('roles')
            // Grouped so the role / status filters below still apply.
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($request->filled('role'), fn ($q) =>
                $q->whereHas('roles', fn ($r) => $r->where('name', $request->input('role')))
            )
            ->when(in_array($status, ['0', '1'], true), fn ($q) =>
                $q->where('is_active', $status === '1')
            )
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $roles = Role::orderBy('name')->get();

        // Summary numbers for the stat cards (all accounts, not the filtered list).
        $stats = [
            'total'      => User::count(),
            'active'     => User::where('is_active', true)->count(),
            'inactive'   => User::where('is_active', false)->count(),
            'admins'     => User::whereHas('roles', fn ($r) => $r->where('name', PermissionCatalog::superAdminRole()))->count(),
            'new_month'  => User::where('created_at', '>=', now()->startOfMonth())->count(),
        ];

        return view('admin.users.index', compact('users', 'roles', 'stats'));
    }

    public function create()
    {
        $this->authorize('manage-users');

        $roles = $this->assignableRoles();

        return view('admin.users.create', compact('roles'));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->ensureCanTouchAdmin(null, $data['role']);

        $user = User::create([
            'name'                 => $data['name'],
            'email'                => $data['email'],
            'password'             => Hash::make($data['password']),
            'is_active'            => $request->boolean('is_active'),
            'must_change_password' => $request->boolean('must_change_password'),
        ]);
        $user->assignRole($data['role']);

        AuditLogService::log(
            'created',
            'users',
            "Created user: {$user->name} ({$user->email}) with role {$data['role']}",
            null,
            ['name' => $user->name, 'email' => $user->email, 'role' => $data['role'], 'is_active' => $user->is_active]
        );

        $emailed = \App\Notifications\WelcomeUserNotification::sendTo($user);

        return redirect()->route('admin.users.index')
            ->with('success', "User {$user->name} created successfully."
                .($emailed ? " A welcome email with a set-password link was sent to {$user->email}." : ''));
    }

    public function show(User $user)
    {
        $this->authorize('view-users');

        $user->load('roles');

        $recentLogs = AuditLog::where('user_id', $user->id)
            ->latest()->limit(10)->get();

        $lastLogin = AuditLog::where('user_id', $user->id)
            ->where('action', 'logged_in')
            ->latest()->first();

        $clinicalRecords = $user->clinicalRecordCount();
        $isSuperAdmin    = $user->isAdmin();

        return view('admin.users.show', compact('user', 'recentLogs', 'lastLogin', 'clinicalRecords', 'isSuperAdmin'));
    }

    public function edit(User $user)
    {
        $this->authorize('manage-users');
        $this->ensureCanTouchAdmin($user);

        $roles = $this->assignableRoles();

        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data     = $request->validated();
        $isSelf   = $user->is(auth()->user());
        $newRole  = $data['role'];
        $active   = $request->boolean('is_active');
        $oldRole  = $user->roles->first()?->name;

        $this->ensureCanTouchAdmin($user, $newRole);

        if ($isSelf && $newRole !== $oldRole) {
            return back()->withInput()->withErrors(['role' => 'You cannot change your own role.']);
        }
        if ($isSelf && ! $active) {
            return back()->withInput()->withErrors(['is_active' => 'You cannot deactivate your own account.']);
        }

        // Never remove the last active administrator (role change or deactivation).
        if ($user->isLastActiveAdmin()) {
            if (! PermissionCatalog::isSuperAdminRole($newRole)) {
                return back()->withInput()->withErrors(['role' => 'Cannot change the role of the last active administrator.']);
            }
            if (! $active) {
                return back()->withInput()->withErrors(['is_active' => 'Cannot deactivate the last active administrator.']);
            }
        }

        $old = $user->only('name', 'email', 'is_active') + ['role' => $oldRole];
        $wasActive = $user->is_active;

        $user->fill([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'is_active' => $active,
        ]);

        $passwordChanged = ! empty($data['password']);
        if ($passwordChanged) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();
        $user->syncRoles([$newRole]);

        // Kick existing sessions when access was revoked or credentials changed.
        if (! $isSelf && (($wasActive && ! $active) || $passwordChanged)) {
            $this->terminateSessions($user);
        }

        AuditLogService::log(
            'updated',
            'users',
            "Updated user: {$user->name}" . ($oldRole !== $newRole ? " (role {$oldRole} → {$newRole})" : '')
                . ($passwordChanged ? ' (password changed)' : ''),
            $old,
            $user->only('name', 'email', 'is_active') + ['role' => $newRole]
        );

        return redirect()->route('admin.users.index')
            ->with('success', "User {$user->name} updated successfully.");
    }

    /**
     * Activate / deactivate an account. Deactivation signs the user out
     * everywhere (their database sessions are removed).
     */
    public function toggleActive(User $user): RedirectResponse
    {
        $this->authorize('manage-users');
        $this->ensureCanTouchAdmin($user);

        if ($user->is(auth()->user())) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        if ($user->is_active && $user->isLastActiveAdmin()) {
            return back()->with('error', 'Cannot deactivate the last active administrator.');
        }

        $user->forceFill(['is_active' => ! $user->is_active])->save();

        if (! $user->is_active) {
            $this->terminateSessions($user);
        }

        $verb = $user->is_active ? 'Activated' : 'Deactivated';

        AuditLogService::log(
            'updated',
            'users',
            "{$verb} user: {$user->name} ({$user->email})",
            ['is_active' => ! $user->is_active],
            ['is_active' => $user->is_active]
        );

        return back()->with('success', "{$verb} {$user->name}.");
    }

    /**
     * Generate a one-time temporary password. It is shown once (flash) and
     * the user must change it at next sign-in. Existing sessions are ended.
     */
    public function resetPassword(User $user): RedirectResponse
    {
        $this->authorize('manage-users');
        $this->ensureCanTouchAdmin($user);

        if ($user->is(auth()->user())) {
            return back()->with('error', 'Use your profile page to change your own password.');
        }

        $temporary = $this->temporaryPassword();

        $user->forceFill([
            'password'             => Hash::make($temporary),
            'must_change_password' => true,
            'remember_token'       => Str::random(60),
        ])->save();

        $this->terminateSessions($user);

        AuditLogService::log(
            'updated',
            'users',
            "Reset password for user: {$user->name} ({$user->email})"
        );

        return redirect()->route('admin.users.show', $user)
            ->with('temp_password', $temporary)
            ->with('success', "Temporary password generated for {$user->name}. It will not be shown again.");
    }

    /**
     * Guards: not yourself, not the last active administrator, and not an
     * account that owns clinical records (deactivate those instead).
     */
    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('manage-users');
        $this->ensureCanTouchAdmin($user);

        if ($user->is(auth()->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->isLastActiveAdmin()) {
            return back()->with('error',
                'Cannot delete the last administrator account. Promote another user to administrator first.'
            );
        }

        $records = $user->clinicalRecordCount();
        if ($records > 0) {
            return back()->with('error',
                "{$user->name} is linked to {$records} clinical/inventory record(s) and cannot be deleted. Deactivate the account instead."
            );
        }

        $name  = $user->name;
        $email = $user->email;

        $this->terminateSessions($user);
        $user->delete();

        AuditLogService::log('deleted', 'users', "Deleted user: {$name} ({$email})");

        return redirect()->route('admin.users.index')
            ->with('success', "User {$name} deleted.");
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Only super-admins may create, edit or remove super-admin accounts, or
     * hand out the super-admin role.
     */
    private function ensureCanTouchAdmin(?User $target, ?string $requestedRole = null): void
    {
        $actor = auth()->user();
        if ($actor->isAdmin()) {
            return;
        }

        $touchesAdmin = ($target && $target->isAdmin())
            || ($requestedRole && PermissionCatalog::isSuperAdminRole($requestedRole));

        abort_if($touchesAdmin, 403, 'Only administrators can manage administrator accounts.');
    }

    private function assignableRoles()
    {
        $roles = Role::orderBy('name')->get();

        return auth()->user()->isAdmin()
            ? $roles
            : $roles->reject(fn ($r) => PermissionCatalog::isSuperAdminRole($r->name))->values();
    }

    /** Sign the user out everywhere. */
    private function terminateSessions(User $user): void
    {
        $table = config('session.table', 'sessions');

        if (config('session.driver') === 'database' && Schema::hasTable($table)) {
            DB::connection(config('session.connection'))
                ->table($table)
                ->where('user_id', $user->getKey())
                ->delete();
        }

        // Invalidate "remember me" cookies as well.
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }

    /** Readable temporary password that satisfies the password policy. */
    private function temporaryPassword(): string
    {
        $pick = function (string $set, int $n): string {
            $out = '';
            for ($i = 0; $i < $n; $i++) {
                $out .= $set[random_int(0, strlen($set) - 1)];
            }
            return $out;
        };

        // e.g. "Kmrtpa-4821-Zqwh!" (ambiguous characters removed)
        return $pick('ABCDEFGHJKLMNPQRSTUVWXYZ', 1)
            . $pick('abcdefghijkmnpqrstuvwxyz', 5) . '-'
            . $pick('23456789', 4) . '-'
            . $pick('ABCDEFGHJKLMNPQRSTUVWXYZ', 1)
            . $pick('abcdefghijkmnpqrstuvwxyz', 3) . '!';
    }
}
