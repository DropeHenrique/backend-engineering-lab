<?php

namespace App\Contracts;

interface NotificationChannel
{
    public function name(): string;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     */
    public function send(string $eventType, array $payload, array $metadata): void;
}
