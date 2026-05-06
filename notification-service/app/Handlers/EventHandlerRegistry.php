<?php

namespace App\Handlers;

use App\Contracts\EventTypeHandler;
use InvalidArgumentException;

class EventHandlerRegistry
{
    /**
     * @param  iterable<EventTypeHandler>  $handlers
     */
    public function __construct(
        protected iterable $handlers
    ) {}

    public function forType(string $type): EventTypeHandler
    {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($type)) {
                return $handler;
            }
        }

        throw new InvalidArgumentException('No handler for event type: '.$type);
    }
}
