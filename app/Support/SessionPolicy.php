<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * How long people stay signed in (Admin > Settings > Security > Sessions) and
 * the signed-in sessions of a user (database session driver).
 *
 *   - Inactivity: someone who has not used the app for idleMinutes() is signed
 *     out (App\Http\Middleware\EnforceIdleTimeout). The background keep-alive
 *     (resources/js/ui/session.js) does not count as use.
 *   - "Keep me signed in on this device" lasts rememberDays(); 0 hides it.
 */
class SessionPolicy
{
    public const IDLE_CHOICES = [30, 60, 120, 240, 480, 720, 1440];
    public const REMEMBER_CHOICES = [0, 1, 7, 14, 30, 90];

    public static function idleMinutes(): int
    {
        $minutes = (int) settings('session_idle_minutes', 480);

        return in_array($minutes, self::IDLE_CHOICES, true) ? $minutes : 480;
    }

    public static function rememberDays(): int
    {
        $days = (int) settings('session_remember_days', 30);

        return in_array($days, self::REMEMBER_CHOICES, true) ? $days : 30;
    }

    public static function rememberEnabled(): bool
    {
        return self::rememberDays() > 0;
    }

    /** "8 hours", "30 minutes", "1 day". */
    public static function idleLabel(): string
    {
        $m = self::idleMinutes();

        return match (true) {
            $m % 1440 === 0 => self::count(intdiv($m, 1440), 'day'),
            $m % 60 === 0   => self::count(intdiv($m, 60), 'hour'),
            default         => self::count($m, 'minute'),
        };
    }

    /** "1 day", "30 days", "2 other devices" (this Laravel's Str::plural cannot prepend the count). */
    public static function count(int $n, string $word): string
    {
        return $n.' '.Str::plural($word, $n);
    }

    /**
     * Apply the settings to runtime config (AppServiceProvider): the stored
     * session lasts as long as the inactivity limit, and the remember-me
     * cookie as long as "Keep me signed in".
     */
    public static function applyToConfig(): void
    {
        config([
            'session.lifetime'          => self::idleMinutes(),
            'auth.guards.web.remember'  => max(1, self::rememberDays()) * 1440,
        ]);
    }

    /** Whether signed-in sessions are stored where they can be listed and ended. */
    public static function tracked(): bool
    {
        return config('session.driver') === 'database' && Schema::hasTable(self::table());
    }

    /**
     * The user's signed-in sessions, this device first, then newest first:
     * device, IP address, last activity and whether it is this one. With a
     * request, this device is always listed (a new session is only stored at
     * the end of its first request).
     *
     * @return Collection<int, array{id: string, device: string, ip: ?string, last_active: \Illuminate\Support\Carbon, current: bool}>
     */
    public static function sessionsOf(User $user, ?Request $request = null): Collection
    {
        if (! self::tracked()) {
            return collect();
        }

        $currentId = $request?->hasSession() ? $request->session()->getId() : null;
        $since = now()->subMinutes((int) config('session.lifetime'))->getTimestamp();

        $rows = self::query()
            ->where('user_id', $user->getKey())
            ->where('last_activity', '>=', $since)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->reject(fn ($row) => $currentId !== null && hash_equals((string) $row->id, $currentId))
            ->map(fn ($row) => [
                'id'          => (string) $row->id,
                'device'      => self::device((string) $row->user_agent),
                'ip'          => $row->ip_address,
                'last_active' => \Illuminate\Support\Carbon::createFromTimestamp((int) $row->last_activity),
                'current'     => false,
            ])
            ->values();

        if ($currentId !== null) {
            $rows->prepend([
                'id'          => $currentId,
                'device'      => self::device((string) $request->userAgent()),
                'ip'          => $request->ip(),
                'last_active' => now(),
                'current'     => true,
            ]);
        }

        return $rows;
    }

    /**
     * Sign the user out everywhere except this request's session. Remember-me
     * cookies on other devices stop working; this device keeps its own.
     * Returns how many sessions were ended.
     */
    public static function endOtherSessions(User $user, Request $request): int
    {
        $ended = self::tracked()
            ? self::query()->where('user_id', $user->getKey())->where('id', '!=', $request->session()->getId())->delete()
            : 0;

        $guard = Auth::guard('web');
        $remembered = $request->cookies->has($guard->getRecallerName());

        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        if ($remembered && $guard->id() === $user->getKey()) {
            // A fresh remember-me cookie for this device (the old token no longer matches).
            $guard->login($user, true);
        }

        return $ended;
    }

    /** Sign the user out on every device (an administrator's action, password reset). */
    public static function endAllSessions(User $user): int
    {
        $ended = self::tracked() ? self::query()->where('user_id', $user->getKey())->delete() : 0;
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        return $ended;
    }

    /** "Chrome on Windows", "Safari on iPhone"; "Unknown device" when it cannot tell. */
    public static function device(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/')                                 => 'Edge',
            str_contains($agent, 'OPR/') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'SamsungBrowser')                       => 'Samsung Internet',
            str_contains($agent, 'Firefox/') || str_contains($agent, 'FxiOS') => 'Firefox',
            str_contains($agent, 'Chrome/') || str_contains($agent, 'CriOS') => 'Chrome',
            str_contains($agent, 'Safari/')                              => 'Safari',
            default                                                      => null,
        };
        $system = match (true) {
            str_contains($agent, 'iPhone')                                  => 'iPhone',
            str_contains($agent, 'iPad')                                    => 'iPad',
            str_contains($agent, 'Android')                                 => 'Android',
            str_contains($agent, 'Windows')                                 => 'Windows',
            str_contains($agent, 'Mac OS X') || str_contains($agent, 'Macintosh') => 'Mac',
            str_contains($agent, 'CrOS')                                    => 'Chromebook',
            str_contains($agent, 'Linux')                                   => 'Linux',
            default                                                         => null,
        };

        return match (true) {
            $browser && $system => "{$browser} on {$system}",
            (bool) $browser     => $browser,
            (bool) $system      => $system,
            default             => 'Unknown device',
        };
    }

    private static function table(): string
    {
        return (string) config('session.table', 'sessions');
    }

    private static function query(): \Illuminate\Database\Query\Builder
    {
        return DB::connection(config('session.connection'))->table(self::table());
    }
}
