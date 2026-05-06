<?php

namespace App\Handlers;

use App\Contracts\EventTypeHandler;
use App\Notifications\NotificationDispatcher;

class UserRegisteredHandler implements EventTypeHandler
{
    public function supports(string $type): bool
    {
        return $type === 'USER_REGISTERED';
    }

    public function handle(array $payload, array $metadata, NotificationDispatcher $dispatcher): void
    {
        $dispatcher->dispatch('USER_REGISTERED', $payload, $metadata);
    }
}
