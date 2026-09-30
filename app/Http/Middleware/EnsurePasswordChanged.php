<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces users whose password was reset by an administrator to choose a new
 * one before using the application. Only the profile page, the password
 * change endpoint, logout and the session keep-alive stay reachable.
 */
class EnsurePasswordChanged
{
    private const ALLOWED_ROUTES = [
        'profile.edit',
        'profile.password',
        'password.update',
        'logout',
        'session.token',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password && ! $request->routeIs(...self::ALLOWED_ROUTES)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'You must change your password before continuing.',
                ], 403);
            }

            return redirect()->route('profile.edit')
                ->with('warning', 'Your password was reset by an administrator. Please choose a new password to continue.');
        }

        return $next($request);
    }
}
