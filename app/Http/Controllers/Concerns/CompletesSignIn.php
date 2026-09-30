<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Services\AuditLogService;
use App\Services\SignInCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The last step of every sign-in, with or without an email code: new session
 * id, last sign-in time and IP, the audit entry, then the page the person
 * wanted. A forced password change (must_change_password) is handled after
 * this by the EnsurePasswordChanged middleware.
 */
trait CompletesSignIn
{
    protected function completeSignIn(Request $request, User $user): RedirectResponse
    {
        // A code page left open for another account ("Use a different account") is dropped.
        app(SignInCodes::class)->clear($request);

        $request->session()->regenerate();

        // Update last login timestamp + IP (shown on the admin user page)
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        // Audit: record successful login with IP for forensic trail
        AuditLogService::log(
            action: 'logged_in',
            module: 'auth',
            description: "User '{$user->name}' logged in from {$request->ip()}",
        );

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
