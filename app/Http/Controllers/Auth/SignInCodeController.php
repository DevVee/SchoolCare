<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\CompletesSignIn;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SignInCodes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "Check your email": the second step of signing in when Settings > Security >
 * "Ask for a sign-in code by email" is on (see AuthenticatedSessionController).
 * Guest routes; they only work while this session has a pending sign-in.
 */
class SignInCodeController extends Controller
{
    use CompletesSignIn;

    public function __construct(private readonly SignInCodes $codes) {}

    public function show(Request $request): View|RedirectResponse
    {
        [$pending, $user, $redirect] = $this->resolve($request);
        if ($redirect) {
            return $redirect;
        }

        return view('auth.sign-in-code', [
            'maskedEmail'  => SignInCodes::maskEmail($user->email),
            'rememberDays' => $this->codes->rememberDays(),
            'resendIn'     => $this->codes->resendWait($pending),
            'minutes'      => SignInCodes::CODE_MINUTES,
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        [$pending, $user, $redirect] = $this->resolve($request);
        if ($redirect) {
            return $redirect;
        }

        // Spaces or dashes typed or pasted with the code are ignored.
        $code = preg_replace('/\D+/', '', (string) $request->input('code'));

        if (strlen($code) !== 6) {
            return $this->backWithError('Enter the 6-digit code from the email.');
        }

        $result = $this->codes->check($request, $user, $pending, $code);

        if ($result === SignInCodes::LOCKED) {
            return $this->backWithError('Too many wrong codes. Send a new code to try again.');
        }

        if ($result === SignInCodes::WRONG) {
            $left = $this->codes->triesLeft($pending);

            return $this->backWithError("That code is not right. Check the email and try again. {$left} ".($left === 1 ? 'try' : 'tries').' left.');
        }

        $this->codes->clear($request);

        if ($request->boolean('remember_device')) {
            $this->codes->rememberDevice($user, $request);
        }

        Auth::guard('web')->login($user, (bool) ($pending['remember'] ?? false));

        return $this->completeSignIn($request, $user);
    }

    public function resend(Request $request): RedirectResponse
    {
        [$pending, $user, $redirect] = $this->resolve($request);
        if ($redirect) {
            return $redirect;
        }

        if ($error = $this->codes->resend($request, $user, $pending)) {
            return $this->backWithError($error);
        }

        return redirect()->route('login.code')
            ->with('status', 'We sent a new code. The earlier code no longer works.');
    }

    /**
     * The pending sign-in and its user, or a redirect to the sign-in page when
     * there is none, it expired (with its code), or the account was deactivated.
     *
     * @return array{0: ?array, 1: ?User, 2: ?RedirectResponse}
     */
    private function resolve(Request $request): array
    {
        $pending = $this->codes->pending($request);

        if (! $pending) {
            return [null, null, redirect()->route('login')];
        }

        if ($this->codes->expired($pending)) {
            $this->codes->clear($request);

            return [null, null, redirect()->route('login')
                ->withErrors(['email' => 'Your sign-in code expired. Sign in again to get a new one.'])];
        }

        $user = $this->codes->pendingUser($pending);

        if (! $user) {
            $this->codes->clear($request);

            return [null, null, redirect()->route('login')
                ->withErrors(['email' => 'Your account has been deactivated. Please contact the administrator.'])];
        }

        return [$pending, $user, null];
    }

    private function backWithError(string $message): RedirectResponse
    {
        return redirect()->route('login.code')->withErrors(['code' => $message]);
    }
}
