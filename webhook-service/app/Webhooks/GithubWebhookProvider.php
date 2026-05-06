<?php

namespace App\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GithubWebhookProvider implements WebhookProviderContract
{
    public static function slug(): string
    {
        return 'github';
    }

    public function validate(Request $request): bool
    {
        $received = $request->header('X-Hub-Signature-256');
        if (! is_string($received) || ! str_starts_with($received, 'sha256=')) {
            return false;
        }

        $digest = substr($received, 7);
        $secret = (string) config('webhooks.secrets.github');
        $expected = hash_hmac('sha256', $this->raw($request), $secret);

        return hash_equals($expected, $digest);
    }

    public function buildEnvelope(Request $request): ?array
    {
        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($this->raw($request), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        $external = strtolower((string) $request->header('X-GitHub-Event', 'unknown'));
        [$type, $payload] = match ($external) {
            'ping' => ['USER_REGISTERED', [
                'user_id' => 'github-hook',
                'email' => 'ping@'.$external.'.local',
                'hook_id' => $data['hook_id'] ?? null,
            ]],
            'push' => ['ORDER_CONFIRMED', [
                'order_id' => 'push:'.substr(sha1($this->raw($request)), 0, 12),
                'user_email' => null,
                'repository' => data_get($data, 'repository.full_name'),
                'commits' => is_array(data_get($data, 'commits')) ? count(data_get($data, 'commits', [])) : 0,
            ]],
            default => ['PASSWORD_RESET', [
                'user_id' => 'github-event:'.$external,
                'email' => 'noreply@github.local',
            ]],
        };

        return [
            'type' => $type,
            'payload' => $payload,
            'metadata' => $this->meta($request),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function meta(Request $request): array
    {
        return [
            'correlation_id' => $request->header('X-Correlation-Id')
                ?: (string) Str::uuid(),
            'timestamp' => now()->toIso8601String(),
            'source' => 'github',
            'github_event' => $request->header('X-GitHub-Event'),
        ];
    }

    protected function raw(Request $request): string
    {
        return (string) $request->attributes->get('raw_body', '');
    }
}
