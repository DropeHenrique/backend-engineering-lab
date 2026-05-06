<?php

namespace App\Notifications\Channels;

use App\Contracts\NotificationChannel;
use Illuminate\Support\Facades\Log;

/**
 * Mock de e-mail: apenas log estruturado (produção poderia usar Mailtrap/Mail).
 */
class EmailNotificationChannel implements NotificationChannel
{
    public function name(): string
    {
        return 'email';
    }

    public function send(string $eventType, array $payload, array $metadata): void
    {
        $to = $payload['email'] ?? $payload['user_email'] ?? 'unknown@example.com';

        Log::info('notification.email_mock', [
            'channel' => $this->name(),
            'event_type' => $eventType,
            'correlation_id' => $metadata['correlation_id'] ?? null,
            'to' => $to,
            'subject' => match ($eventType) {
                'ORDER_CONFIRMED' => 'Pedido confirmado',
                'USER_REGISTERED' => 'Bem-vindo',
                'PASSWORD_RESET' => 'Redefinição de senha',
                default => 'Notificação',
            },
            'status' => 'mock_sent',
        ]);
    }
}
