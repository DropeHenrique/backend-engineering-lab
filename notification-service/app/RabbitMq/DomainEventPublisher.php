<?php

namespace App\RabbitMq;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class DomainEventPublisher
{
    protected ?AMQPStreamConnection $connection = null;

    protected ?AMQPChannel $channel = null;

    /**
     * @param  array{type: string, payload: array<string, mixed>, metadata: array<string, mixed>}  $envelope
     */
    public function publish(array $envelope): void
    {
        $type = $envelope['type'];

        [$conn, $ch] = $this->ensureChannel();

        try {
            (new Topology)->declare($ch);

            $body = json_encode($envelope, JSON_THROW_ON_ERROR);
            $props = [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ];
            $cid = data_get($envelope, 'metadata.correlation_id');
            if (is_string($cid) && $cid !== '') {
                $props['correlation_id'] = $cid;
            }
            $msg = new AMQPMessage($body, $props);

            $ch->basic_publish($msg, config('lab_events.exchange'), $type);
        } finally {
            $ch->close();
            $conn->close();
            $this->channel = null;
            $this->connection = null;
        }
    }

    /**
     * @return array{0: AMQPStreamConnection, 1: AMQPChannel}
     */
    protected function ensureChannel(): array
    {
        $h = config('rabbitmq.hosts.0');
        $this->connection = new AMQPStreamConnection(
            $h['host'],
            (int) $h['port'],
            $h['user'],
            $h['password'],
            $h['vhost']
        );
        $this->channel = $this->connection->channel();

        return [$this->connection, $this->channel];
    }
}
