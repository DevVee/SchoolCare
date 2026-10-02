<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileAvatarRequest;
use App\Http\Requests\ProfileUpdateRequest;
use App\Services\AuditLogService;
use App\Support\SessionPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user'     => $request->user(),
            // Where this account is signed in (database sessions only).
            'sessions' => SessionPolicy::sessionsOf($request->user(), $request),
        ]);
    }

    /**
     * Update the user's profile information (name, email, bio).
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        AuditLogService::log('updated', 'users', 'Updated own profile information');

        return Redirect::route('profile.edit')
            ->with('success', 'Profile information updated successfully.');
    }

    /**
     * Upload / replace the user's avatar.
     */
    public function updateAvatar(ProfileAvatarRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Delete old avatar if it exists
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        // Store the new avatar
        $path = $request->file('avatar')->store('avatars', 'public');

        $user->update(['avatar' => $path]);

        AuditLogService::log('updated', 'users', 'Updated profile picture');

        return Redirect::route('profile.edit')
            ->with('success', 'Profile picture updated!');
    }

    /**
     * Remove the user's avatar (reset to initials).
     */
    public function removeAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update(['avatar' => null]);

        return Redirect::route('profile.edit')
            ->with('success', 'Profile picture removed.');
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->forceFill([
            'password'             => Hash::make($validated['password']),
            'must_change_password' => false,
        ])->save();

        // Anyone signed in elsewhere with the old password is signed out.
        $ended = SessionPolicy::endOtherSessions($request->user(), $request);

        AuditLogService::log('updated', 'users', 'Changed own password'.($ended ? ", signed out on {$ended} other ".Str::plural('device', $ended) : ''));

        return Redirect::route('profile.edit')
            ->with('success', 'Password changed.'.($ended ? ' You were signed out on '.SessionPolicy::count($ended, 'other device').'.' : ''));
    }

    /** Sign out of every other device (Profile > Where you're signed in). */
    public function destroyOtherSessions(Request $request): RedirectResponse
    {
        $request->validateWithBag('otherSessions', [
            'password' => ['required', 'current_password'],
        ]);

        $ended = SessionPolicy::endOtherSessions($request->user(), $request);

        AuditLogService::log('updated', 'users', 'Signed out of other devices ('.$ended.')');

        return Redirect::route('profile.edit')
            ->with('success', $ended
                ? 'Signed out on '.SessionPolicy::count($ended, 'other device').'.'
                : 'You were not signed in anywhere else. Devices kept signed in with "Keep me signed in" were signed out too.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Never allow the last active administrator to remove their own
        // account — that would leave nobody able to manage the system.
        if ($user->isLastActiveAdmin()) {
            return Redirect::route('profile.edit')->withErrors([
                'password' => 'You are the last active administrator. Promote another administrator before deleting your account.',
            ], 'userDeletion');
        }

        // Accounts that own clinical records must be deactivated by an
        // administrator instead (several records cascade or restrict on delete).
        if ($user->clinicalRecordCount() > 0) {
            return Redirect::route('profile.edit')->withErrors([
                'password' => 'Your account is linked to clinical records and cannot be deleted. Ask an administrator to deactivate it instead.',
            ], 'userDeletion');
        }

        AuditLogService::log('deleted', 'users', "User deleted own account: {$user->name} ({$user->email})");

        // Clean up avatar
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
