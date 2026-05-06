<?php

return [
    'secrets' => [
        'stripe' => env('WEBHOOK_STRIPE_SECRET', 'whsec_demo_stripe'),
        'github' => env('WEBHOOK_GITHUB_SECRET', 'demo_github_webhook_secret'),
        'hotmart' => env('WEBHOOK_HOTMART_SECRET', 'demo_hotmart_hmac_secret'),
        'generic' => env('WEBHOOK_GENERIC_SECRET', 'demo_generic_secret'),
    ],
];
