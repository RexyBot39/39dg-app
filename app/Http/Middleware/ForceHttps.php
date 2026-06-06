<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceHttps
{
    /**
     * Redirect all HTTP requests to HTTPS in production.
     *
     * Railway handles TLS termination, so by the time a request reaches
     * this middleware the scheme is already "https" (once TrustProxies is
     * registered). This acts as a belt-and-suspenders redirect for any
     * request that somehow arrives over plain HTTP.
     *
     * Also sets the HSTS header on every HTTPS response so browsers
     * remember to use HTTPS for the next year without needing a redirect.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production') && ! $request->secure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        $response = $next($request);

        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}
