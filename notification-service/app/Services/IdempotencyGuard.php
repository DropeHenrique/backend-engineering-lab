<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class IdempotencyGuard
{
    public function alreadyProcessed(string $correlationId): bool
    {
        $key = $this->key($correlationId);

        return Cache::has($key);
    }

    public function rememberProcessed(string $correlationId): void
    {
        $ttl = config('lab_events.idempotency_ttl_seconds');
        Cache::put($this->key($correlationId), true, now()->addSeconds($ttl));
    }

    protected function key(string $correlationId): string
    {
        return 'notification:processed:'.$correlationId;
    }
}
