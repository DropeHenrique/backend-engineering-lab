<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\RabbitMq\DomainEventPublisher;
use App\Repositories\DomainEventRepository;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class EventController extends Controller
{
    #[OA\Post(
        path: '/api/events',
        operationId: 'postDomainEvent',
        description: 'Aceita evento de domínio, persiste e publica no RabbitMQ. Idempotência por `correlation_id` (Redis + unique no DB).',
        tags: ['Events'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type', 'payload', 'metadata'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['ORDER_CONFIRMED', 'USER_REGISTERED', 'PASSWORD_RESET']),
                    new OA\Property(property: 'payload', type: 'object', additionalProperties: true),
                    new OA\Property(
                        property: 'metadata',
                        type: 'object',
                        required: ['correlation_id', 'timestamp'],
                        properties: [
                            new OA\Property(property: 'correlation_id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                        ]
                    ),
                ],
                example: [
                    'type' => 'ORDER_CONFIRMED',
                    'payload' => ['order_id' => 'ord_123', 'user_email' => 'buyer@example.com'],
                    'metadata' => [
                        'correlation_id' => '550e8400-e29b-41d4-a716-446655440000',
                        'timestamp' => '2026-05-06T12:00:00+00:00',
                    ],
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 202,
                description: 'Enfileirado',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'queued'),
                        new OA\Property(property: 'correlation_id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'type', type: 'string'),
                    ]
                )
            ),
            new OA\Response(response: 409, description: 'correlation_id duplicado para o mesmo tipo'),
            new OA\Response(response: 422, description: 'Payload inválido ou regras por tipo'),
        ]
    )]
    public function store(
        StoreEventRequest $request,
        DomainEventRepository $events,
        DomainEventPublisher $publisher
    ): JsonResponse {
        $data = $request->validated();

        try {
            $event = $events->createIncoming(
                $data['metadata']['correlation_id'],
                $data['type'],
                $data['payload'],
                $data['metadata'],
            );
        } catch (QueryException $e) {
            $msg = strtolower($e->getMessage());

            return response()->json([
                'error' => 'Duplicate event',
                'message' => 'correlation_id already registered for this type',
                'duplicate' => str_contains($msg, 'unique') || str_contains($msg, 'unique constraint'),
            ], 409);
        }

        $publisher->publish([
            'type' => $data['type'],
            'payload' => $data['payload'],
            'metadata' => $data['metadata'],
        ]);

        $events->markQueued($event);

        return response()->json([
            'status' => 'queued',
            'correlation_id' => $data['metadata']['correlation_id'],
            'type' => $data['type'],
        ], 202);
    }
}
