<?php

namespace App\RabbitMq;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * Topologia alinhada ao notification-service (exchange + filas + DLQ).
 *
 * @see notification-service App\RabbitMq\Topology
 */
class Topology
{
    public function declare(AMQPChannel $channel): void
    {
        $exchange = (string) config('rabbitmq.exchange');
        $queue = (string) config('rabbitmq.notifications_queue');
        $dlq = (string) config('rabbitmq.notifications_dlq');
        $types = config('lab_events.allowed_types');

        $channel->exchange_declare($exchange, 'direct', false, true, false);

        $channel->queue_declare($dlq, false, true, false, false, false);
        $channel->queue_bind($dlq, $exchange, $dlq);

        $channel->queue_declare(
            $queue,
            false,
            true,
            false,
            false,
            false,
            new AMQPTable([
                'x-dead-letter-exchange' => $exchange,
                'x-dead-letter-routing-key' => $dlq,
            ])
        );

        foreach ($types as $routingKey) {
            $channel->queue_bind($queue, $exchange, $routingKey);
        }
    }
}
