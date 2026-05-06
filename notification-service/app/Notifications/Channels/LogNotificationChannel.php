<?php

namespace App\Notifications\Channels;

use App\Contracts\NotificationChannel;
use Illuminate\Support\Facades\Log;

class LogNotificationChannel implements NotificationChannel
{
    public function name(): string
    {
        return 'log';
    }

    public function send(string $eventType, array $payload, array $metadata): void
    {
        Log::info('notification.log_channel', [
            'channel' => $this->name(),
            'event_type' => $eventType,
            'correlation_id' => $metadata['correlation_id'] ?? null,
            'payload' => $payload,
            'status' => 'dispatched',
        ]);
    }
}
