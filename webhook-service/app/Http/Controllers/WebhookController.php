<?php

namespace App\Http\Controllers;

use App\Models\WebhookEvent;
use App\RabbitMq\LabEventPublisher;
use App\Webhooks\ReplayGuard;
use App\Webhooks\WebhookProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class WebhookController extends Controller
{
    public function logs(): JsonResponse
    {
        $rows = WebhookEvent::query()
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function receive(
        Request $request,
        string $provider,
        ReplayGuard $replay,
        WebhookProviderRegistry $registry,
        LabEventPublisher $publisher
    ): JsonResponse {
        $slug = strtolower($provider);

        try {
            $impl = $registry->get($slug);
        } catch (Throwable) {
            return response()->json(['error' => 'Unknown provider'], 404);
        }

        $replay->assert($request, $slug);

        if (! $impl->validate($request)) {
            WebhookEvent::create([
                'provider' => $slug,
                'lab_event_type' => null,
                'correlation_id' => null,
                'http_status' => 401,
                'status' => 'rejected',
                'notes' => 'HMAC validation failed',
            ]);

            return response()->json([
                'error' => 'Invalid signature',
            ], 401);
        }

        $envelope = $impl->buildEnvelope($request);
        if ($envelope === null) {
            WebhookEvent::create([
                'provider' => $slug,
                'lab_event_type' => null,
                'correlation_id' => null,
                'http_status' => 400,
                'status' => 'rejected',
                'notes' => 'Invalid envelope',
            ]);

            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $envelope['metadata']['correlation_id'] = isset($envelope['metadata']['correlation_id'])
            && is_string($envelope['metadata']['correlation_id'])
            && Str::isUuid($envelope['metadata']['correlation_id'])
                ? $envelope['metadata']['correlation_id']
                : (string) Str::uuid();

        $envelope['metadata']['timestamp'] = now()->toIso8601String();

        $type = $envelope['type'];
        if (! in_array($type, config('lab_events.allowed_types'), true)) {
            WebhookEvent::create([
                'provider' => $slug,
                'lab_event_type' => $type,
                'correlation_id' => $envelope['metadata']['correlation_id'],
                'http_status' => 422,
                'status' => 'rejected',
                'notes' => 'Unknown lab event type',
            ]);

            return response()->json(['error' => 'Unsupported mapped type'], 422);
        }

        try {
            $publisher->publish($envelope);
        } catch (Throwable $e) {
            Log::error('webhook.publish_failed', [
                'component' => 'webhook',
                'status' => 'publish_failed',
                'provider' => $slug,
                'error' => $e->getMessage(),
            ]);

            WebhookEvent::create([
                'provider' => $slug,
                'lab_event_type' => $type,
                'correlation_id' => $envelope['metadata']['correlation_id'],
                'http_status' => 502,
                'status' => 'failed',
                'notes' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to enqueue',
            ], 502);
        }

        Log::info('webhook.accepted', [
            'component' => 'webhook',
            'status' => 'accepted',
            'provider' => $slug,
            'event_type' => $type,
            'correlation_id' => $envelope['metadata']['correlation_id'],
        ]);

        $event = WebhookEvent::create([
            'provider' => $slug,
            'lab_event_type' => $type,
            'correlation_id' => $envelope['metadata']['correlation_id'],
            'http_status' => 200,
            'status' => 'accepted',
            'response_body' => ['enqueued' => true],
        ]);

        return response()->json([
            'status' => 'accepted',
            'id' => $event->id,
            'correlation_id' => $envelope['metadata']['correlation_id'],
        ]);
    }
}
