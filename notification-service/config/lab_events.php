<?php

return [
    'exchange' => env('RABBITMQ_EXCHANGE', 'lab.events'),
    'queue' => env('RABBITMQ_QUEUE_NOTIFICATIONS', 'notifications'),
    'dlq' => env('RABBITMQ_QUEUE_DLQ', 'notifications.dlq'),
    'types' => [
        'ORDER_CONFIRMED',
        'USER_REGISTERED',
        'PASSWORD_RESET',
    ],
    'idempotency_ttl_seconds' => (int) env('NOTIFICATION_IDEMPOTENCY_TTL', 86400),
    'retry_backoff_seconds' => [1, 5, 25],
    'max_attempts' => 3,
];
