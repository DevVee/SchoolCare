<?php

namespace App\Http\Middleware;

use App\Support\SessionPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out someone who has not used the app for the inactivity limit in
 * Admin > Settings > Security (SessionPolicy::idleMinutes()).
 *
 * The background keep-alive (GET session.token every 20 minutes, ui/session.js)
 * keeps the stored session and CSRF token fresh but is not use, so it does not
 * reset the clock: a tab left open on a shared clinic computer does not stay
 * signed in for ever. A device signed in with "Keep me signed in" is exempt.
 */
class EnforceIdleTimeout
{
    public const KEY = 'last_active_at';

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        if (! $request->hasSession() || ! $guard->check()) {
            return $next($request);
        }

        $session = $request->session();
        $now = time();
        $last = (int) $session->get(self::KEY, $now);
        $remembered = $request->cookies->has($guard->getRecallerName());

        if (! $remembered && $now - $last > SessionPolicy::idleMinutes() * 60) {
            $guard->logout();
            $session->invalidate();
            $session->regenerateToken();

            $message = 'You were signed out after '.SessionPolicy::idleLabel().' without activity. Please sign in again.';

            return $request->expectsJson()
                ? response()->json(['message' => $message, 'expired' => true], 401)
                : redirect()->guest(route('login'))->with('status', $message);
        }

        if (! $request->routeIs('session.token')) {
            $session->put(self::KEY, $now);
        }

        return $next($request);
    }
}
