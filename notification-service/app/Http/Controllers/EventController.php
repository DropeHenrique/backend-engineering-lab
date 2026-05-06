<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\RabbitMq\DomainEventPublisher;
use App\Repositories\DomainEventRepository;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

class EventController extends Controller
{
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
