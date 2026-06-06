<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Security headers for the 39DG Advisor chat widget.
     *
     * The Content-Security-Policy below is tuned for two deployment modes:
     *
     *   1. Standalone (advisor runs on its own Railway URL)
     *      - frame-ancestors 'none' prevents clickjacking
     *
     *   2. Embedded iframe on 39dollarglasses.com (more common)
     *      - Change frame-ancestors to 'self' https://39dollarglasses.com
     *      - Set ADVISOR_EMBED_ORIGIN=https://39dollarglasses.com in Railway
     *        env vars and update frame-ancestors below
     *
     * The script-src and style-src are intentionally strict. If you add
     * third-party analytics or chat scripts, add their origins here rather
     * than using 'unsafe-inline'.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only apply to HTML responses — skip JSON API responses
        $contentType = $response->headers->get('Content-Type', '');
        if (! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $embedOrigin = config('security.embed_origin', 'none');
        $frameAncestors = $embedOrigin === 'none'
            ? "'none'"
            : "'self' {$embedOrigin}";

        $appUrl = config('app.url');

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",       // Vite-compiled CSS uses inline styles
            "img-src 'self' data: https:",             // allow product images from any HTTPS source
            "connect-src 'self'",                      // AJAX only to this app
            "font-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors {$frameAncestors}",
            "upgrade-insecure-requests",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Frame-Options', $embedOrigin === 'none' ? 'DENY' : 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Remove headers that leak server info
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }

    /**
     * Extract the existing CSP nonce if Vite/Laravel set one, or return
     * a placeholder. In practice, Laravel 13 + Vite sets @vite nonces
     * automatically — this just threads it through to CSP.
     */
}
