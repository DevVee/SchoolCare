<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\InviteUserNotification;
use App\Services\AuditLogService;
use App\Services\SignInCodes;
use App\Support\PermissionCatalog;
use App\Support\SessionPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
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
            'name'      => $data['name'],
            'email'     => $data['email'],
            // Unusable until the invitation is accepted and a password chosen.
            'password'  => Hash::make(Str::random(64)),
            'is_active' => $request->boolean('is_active'),
        ]);
        $user->assignRole($data['role']);

        AuditLogService::log(
            'created',
            'users',
            "Invited user: {$user->name} ({$user->email}) with role {$data['role']}",
            null,
            ['name' => $user->name, 'email' => $user->email, 'role' => $data['role'], 'is_active' => $user->is_active]
        );

        if (! $this->sendInvitation($user)) {
            return redirect()->route('admin.users.index')
                ->with('error', "User {$user->name} was created, but the invitation email could not be sent. Use Resend invitation to try again.");
        }

        return redirect()->route('admin.users.index')
            ->with('success', "Invitation sent to {$user->email}. {$user->name} can sign in after choosing a password.");
    }

    /**
     * Send a fresh invitation (the previous link stops working). Only for
     * accounts that have never signed in; others use Reset password.
     */
    public function resendInvitation(User $user): RedirectResponse
    {
        $this->authorize('manage-users');
        $this->ensureCanTouchAdmin($user);

        if ($user->last_login_at !== null) {
            return back()->with('error', "{$user->name} has already signed in. Use Reset password instead.");
        }

        if (! $this->sendInvitation($user)) {
            return back()->with('error', "The invitation email to {$user->email} could not be sent. Please try again later.");
        }

        AuditLogService::log('updated', 'users', "Resent invitation to: {$user->name} ({$user->email})");

        return back()->with('success', "Invitation sent again to {$user->email}.");
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

        // Browsers remembered for the email sign-in code (Settings > Security).
        $devices = $user->trustedDevices()->active()->latest('last_used_at')->get(['id', 'last_used_at', 'expires_at']);

        // Devices signed in right now (database sessions).
        $signedIn = SessionPolicy::sessionsOf($user)->count();

        return view('admin.users.show', compact('user', 'recentLogs', 'lastLogin', 'clinicalRecords', 'isSuperAdmin', 'devices', 'signedIn'));
    }

    /** Sign the user out on every device, including ones kept signed in. */
    public function signOut(User $user): RedirectResponse
    {
        $this->authorize('manage-users');
        $this->ensureCanTouchAdmin($user);
        abort_if($user->id === auth()->id(), 403, 'Use Profile > Sign out of other devices for your own account.');

        $ended = SessionPolicy::endAllSessions($user);

        AuditLogService::log('updated', 'users', "Signed out everywhere: {$user->name} ({$user->email}), {$ended} ".Str::plural('session', $ended));

        return back()->with('success', "{$user->name} is signed out on every device.");
    }

    /**
     * Forget every browser this user chose to remember for the email sign-in
     * code; they are asked for a code on their next sign-in everywhere.
     */
    public function forgetDevices(User $user, SignInCodes $codes): RedirectResponse
    {
        $this->authorize('manage-users');
        $this->ensureCanTouchAdmin($user);

        $count = $codes->forgetDevices($user);

        AuditLogService::log('updated', 'users', "Forgot remembered devices for: {$user->name} ({$user->email}), {$count} removed");

        return back()->with('success', "{$user->name} will be asked for a sign-in code on every device next time.");
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
     * Email the user a password-reset link (same email as "Forgot password").
     * Their current password keeps working until they choose a new one.
     */
    public function resetPassword(User $user): RedirectResponse
    {
        $this->authorize('manage-users');
        $this->ensureCanTouchAdmin($user);

        if ($user->is(auth()->user())) {
            return back()->with('error', 'Use your profile page to change your own password.');
        }

        try {
            $status = Password::broker()->sendResetLink(['email' => $user->email]);
        } catch (\Throwable $e) {
            Log::warning('Password reset email could not be sent', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return back()->with('error', "The reset email to {$user->email} could not be sent. Please try again later.");
        }

        if ($status === Password::RESET_THROTTLED) {
            return back()->with('error', 'A reset link was sent moments ago. Wait a minute before sending another.');
        }
        if ($status !== Password::RESET_LINK_SENT) {
            return back()->with('error', __($status));
        }

        AuditLogService::log(
            'updated',
            'users',
            "Sent password reset link to: {$user->name} ({$user->email})"
        );

        return back()->with('success', "Password reset link sent to {$user->email}.");
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

    /** Sign the user out everywhere, "remember me" cookies included. */
    private function terminateSessions(User $user): void
    {
        SessionPolicy::endAllSessions($user);
    }

    /** Email an invitation; false (and a log entry) when it could not be sent. */
    private function sendInvitation(User $user): bool
    {
        try {
            InviteUserNotification::sendTo($user);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Invitation email could not be sent', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
