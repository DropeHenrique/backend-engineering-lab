<?php

namespace App\Webhooks;

use Illuminate\Http\Request;

/**
 * Espera JSON no formato do notification-service ({type,payload,metadata}).
 */
class GenericWebhookProvider implements WebhookProviderContract
{
    public static function slug(): string
    {
        return 'generic';
    }

    public function validate(Request $request): bool
    {
        $secret = (string) config('webhooks.secrets.generic');
        $received = $request->header('X-Signature');
        if ($received === null || $received === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $this->raw($request), $secret);

        return hash_equals($expected, $received);
    }

    public function buildEnvelope(Request $request): ?array
    {
        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($this->raw($request), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (! isset($data['type'], $data['payload'], $data['metadata']) || ! is_array($data['payload']) || ! is_array($data['metadata'])) {
            return null;
        }

        return [
            'type' => (string) $data['type'],
            'payload' => $data['payload'],
            'metadata' => $data['metadata'],
        ];
    }

    protected function raw(Request $request): string
    {
        return (string) $request->attributes->get('raw_body', '');
    }
}
