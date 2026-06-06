<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        // AI chat endpoint — tight limit, every request costs OpenAI money
        RateLimiter::for('chat', function (Request $request) {
            $key = $request->ip() . '|' . $request->session()->getId();
            return Limit::perMinute(config('security.rate_limits.chat', 10))
                ->by($key)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'error'   => 'Too many requests. Please wait a moment before asking another question.',
                        'retryIn' => $headers['Retry-After'] ?? 60,
                    ], 429, $headers);
                });
        });

        // General API routes
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(config('security.rate_limits.api', 60))
                ->by($request->ip());
        });

        // Web/Blade routes — broad safety net
        RateLimiter::for('web', function (Request $request) {
            return Limit::perMinute(config('security.rate_limits.web', 120))
                ->by($request->ip());
        });
    }
}
