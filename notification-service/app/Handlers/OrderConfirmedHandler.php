<?php

namespace App\Handlers;

use App\Contracts\EventTypeHandler;
use App\Notifications\NotificationDispatcher;

class OrderConfirmedHandler implements EventTypeHandler
{
    public function supports(string $type): bool
    {
        return $type === 'ORDER_CONFIRMED';
    }

    public function handle(array $payload, array $metadata, NotificationDispatcher $dispatcher): void
    {
        $dispatcher->dispatch('ORDER_CONFIRMED', $payload, $metadata);
    }
}
