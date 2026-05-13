<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Feature Flag
    |--------------------------------------------------------------------------
    | Master switch. Set AI_ADVISOR_ENABLED=false in .env to instantly disable
    | the widget and API across the entire site — no deploy required.
    |
    | Per-page control is handled in the Blade component by passing enabled=false.
    | Soft-launch pages: neurolux, lumeo, lenses, progressives, frames.
    |--------------------------------------------------------------------------
    */
    'enabled' => env('AI_ADVISOR_ENABLED', false),

    // Pages approved for the soft launch. Used by the pre-launch checker.
    'soft_launch_pages' => ['neurolux', 'lumeo', 'lenses', 'progressives', 'frames'],

    /*
    |--------------------------------------------------------------------------
    | Feed Configuration
    |--------------------------------------------------------------------------
    */
    'feed' => [
        // Multiple feed URLs — primary + optional extras (e.g. kids, sunglasses)
        'urls' => array_filter([
            env('AI_ADVISOR_FEED_URL', ''),
            env('AI_ADVISOR_FEED_URL_2', ''),
            env('AI_ADVISOR_FEED_URL_3', ''),
        ]),
        'format'  => env('AI_ADVISOR_FEED_FORMAT', 'xml'), // xml | tsv
        'timeout' => env('AI_ADVISOR_FEED_TIMEOUT', 60),   // seconds

        // How often the scheduler runs (in hours). Set in Kernel.php.
        'schedule_hours' => env('AI_ADVISOR_SCHEDULE_HOURS', 6),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sanitization
    |--------------------------------------------------------------------------
    | Fields to strip from the raw feed before storing.
    | Extend this list if your feed contains internal/private fields.
    */
    'sanitize' => [
        'blocked_fields' => [
            'cost_of_goods_sold',
            'cost',
            'margin',
            'supplier',
            'internal_note',
            'admin_url',
            'inventory_quantity',
            'inventory_count',
            'warehouse',
            'sku_internal',
            'vendor',
            'purchase_price',
        ],
        // Strip products with these product_type values entirely
        'blocked_product_types' => [],
        // Only import products in these availability states
        'allowed_availability' => ['in stock', 'preorder'],
        // Approved image CDN domains
        'allowed_image_domains' => [
            '39dollarglasses.com',
            'www.39dollarglasses.com',
            'cdn.39dollarglasses.com',
            'cdn.eyeglasses39.net',
            'images.39dollarglasses.com',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing Tiers
    |--------------------------------------------------------------------------
    */
    'budget_tiers' => [
        'budget'  => ['min' => 0,   'max' => 30],
        'mid'     => ['min' => 30,  'max' => 65],
        'premium' => ['min' => 65,  'max' => PHP_INT_MAX],
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI (used in Phase 3+ for the advisor API)
    |--------------------------------------------------------------------------
    */
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model'   => env('AI_ADVISOR_MODEL', 'gpt-4o'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Recommendation Limits
    |--------------------------------------------------------------------------
    */
    'recommendations' => [
        'max_products' => 5,
        'min_products' => 1,
    ],

];
