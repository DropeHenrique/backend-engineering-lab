<?php

namespace App\Contracts;

use App\Notifications\NotificationDispatcher;

interface EventTypeHandler
{
    public function supports(string $type): bool;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     */
    public function handle(array $payload, array $metadata, NotificationDispatcher $dispatcher): void;
}
