<?php

namespace App\Notifications;

use App\Contracts\NotificationChannel;

class NotificationDispatcher
{
    /**
     * @param  iterable<NotificationChannel>  $channels
     */
    public function __construct(
        protected iterable $channels
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     */
    public function dispatch(string $eventType, array $payload, array $metadata): void
    {
        foreach ($this->channels as $channel) {
            $channel->send($eventType, $payload, $metadata);
        }
    }
}
