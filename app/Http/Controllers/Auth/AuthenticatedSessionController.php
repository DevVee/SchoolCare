<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     *
     * MED-10 FIX: Records login event in audit_logs and updates last_login_at.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = auth()->user();

        // Deactivated accounts must not be able to sign in at all.
        if (! $user->is_active) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated. Please contact the administrator.',
            ]);
        }

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

    /**
     * Destroy an authenticated session.
     *
     * MED-10 FIX: Records logout event in audit_logs before session is destroyed.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = auth()->user();

        // Audit logout BEFORE session is destroyed (auth()->user() becomes null after)
        if ($user) {
            AuditLogService::log(
                action: 'logged_out',
                module: 'auth',
                description: "User '{$user->name}' logged out",
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
