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
use OpenApi\Attributes as OA;
use Throwable;

class WebhookController extends Controller
{
    #[OA\Get(
        path: '/webhook/logs',
        operationId: 'webhookLogs',
        description: 'Últimos registros de auditoria (`webhook_events`), mais recentes primeiro.',
        tags: ['Auditoria'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(type: 'object')
                        ),
                    ]
                )
            ),
        ]
    )]
    public function logs(): JsonResponse
    {
        $rows = WebhookEvent::query()
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json(['data' => $rows]);
    }

    #[OA\Post(
        path: '/webhook/{provider}',
        operationId: 'webhookReceive',
        description: 'Recebe webhook por provider. Replay protection: `X-Timestamp` + `X-Nonce` (Redis). HMAC depende do provider (ex.: generic usa `X-Signature` = HMAC-SHA256 do corpo bruto).',
        tags: ['Webhooks'],
        parameters: [
            new OA\Parameter(
                name: 'provider',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', enum: ['generic', 'stripe', 'github', 'hotmart'])
            ),
            new OA\Parameter(name: 'X-Timestamp', in: 'header', required: true, schema: new OA\Schema(type: 'string', example: '1715000000')),
            new OA\Parameter(name: 'X-Nonce', in: 'header', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(
                name: 'X-Signature',
                in: 'header',
                required: false,
                description: 'Obrigatório para `generic`: HMAC-SHA256 do raw body com o segredo do lab.',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Para `generic`: JSON alinhado ao notification-service (`type`, `payload`, `metadata`).',
            content: new OA\JsonContent(
                required: ['type', 'payload', 'metadata'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', example: 'ORDER_CONFIRMED'),
                    new OA\Property(property: 'payload', type: 'object'),
                    new OA\Property(property: 'metadata', type: 'object'),
                ],
                example: [
                    'type' => 'USER_REGISTERED',
                    'payload' => ['user_id' => 'u1', 'email' => 'user@example.com'],
                    'metadata' => ['source' => 'demo'],
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Aceito e enfileirado',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'accepted'),
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'correlation_id', type: 'string', format: 'uuid'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Assinatura HMAC inválida'),
            new OA\Response(response: 409, description: 'Replay detectado'),
            new OA\Response(response: 404, description: 'Provider desconhecido'),
            new OA\Response(response: 422, description: 'Tipo mapeado não suportado / payload inválido'),
            new OA\Response(response: 502, description: 'Falha ao publicar no RabbitMQ'),
        ]
    )]
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
