<?php

namespace App\RabbitMq;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Wire\AMQPTable;

class Topology
{
    public function declare(AMQPChannel $channel): void
    {
        $exchange = config('lab_events.exchange');
        $queue = config('lab_events.queue');
        $dlq = config('lab_events.dlq');
        $types = config('lab_events.types');

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
