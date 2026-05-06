<?php

namespace App\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Simulação de provider Hotmart: HMAC-SHA256 do raw body em X-Signature (hex).
 *
 * Produção deve seguir especificação oficial da Hotmart; aqui apenas vetores de teste determinísticos.
 */
class HotmartWebhookProvider implements WebhookProviderContract
{
    public static function slug(): string
    {
        return 'hotmart';
    }

    public function validate(Request $request): bool
    {
        $received = $request->header('X-Signature');
        if ($received === null || $received === '') {
            return false;
        }

        $secret = (string) config('webhooks.secrets.hotmart');
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

        $purchaseId = data_get($data, 'purchase_id', data_get($data, 'transaction', 'purchase_unknown'));
        $email = data_get($data, 'buyer.email', 'buyer@hotmart.lab');

        return [
            'type' => 'ORDER_CONFIRMED',
            'payload' => [
                'order_id' => (string) $purchaseId,
                'user_email' => $email,
            ],
            'metadata' => [
                'correlation_id' => $request->header('X-Correlation-Id')
                    ?: (string) Str::uuid(),
                'timestamp' => now()->toIso8601String(),
                'source' => 'hotmart',
            ],
        ];
    }

    protected function raw(Request $request): string
    {
        return (string) $request->attributes->get('raw_body', '');
    }
}
