<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

/**
 * Trust the upstream reverse proxy / load balancer so that:
 *   - Request::ip()      returns the real client IP (for audit logs)
 *   - Request::secure()  returns true when the proxy terminates HTTPS
 *   - Rate limiters      throttle per real client, not per proxy
 *
 * TRUST_PROXIES is configured in config/app.php (reads from env).
 * The middleware reads config() — not env() — so it works correctly
 * even when `php artisan config:cache` is active (env() returns null
 * for variables that aren't real OS env vars after caching).
 *
 * Set TRUST_PROXIES=* in .env to trust all proxies.
 * For a known proxy IP (e.g. 10.0.0.1), set TRUST_PROXIES=10.0.0.1.
 */
class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR    |
        Request::HEADER_X_FORWARDED_HOST   |
        Request::HEADER_X_FORWARDED_PORT   |
        Request::HEADER_X_FORWARDED_PROTO  |
        Request::HEADER_X_FORWARDED_AWS_ELB;

    public function __construct()
    {
        // Use config() — not env() — so this works with config:cache active.
        // env() returns null for variables not set as real OS env vars after caching.
        $value = config('app.trust_proxies', '*');
        $this->proxies = $value === '*' ? '*' : array_map('trim', explode(',', (string) $value));
    }
}
