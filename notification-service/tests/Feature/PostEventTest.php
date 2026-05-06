<?php

namespace Tests\Feature;

use App\RabbitMq\DomainEventPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_events_returns_202_and_queues(): void
    {
        $this->mock(DomainEventPublisher::class, function ($mock) {
            $mock->shouldReceive('publish')
                ->once()
                ->with(\Mockery::on(function (array $envelope) {
                    return $envelope['type'] === 'ORDER_CONFIRMED'
                        && ($envelope['metadata']['correlation_id'] ?? null) !== null;
                }));
        });

        $cid = (string) Str::uuid();

        $response = $this->postJson('/api/events', [
            'type' => 'ORDER_CONFIRMED',
            'payload' => [
                'order_id' => 'ord_123',
                'user_email' => 'buyer@example.com',
            ],
            'metadata' => [
                'correlation_id' => $cid,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('correlation_id', $cid);
    }

    public function test_duplicate_correlation_returns_409(): void
    {
        $this->mock(DomainEventPublisher::class)->shouldReceive('publish')->once();

        $cid = (string) Str::uuid();
        $body = [
            'type' => 'USER_REGISTERED',
            'payload' => [
                'user_id' => 'u1',
                'email' => 'u1@example.com',
            ],
            'metadata' => [
                'correlation_id' => $cid,
                'timestamp' => now()->toIso8601String(),
            ],
        ];

        $this->postJson('/api/events', $body)->assertStatus(202);
        $this->postJson('/api/events', $body)->assertStatus(409);
    }

    public function test_validation_rejects_invalid_payload(): void
    {
        $this->mock(DomainEventPublisher::class)->shouldReceive('publish')->never();

        $response = $this->postJson('/api/events', [
            'type' => 'ORDER_CONFIRMED',
            'payload' => [],
            'metadata' => [
                'correlation_id' => (string) Str::uuid(),
                'timestamp' => now()->toIso8601String(),
            ],
        ]);

        $response->assertStatus(422);
    }
}
