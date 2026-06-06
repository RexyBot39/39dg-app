<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Embed Origin
    |--------------------------------------------------------------------------
    |
    | If the advisor is embedded as an iframe on another site, set this to
    | that site's origin so the Content-Security-Policy frame-ancestors and
    | X-Frame-Options headers allow it.
    |
    | Set ADVISOR_EMBED_ORIGIN in Railway environment variables.
    |
    | Examples:
    |   ADVISOR_EMBED_ORIGIN=https://39dollarglasses.com   (iframe embed)
    |   ADVISOR_EMBED_ORIGIN=none                          (standalone, no embed)
    |
    */
    'embed_origin' => env('ADVISOR_EMBED_ORIGIN', 'none'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    |
    | Override defaults via Railway env vars without touching code.
    |
    | RATE_LIMIT_CHAT  — AI chat endpoint, requests per minute per IP+session
    | RATE_LIMIT_API   — API routes, requests per minute per IP
    | RATE_LIMIT_WEB   — Web/Blade routes, requests per minute per IP
    |
    */
    'rate_limits' => [
        'chat' => (int) env('RATE_LIMIT_CHAT', 10),
        'api'  => (int) env('RATE_LIMIT_API', 60),
        'web'  => (int) env('RATE_LIMIT_WEB', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | '*' is correct for Railway. If you route traffic through Cloudflare,
    | change this to 'cloudflare' and ensure Cloudflare's IP ranges are used.
    | Do not set a specific IP — Railway's proxy IPs rotate.
    |
    */
    'trusted_proxies' => env('TRUST_PROXIES', '*'),

];
