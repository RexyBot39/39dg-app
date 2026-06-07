<?php

use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustProxies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(TrustProxies::class);
        $middleware->append(ForceHttps::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->trustProxies(at: '*');
        $middleware->encryptCookies();
        $middleware->validateCsrfTokens(except: ['advisor/*']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (
            \Illuminate\Http\Exceptions\ThrottleRequestsException $e,
            Request $request
        ) {
            if ($request->expectsJson() || $request->is('api/*') || $request->is('advisor/chat*')) {
                return response()->json([
                    'error'   => 'Too many requests. Please slow down.',
                    'retryIn' => $e->getHeaders()['Retry-After'] ?? 60,
                ], 429);
            }
        });
    })
    ->create();
