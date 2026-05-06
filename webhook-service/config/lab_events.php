<?php

return [
    'allowed_types' => [
        'ORDER_CONFIRMED',
        'USER_REGISTERED',
        'PASSWORD_RESET',
    ],
    'replay_window_seconds' => (int) env('WEBHOOK_REPLAY_WINDOW', 300),
    'nonce_ttl_seconds' => (int) env('WEBHOOK_NONCE_TTL', 300),
];
