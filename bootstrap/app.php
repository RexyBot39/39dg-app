<?php

use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\RateLimitingConfig;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Middleware registration for 39DG Advisor
|--------------------------------------------------------------------------
|
| Laravel 13 uses bootstrap/app.php for middleware — there is no Kernel.php.
|
| Order matters:
|   1. TrustProxies   — must be first so all subsequent middleware sees
|                        the correct scheme, IP, and host from Railway's proxy
|   2. ForceHttps     — redirect HTTP → HTTPS before any other processing
|   3. SecurityHeaders — set CSP/HSTS/etc. on every HTML response going out
|
| Rate limiters are registered via RateLimitingConfig::register() and then
| applied per-route-group in routes/web.php and routes/api.php using the
| named limiters ("chat", "api", "web").
|
*/

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // Register rate limiters now that the container is available
        RateLimitingConfig::register();

        // Global middleware — runs on every request, in this order
        $middleware->prepend(TrustProxies::class);  // must be first
        $middleware->append(ForceHttps::class);
        $middleware->append(SecurityHeaders::class);

        // Alias for route-level throttling
        $middleware->alias([
            'throttle.chat' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        ]);

        // Trust Railway's proxy for secure cookies
        $middleware->trustProxies(at: '*');

        // Ensure session cookies are always marked Secure + SameSite=Lax
        $middleware->encryptCookies();
        $middleware->validateCsrfTokens(except: [
            // If the chat widget POSTs from an embedded iframe on a different
            // origin, add that route here. Otherwise keep CSRF on everywhere.
            // 'advisor/chat',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Return JSON for rate limit errors on API/chat routes
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*') || $request->is('advisor/chat*')) {
                return response()->json([
                    'error'   => 'Too many requests. Please slow down.',
                    'retryIn' => $e->getHeaders()['Retry-After'] ?? 60,
                ], 429);
            }
        });
    })
    ->create();
