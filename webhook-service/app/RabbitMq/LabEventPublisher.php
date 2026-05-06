<?php

namespace App\RabbitMq;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class LabEventPublisher
{
    /**
     * @param  array{type: string, payload: array<string, mixed>, metadata: array<string, mixed>}  $envelope
     */
    public function publish(array $envelope): void
    {
        $type = $envelope['type'];

        $h = config('rabbitmq.hosts.0');
        $exchange = config('rabbitmq.exchange');

        $conn = new AMQPStreamConnection(
            $h['host'],
            (int) $h['port'],
            $h['user'],
            $h['password'],
            $h['vhost']
        );

        $channel = $conn->channel();

        try {
            (new Topology)->declare($channel);

            $body = json_encode($envelope, JSON_THROW_ON_ERROR);
            $props = [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ];
            $cid = data_get($envelope, 'metadata.correlation_id');
            if (is_string($cid) && $cid !== '') {
                $props['correlation_id'] = $cid;
            }

            $channel->basic_publish(new AMQPMessage($body, $props), $exchange, $type);
        } finally {
            $channel->close();
            $conn->close();
        }
    }
}
