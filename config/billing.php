<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Billing
    |--------------------------------------------------------------------------
    |
    | Prices live in the Plan enum so the pricing page, the checkout and the
    | database always agree. Stripe only needs the ids of the two paid plans;
    | without them billing reports itself as unconfigured rather than failing a
    | visitor's click with a stack trace.
    |
    */

    'stripe' => [
        'secret' => env('BILLING_STRIPE_SECRET'),
        'webhook_secret' => env('BILLING_STRIPE_WEBHOOK_SECRET'),
        'price_ids' => [
            'pro' => env('BILLING_STRIPE_PRICE_PRO'),
            'premium' => env('BILLING_STRIPE_PRICE_PREMIUM'),
        ],
    ],

    'gateway' => env('BILLING_GATEWAY', 'stripe'),
];
