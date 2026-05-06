<?php

namespace App\Webhooks;

use InvalidArgumentException;

class WebhookProviderRegistry
{
    /**
     * @param  array<string, WebhookProviderContract>  $providers
     */
    public function __construct(
        protected array $providers
    ) {}

    public function get(string $slug): WebhookProviderContract
    {
        if (! isset($this->providers[$slug])) {
            throw new InvalidArgumentException('Unknown webhook provider: '.$slug);
        }

        return $this->providers[$slug];
    }
}
