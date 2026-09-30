<?php

namespace App\Services;

use App\Mail\SignInCodeMail;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Email sign-in codes (Settings > Security).
 *
 * After a correct password the sign-in is kept "pending" in the session: a
 * random 6-digit code is emailed and only its hash is stored, for 10 minutes.
 * Five wrong codes invalidate it (a new one must be sent); new codes are
 * limited per user. A correct code may remember the browser: a random token
 * in an encrypted, HttpOnly cookie, stored as a SHA-256 hash in trusted_devices.
 *
 * Never locks people out:
 *  - it cannot be turned on while email sending is not set up (UpdateSettingsRequest);
 *  - if email stops being set up later, no code is asked for (active());
 *  - `php artisan auth:otp-off` turns it off from the server.
 */
class SignInCodes
{
    public const CODE_MINUTES = 10;
    public const MAX_ATTEMPTS = 5;
    public const RESEND_SECONDS = 60;          // wait between two codes
    public const MAX_RESENDS_PER_HOUR = 5;     // "Resend code", per user
    public const MAX_CODES_PER_HOUR = 10;      // every code (sign-in + resend), per user: bounds guessing

    // check() results
    public const VERIFIED = 'verified';
    public const WRONG = 'wrong';
    public const LOCKED = 'locked';

    public const SEND_FAILED = "We couldn't send your code. Try again, or ask an administrator.";

    private const SESSION_KEY = 'auth.sign_in_code';

    // ─── Settings ────────────────────────────────────────────────────────────

    /** The setting itself (Settings > Security). */
    public function turnedOn(): bool
    {
        return (bool) settings('otp_enabled', false);
    }

    /**
     * Turned on and email can be delivered. When the server's email is not set
     * up (log / array mailer, or Brevo without a key) nobody could receive a
     * code, so none is asked for.
     */
    public function active(): bool
    {
        if (! $this->turnedOn()) {
            return false;
        }

        if (! self::emailReady()) {
            Log::warning('Sign-in codes are turned on, but email sending is not set up on the server, so no code was asked for.');

            return false;
        }

        return true;
    }

    public function rememberDays(): int
    {
        return max(0, min(365, (int) settings('otp_remember_days', 30)));
    }

    public function appliesTo(User $user): bool
    {
        return settings('otp_applies_to', 'everyone') !== 'admins' || $user->isAdmin();
    }

    /** This sign-in needs a code: turned on, email works, it applies to the user and the browser is not remembered. */
    public function requiredFor(User $user, Request $request): bool
    {
        return $this->active() && $this->appliesTo($user) && ! $this->deviceIsTrusted($user, $request);
    }

    /** Whether the configured mailer actually delivers email (not log / array, Brevo has a key). */
    public static function emailReady(): bool
    {
        return self::mailerDelivers((string) config('mail.default'));
    }

    private static function mailerDelivers(string $mailer, int $depth = 0): bool
    {
        $config = config("mail.mailers.{$mailer}");
        if (! is_array($config)) {
            return false;
        }

        return match ((string) ($config['transport'] ?? $mailer)) {
            'log', 'array' => false,
            'brevo'        => filled(config('services.brevo.key')),
            'failover', 'roundrobin' => $depth < 3 && collect($config['mailers'] ?? [])
                ->contains(fn ($m) => self::mailerDelivers((string) $m, $depth + 1)),
            default        => true,
        };
    }

    // ─── Pending sign-in ─────────────────────────────────────────────────────

    /**
     * Start a pending sign-in and email the first code.
     * Returns null when the code was sent, else a message for the sign-in page.
     */
    public function start(Request $request, User $user, bool $remember): ?string
    {
        $this->clear($request);

        if ($error = $this->issueLimitError($user)) {
            return $error;
        }

        $pending = $this->send($user, $remember);
        if ($pending === null) {
            return self::SEND_FAILED;
        }

        $request->session()->put(self::SESSION_KEY, $pending);

        return null;
    }

    /** The pending sign-in of this session (may be expired, see expired()), or null. */
    public function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        return is_array($pending) && ! empty($pending['user_id']) && ! empty($pending['id']) ? $pending : null;
    }

    /** The pending sign-in ends with its code. */
    public function expired(array $pending): bool
    {
        return now()->getTimestamp() >= (int) ($pending['expires_at'] ?? 0);
    }

    /** The active user of a pending sign-in, or null (deleted or deactivated since). */
    public function pendingUser(array $pending): ?User
    {
        $user = User::query()->find($pending['user_id']);

        return $user && $user->is_active ? $user : null;
    }

    /** Check a typed code. Returns VERIFIED, WRONG or LOCKED; audits each outcome (never the code). */
    public function check(Request $request, User $user, array $pending, string $code): string
    {
        $key = $this->attemptKey($pending);

        if (empty($pending['hash']) || RateLimiter::attempts($key) >= self::MAX_ATTEMPTS) {
            return self::LOCKED;
        }

        if (Hash::check($code, $pending['hash'])) {
            RateLimiter::clear($key);
            AuditLogService::log('code_verified', 'auth', "Sign-in code accepted for '{$user->name}'", actor: $user);

            return self::VERIFIED;
        }

        RateLimiter::hit($key, self::CODE_MINUTES * 60);

        if (RateLimiter::attempts($key) >= self::MAX_ATTEMPTS) {
            // The code can no longer be used; a new one must be sent.
            $request->session()->put(self::SESSION_KEY, ['hash' => null] + $pending);
            AuditLogService::log('code_locked', 'auth', "Sign-in code for '{$user->name}' locked after ".self::MAX_ATTEMPTS.' wrong tries', actor: $user);

            return self::LOCKED;
        }

        AuditLogService::log('code_failed', 'auth', "Wrong sign-in code for '{$user->name}'", actor: $user);

        return self::WRONG;
    }

    public function triesLeft(array $pending): int
    {
        return max(0, self::MAX_ATTEMPTS - RateLimiter::attempts($this->attemptKey($pending)));
    }

    /** Seconds until another code may be sent (0 = now). */
    public function resendWait(array $pending): int
    {
        return max(0, (int) ($pending['sent_at'] ?? 0) + self::RESEND_SECONDS - now()->getTimestamp());
    }

    /**
     * Email a new code for the pending sign-in (the old one stops working).
     * Returns null when sent, else a message for the verify page.
     */
    public function resend(Request $request, User $user, array $pending): ?string
    {
        if (($wait = $this->resendWait($pending)) > 0) {
            return "Wait {$wait} seconds before asking for a new code.";
        }

        $resendKey = 'sign-in-code-resend:'.$user->getKey();
        if (RateLimiter::tooManyAttempts($resendKey, self::MAX_RESENDS_PER_HOUR)) {
            return 'You asked for a new code too many times. Try again in '.$this->minutes(RateLimiter::availableIn($resendKey)).'.';
        }

        if ($error = $this->issueLimitError($user)) {
            return $error;
        }

        $new = $this->send($user, (bool) ($pending['remember'] ?? false));
        if ($new === null) {
            return self::SEND_FAILED;
        }

        RateLimiter::hit($resendKey, 3600);
        RateLimiter::clear($this->attemptKey($pending));
        $request->session()->put(self::SESSION_KEY, $new);

        return null;
    }

    /** End the pending sign-in (completed, expired or abandoned). */
    public function clear(Request $request): void
    {
        if ($pending = $this->pending($request)) {
            RateLimiter::clear($this->attemptKey($pending));
        }

        $request->session()->forget(self::SESSION_KEY);
    }

    /** p***@school.edu */
    public static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).'***'.($domain !== '' ? '@'.$domain : '');
    }

    /** Generate, email and hash a new code. Null when the email could not be sent. */
    private function send(User $user, bool $remember): ?array
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        try {
            Mail::to($user)->send(new SignInCodeMail($code, self::CODE_MINUTES));
        } catch (\Throwable $e) {
            Log::error('Sign-in code email could not be sent', ['user_id' => $user->getKey(), 'error' => $e->getMessage()]);
            AuditLogService::log('code_not_sent', 'auth', "Sign-in code email to '{$user->name}' could not be sent", actor: $user);

            return null;
        }

        RateLimiter::hit($this->issueKey($user), 3600);
        AuditLogService::log('code_sent', 'auth', "Sign-in code emailed to '{$user->name}'", actor: $user);

        return [
            'id'         => Str::random(40),
            'user_id'    => $user->getKey(),
            'remember'   => $remember,
            'hash'       => Hash::make($code),
            'sent_at'    => now()->getTimestamp(),
            'expires_at' => now()->addMinutes(self::CODE_MINUTES)->getTimestamp(),
        ];
    }

    private function issueLimitError(User $user): ?string
    {
        $key = $this->issueKey($user);

        return RateLimiter::tooManyAttempts($key, self::MAX_CODES_PER_HOUR)
            ? 'Too many sign-in codes were sent to this account. Try again in '.$this->minutes(RateLimiter::availableIn($key)).'.'
            : null;
    }

    private function issueKey(User $user): string
    {
        return 'sign-in-code-issue:'.$user->getKey();
    }

    /** Counted in the cache (not the session), per code. */
    private function attemptKey(array $pending): string
    {
        return 'sign-in-code-attempts:'.$pending['id'];
    }

    private function minutes(int $seconds): string
    {
        $minutes = max(1, (int) ceil($seconds / 60));

        return $minutes.' '.($minutes === 1 ? 'minute' : 'minutes');
    }

    // ─── Remembered devices ──────────────────────────────────────────────────

    /** One cookie per user, so people sharing a clinic computer do not replace each other's. */
    public static function deviceCookieName(User $user): string
    {
        return 'device_'.substr(hash_hmac('sha256', 'trusted-device|'.$user->getKey(), (string) config('app.key')), 0, 20);
    }

    /** The browser has an unexpired remembered-device cookie for this user (refreshes last used). */
    public function deviceIsTrusted(User $user, Request $request): bool
    {
        $days = $this->rememberDays();
        $name = self::deviceCookieName($user);
        $token = $request->cookie($name);

        if ($days === 0 || ! is_string($token) || $token === '') {
            return false;
        }

        $device = TrustedDevice::query()
            ->where('user_id', $user->getKey())
            ->where('token_hash', hash('sha256', $token))
            ->first();

        // Also honours a shorter "Remember this device for" set after the device was remembered.
        if (! $device || $device->expires_at->isPast() || $device->created_at->lt(now()->subDays($days))) {
            $device?->delete();
            Cookie::queue(Cookie::forget($name, '/', config('session.domain')));

            return false;
        }

        $device->forceFill(['last_used_at' => now(), 'ip_address' => $request->ip()])->save();

        return true;
    }

    /** Remember this browser for the configured number of days. */
    public function rememberDevice(User $user, Request $request): void
    {
        $days = $this->rememberDays();
        if ($days === 0) {
            return;
        }

        TrustedDevice::query()->where('user_id', $user->getKey())->where('expires_at', '<=', now())->delete();

        $token = Str::random(64);

        TrustedDevice::query()->create([
            'user_id'      => $user->getKey(),
            'token_hash'   => hash('sha256', $token),
            'user_agent'   => Str::limit((string) $request->userAgent(), 250, ''),
            'ip_address'   => $request->ip(),
            'expires_at'   => now()->addDays($days),
            'last_used_at' => now(),
        ]);

        // Encrypted by the EncryptCookies middleware; HttpOnly, SameSite=Lax, Secure on HTTPS.
        Cookie::queue(new SymfonyCookie(
            self::deviceCookieName($user),
            $token,
            now()->addDays($days),
            '/',
            config('session.domain'),
            $request->isSecure() || (bool) config('session.secure') || str_starts_with((string) config('app.url'), 'https://'),
            true,
            false,
            SymfonyCookie::SAMESITE_LAX,
        ));
    }

    /** Forget every remembered device of a user. Returns how many were removed. */
    public function forgetDevices(User $user): int
    {
        return TrustedDevice::query()->where('user_id', $user->getKey())->delete();
    }
}
