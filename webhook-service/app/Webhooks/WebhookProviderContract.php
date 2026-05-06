<?php

namespace App\Webhooks;

use Illuminate\Http\Request;

interface WebhookProviderContract
{
    public static function slug(): string;

    public function validate(Request $request): bool;

    /**
     * @return array{type: string, payload: array<string, mixed>, metadata: array<string, mixed>}|null
     */
    public function buildEnvelope(Request $request): ?array;
}
