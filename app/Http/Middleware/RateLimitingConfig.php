<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limiting strategy for the 39DG Advisor.
 *
 * Three tiers, registered in bootstrap/app.php:
 *
 *   "chat"    — The AI advisor endpoint. Tight limit because each request
 *               hits OpenAI and costs real money. 10 requests per minute
 *               per IP is generous for a chat widget but stops abuse.
 *               Keyed by IP + session to handle shared IPs (offices, etc.)
 *               without punishing innocent users.
 *
 *   "api"     — General API routes (feed refresh, health check). Per-IP,
 *               looser — 60/min. Primarily there to stop hammering.
 *
 *   "web"     — Standard Blade web routes. 120/min per IP.
 *               High enough that no real user hits it.
 */
class RateLimitingConfig
{
    /**
     * Call this from a ServiceProvider or bootstrap/app.php to register
     * all rate limiters in one place.
     */
    public static function register(): void
    {
        // Chat / AI advisor endpoint — expensive per-call, protect hard
        RateLimiterFacade::for('chat', function (Request $request) {
            $key = $request->ip() . '|' . $request->session()->getId();
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(10)
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'error'   => 'Too many requests. Please wait a moment before asking another question.',
                        'retryIn' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        // General API routes
        RateLimiterFacade::for('api', function (Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)
                ->by($request->ip());
        });

        // Web routes — broad protection only
        RateLimiterFacade::for('web', function (Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(120)
                ->by($request->ip());
        });
    }
}
