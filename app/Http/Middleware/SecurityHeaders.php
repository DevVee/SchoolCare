<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds security response headers to every web response.
 *
 * Headers applied:
 *   - Content-Security-Policy    : Restricts resource origins to prevent XSS
 *   - X-Frame-Options            : Prevents clickjacking
 *   - X-Content-Type-Options     : Prevents MIME sniffing
 *   - X-XSS-Protection           : Legacy XSS filter for older browsers
 *   - Referrer-Policy            : Limits referrer leakage (patient IDs in URLs)
 *   - Permissions-Policy         : Disables unused browser APIs
 *   - Strict-Transport-Security  : Enforces HTTPS (only added on HTTPS connections)
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // ── Clickjacking protection ───────────────────────────────────────────
        $response->headers->set('X-Frame-Options', 'DENY');

        // ── MIME sniffing protection ──────────────────────────────────────────
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // ── Legacy XSS filter (IE/Chrome < 78) ───────────────────────────────
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // ── Referrer policy (prevents patient IDs leaking to 3rd-party URLs) ─
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // ── Disable browser features not needed for a clinic MIS ─────────────
        $response->headers->set(
            'Permissions-Policy',
            'accelerometer=(), camera=(), geolocation=(), gyroscope=(), ' .
            'magnetometer=(), microphone=(), payment=(), usb=()'
        );

        // ── Content Security Policy ───────────────────────────────────────────
        // Bootstrap + custom SCSS served locally via Vite; Bootstrap JS uses
        // inline handlers — 'unsafe-inline' is retained until nonce-based CSP
        // can be wired through the Vite plugin.
        // Google Fonts CSS comes from fonts.googleapis.com;
        // the actual woff2 files are served from fonts.gstatic.com.
        // Bootstrap Icons are now bundled locally via Vite — no CDN needed there.
        // Chart.js (dashboard + monthly/annual reports) is loaded from
        // cdn.jsdelivr.net; the patient address picker calls the PSGC API
        // at https://psgc.cloud (patients/create + patients/edit).
        // `npm run dev` / `composer dev` (local only): the page loads its CSS and JS from
        // the Vite dev server and keeps a websocket to it. Without these the page has
        // no styles and no scripts, so the sidebar, topbar menus and buttons do nothing.
        [$dev, $devSocket] = $this->viteDevOrigins();
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net{$dev}",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net{$dev}",
            "img-src 'self' data: blob:{$dev}",
            "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net{$dev}",
            "connect-src 'self' https://psgc.cloud{$dev}{$devSocket}",
            "form-action 'self' https:",
            "base-uri 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
        ]);
        $response->headers->set('Content-Security-Policy', $csp);

        // ── HSTS — only set on actual HTTPS connections ───────────────────────
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        // ── Remove server fingerprinting headers ──────────────────────────────
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }

    /**
     * The Vite dev server's origin and websocket origin (each with a leading space)
     * while it runs in a local environment, else empty strings. Never in production.
     *
     * @return array{0: string, 1: string}
     */
    private function viteDevOrigins(): array
    {
        if (! app()->environment('local') || ! Vite::isRunningHot()) {
            return ['', ''];
        }

        $url = trim((string) @file_get_contents(Vite::hotFile()));
        $parts = parse_url($url);
        if (! isset($parts['scheme'], $parts['host']) || ! in_array($parts['scheme'], ['http', 'https'], true)) {
            return ['', ''];
        }

        $host = $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $socket = $parts['scheme'] === 'https' ? 'wss' : 'ws';

        return [" {$parts['scheme']}://{$host}", " {$socket}://{$host}"];
    }
}
