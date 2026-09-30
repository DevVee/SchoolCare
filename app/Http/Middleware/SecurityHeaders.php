<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
            "img-src 'self' data: blob:",
            "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net",
            "connect-src 'self' https://psgc.cloud",
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
}
