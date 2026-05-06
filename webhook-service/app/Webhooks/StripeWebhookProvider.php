<?php

namespace App\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StripeWebhookProvider implements WebhookProviderContract
{
    public static function slug(): string
    {
        return 'stripe';
    }

    public function validate(Request $request): bool
    {
        $header = $request->header('Stripe-Signature');
        if (! is_string($header) || $header === '') {
            return false;
        }

        $parsed = [];
        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $kv = explode('=', $part, 2);
            if (count($kv) !== 2) {
                continue;
            }
            [$k, $v] = $kv;
            $parsed[$k][] = $v;
        }

        $t = isset($parsed['t'][0]) ? (string) $parsed['t'][0] : '';
        $v1s = $parsed['v1'] ?? [];
        if ($t === '' || $v1s === []) {
            return false;
        }

        $skew = config('lab_events.replay_window_seconds');
        if (! ctype_digit($t)) {
            return false;
        }
        $ts = (int) $t;
        if (abs(time() - $ts) > $skew) {
            return false;
        }

        $signedPayload = $t.'.'.$this->raw($request);
        $secret = $this->signingSecret();
        $mac = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($v1s as $sig) {
            if (hash_equals($mac, (string) $sig)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{type: string, payload: array<string, mixed>, metadata: array<string, mixed>}|null
     */
    public function buildEnvelope(Request $request): ?array
    {
        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($this->raw($request), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        $type = (string) ($data['type'] ?? 'unknown');

        return match ($type) {
            'checkout.session.completed' => [
                'type' => 'ORDER_CONFIRMED',
                'payload' => [
                    'order_id' => (string) data_get($data, 'data.object.id', ''),
                    'user_email' => data_get($data, 'data.object.customer_email'),
                ],
                'metadata' => $this->meta($request, $type),
            ],
            'customer.subscription.created' => [
                'type' => 'USER_REGISTERED',
                'payload' => [
                    'user_id' => (string) data_get($data, 'data.object.customer', ''),
                    'email' => data_get($data, 'data.object.customer_email'),
                ],
                'metadata' => $this->meta($request, $type),
            ],
            default => [
                'type' => 'PASSWORD_RESET',
                'payload' => [
                    'user_id' => 'stripe-hook',
                    'email' => $type.'@stripe.local',
                ],
                'metadata' => $this->meta($request, $type),
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function meta(Request $request, string $stripeType): array
    {
        return [
            'correlation_id' => $request->header('X-Correlation-Id')
                ?: (string) Str::uuid(),
            'timestamp' => now()->toIso8601String(),
            'source' => 'stripe',
            'stripe_type' => $stripeType,
        ];
    }

    protected function signingSecret(): string
    {
        $env = (string) config('webhooks.secrets.stripe');

        if (str_starts_with($env, 'whsec_')) {
            $decoded = base64_decode(substr($env, 6), true);

            return $decoded !== false ? $decoded : $env;
        }

        return $env;
    }

    protected function raw(Request $request): string
    {
        return (string) $request->attributes->get('raw_body', '');
    }
}
