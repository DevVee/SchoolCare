<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\CompletesSignIn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditLogService;
use App\Services\SignInCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    use CompletesSignIn;

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
     * With Settings > Security > "Ask for a sign-in code by email" on, a correct
     * password does not sign in yet: a code is emailed and the person is sent
     * to the code page (SignInCodeController), unless this browser is remembered.
     */
    public function store(LoginRequest $request, SignInCodes $codes): RedirectResponse
    {
        if (! $codes->active()) {
            $request->authenticate();

            $user = auth()->user();

            // Deactivated accounts must not be able to sign in at all.
            if (! $user->is_active) {
                Auth::guard('web')->logout();

                throw ValidationException::withMessages([
                    'email' => 'Your account has been deactivated. Please contact the administrator.',
                ]);
            }

            return $this->completeSignIn($request, $user);
        }

        // Check the password without signing in.
        $user = $request->validateCredentials();

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated. Please contact the administrator.',
            ]);
        }

        if (! $codes->requiredFor($user, $request)) {
            Auth::guard('web')->login($user, $request->boolean('remember'));

            return $this->completeSignIn($request, $user);
        }

        if ($error = $codes->start($request, $user, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => $error]);
        }

        return redirect()->route('login.code');
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
