<?php

return [
    'hosts' => [
        [
            'host' => env('RABBITMQ_HOST', '127.0.0.1'),
            'port' => (int) env('RABBITMQ_PORT', 5672),
            'user' => env('RABBITMQ_USER', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),
            'vhost' => env('RABBITMQ_VHOST', '/'),
        ],
    ],
    'exchange' => env('RABBITMQ_EXCHANGE', 'lab.events'),
    'notifications_queue' => env('RABBITMQ_QUEUE_NOTIFICATIONS', 'notifications'),
    'notifications_dlq' => env('RABBITMQ_QUEUE_DLQ', 'notifications.dlq'),
];
