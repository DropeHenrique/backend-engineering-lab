<?php

namespace App\Handlers;

use App\Contracts\EventTypeHandler;
use App\Notifications\NotificationDispatcher;

class PasswordResetHandler implements EventTypeHandler
{
    public function supports(string $type): bool
    {
        return $type === 'PASSWORD_RESET';
    }

    public function handle(array $payload, array $metadata, NotificationDispatcher $dispatcher): void
    {
        $dispatcher->dispatch('PASSWORD_RESET', $payload, $metadata);
    }
}
