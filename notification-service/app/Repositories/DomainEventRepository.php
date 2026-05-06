<?php

namespace App\Repositories;

use App\Models\DomainEvent;

class DomainEventRepository
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $metadata
     */
    public function createIncoming(string $correlationId, string $type, array $payload, array $metadata): DomainEvent
    {
        return DomainEvent::create([
            'correlation_id' => $correlationId,
            'type' => $type,
            'payload' => $payload,
            'metadata' => $metadata,
            'status' => 'received',
        ]);
    }

    public function markQueued(DomainEvent $event): void
    {
        $event->update(['status' => 'queued']);
    }

    public function markProcessed(DomainEvent $event, int $durationMs): void
    {
        $event->update([
            'status' => 'processed',
            'duration_ms' => $durationMs,
            'last_error' => null,
        ]);
    }

    public function markFailed(DomainEvent $event, string $message): void
    {
        $event->update([
            'status' => 'failed',
            'last_error' => $message,
        ]);
    }

    public function findByCorrelationAndType(string $correlationId, string $type): ?DomainEvent
    {
        return DomainEvent::query()
            ->where('correlation_id', $correlationId)
            ->where('type', $type)
            ->first();
    }
}
