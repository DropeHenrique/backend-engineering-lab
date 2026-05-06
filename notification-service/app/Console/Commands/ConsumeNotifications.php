<?php

namespace App\Console\Commands;

use App\Handlers\EventHandlerRegistry;
use App\Models\DomainEvent;
use App\Notifications\NotificationDispatcher;
use App\RabbitMq\Topology;
use App\Repositories\DomainEventRepository;
use App\Services\IdempotencyGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class ConsumeNotifications extends Command
{
    protected $signature = 'notifications:consume';

    protected $description = 'Consume domain events from RabbitMQ (JSON envelope)';

    public function handle(
        Topology $topology,
        EventHandlerRegistry $registry,
        IdempotencyGuard $idempotency,
        DomainEventRepository $events,
        NotificationDispatcher $dispatcher
    ): int {
        $h = config('rabbitmq.hosts.0');
        $queue = config('lab_events.queue');

        $connection = new AMQPStreamConnection(
            $h['host'],
            (int) $h['port'],
            $h['user'],
            $h['password'],
            $h['vhost']
        );

        $channel = $connection->channel();
        $topology->declare($channel);
        $channel->basic_qos(0, 1, false);

        $this->info('Consuming queue: '.$queue);

        $channel->basic_consume(
            $queue,
            '',
            false,
            false,
            false,
            false,
            function (AMQPMessage $msg) use (
                $channel,
                $registry,
                $idempotency,
                $events,
                $dispatcher
            ): void {
                $tag = $msg->getDeliveryTag();
                $body = $msg->getBody();

                try {
                    /** @var array<string, mixed> $envelope */
                    $envelope = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                } catch (Throwable $e) {
                    Log::error('notification_consumer.invalid_json', [
                        'component' => 'notification_consumer',
                        'status' => 'invalid_json',
                        'error' => $e->getMessage(),
                    ]);
                    $channel->basic_ack($tag);

                    return;
                }

                $type = $envelope['type'] ?? null;
                $payload = $envelope['payload'] ?? [];
                $metadata = $envelope['metadata'] ?? [];

                if (! is_string($type) || ! is_array($payload) || ! is_array($metadata)) {
                    Log::warning('notification_consumer.invalid_envelope', [
                        'component' => 'notification_consumer',
                        'status' => 'invalid_envelope',
                    ]);
                    $channel->basic_ack($tag);

                    return;
                }

                $cid = $metadata['correlation_id'] ?? null;
                if (! is_string($cid) || $cid === '') {
                    Log::warning('notification_consumer.missing_correlation_id', [
                        'component' => 'notification_consumer',
                        'status' => 'missing_correlation_id',
                    ]);
                    $channel->basic_ack($tag);

                    return;
                }

                if ($idempotency->alreadyProcessed($cid)) {
                    Log::info('notification_consumer.duplicate_skipped', [
                        'component' => 'notification_consumer',
                        'status' => 'duplicate_skipped',
                        'correlation_id' => $cid,
                        'type' => $type,
                    ]);
                    $channel->basic_ack($tag);

                    return;
                }

                $model = DomainEvent::query()
                    ->where('correlation_id', $cid)
                    ->where('type', $type)
                    ->first();

                $delays = config('lab_events.retry_backoff_seconds');
                $maxAttempts = config('lab_events.max_attempts');
                $started = microtime(true);
                $lastError = null;

                for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                    if ($attempt > 1 && isset($delays[$attempt - 2])) {
                        sleep((int) $delays[$attempt - 2]);
                    }

                    try {
                        $registry->forType($type)->handle($payload, $metadata, $dispatcher);
                        $duration = (int) round((microtime(true) - $started) * 1000);

                        $idempotency->rememberProcessed($cid);

                        if ($model) {
                            $events->markProcessed($model, $duration);
                        }

                        Log::info('notification_consumer.processed', [
                            'component' => 'notification_consumer',
                            'status' => 'processed',
                            'correlation_id' => $cid,
                            'type' => $type,
                            'duration_ms' => $duration,
                            'attempts' => $attempt,
                        ]);

                        $channel->basic_ack($tag);

                        return;
                    } catch (Throwable $e) {
                        $lastError = $e;
                        Log::warning('notification_consumer.attempt_failed', [
                            'component' => 'notification_consumer',
                            'status' => 'attempt_failed',
                            'correlation_id' => $cid,
                            'type' => $type,
                            'attempt' => $attempt,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                if ($model) {
                    $events->markFailed($model, $lastError?->getMessage() ?? 'unknown');
                }

                Log::error('notification_consumer.moved_dlq', [
                    'component' => 'notification_consumer',
                    'status' => 'moved_dlq',
                    'correlation_id' => $cid,
                    'type' => $type,
                    'error' => $lastError?->getMessage(),
                ]);

                $channel->basic_nack($tag, false, false);
            }
        );

        while ($channel->is_consuming()) {
            try {
                $channel->wait();
            } catch (Throwable $e) {
                $this->error($e->getMessage());
                usleep(500_000);

                break;
            }
        }

        $channel->close();
        $connection->close();

        return self::SUCCESS;
    }
}
