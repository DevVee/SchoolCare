<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // ── Trust reverse proxy / load balancer (must run first) ─────────────
        // Ensures Request::ip() returns the real client IP for audit logs and
        // rate limiting, not the proxy's internal IP.
        $middleware->prepend(\App\Http\Middleware\TrustProxies::class);

        // ── Security response headers on every web response ───────────────────
        // Adds CSP, X-Frame-Options, HSTS, etc. to all web responses.
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            // Sign out after the inactivity limit in Settings > Security (keep-alive is not use).
            \App\Http\Middleware\EnforceIdleTimeout::class,
        ]);

        // ── Register custom middleware aliases ─────────────────────────────────
        $middleware->alias([
            'check.active'       => \App\Http\Middleware\CheckActiveUser::class,
            'password.changed'   => \App\Http\Middleware\EnsurePasswordChanged::class,
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Secret settings are never flashed back into the session on a validation error.
        $exceptions->dontFlash(['ai_groq_api_key']);

        // ── 419 CSRF TokenMismatch: log + graceful recovery ───────────────────
        //
        // Root causes:
        //   1. User leaves a tab open across a session expiry / deploy
        //   2. Sessions cleared (e.g. user deactivated, password reset)
        //   3. APP_KEY change (invalidates every session and token)
        //
        // Recovery strategy:
        //   - AJAX/JSON callers  → 419 JSON so the JS can show a "refresh" prompt
        //   - Browser requests   → redirect to /login with an explanatory flash message
        //     (much better UX than a dead "419 Page Expired" wall)
        $exceptions->render(function (
            TokenMismatchException $e,
            \Illuminate\Http\Request $request
        ) {
            \Illuminate\Support\Facades\Log::warning('419 CSRF TokenMismatch', [
                'url'         => $request->fullUrl(),
                'method'      => $request->method(),
                'ip'          => $request->ip(),
                'user_id'     => optional(auth()->user())->id,
                'user_agent'  => $request->userAgent(),
                'referer'     => $request->header('Referer'),
                'session_id'  => $request->hasSession()
                                    ? $request->session()->getId()
                                    : null,
                'has_token'   => $request->hasSession()
                                    ? (bool) $request->session()->token()
                                    : false,
                'session_drv' => config('session.driver'),
            ]);

            // AJAX / API callers: return JSON so the frontend can handle it
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session has expired. Please refresh the page and try again.',
                    'expired' => true,
                ], 419);
            }

            // Browser form submissions: redirect to login with a helpful message.
            // The flash message is displayed in the auth-session-status component.
            return redirect()->route('login')
                ->with('status', 'Your session expired or timed out. Please log in again to continue.');
        });
    })->create();
