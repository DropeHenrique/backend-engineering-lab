<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\OpenApi(
    openapi: '3.0.0',
    info: new OA\Info(version: '1.0.0', title: 'notification-service'),
    servers: [
        new OA\Server(url: 'http://localhost:8081', description: 'Compose (host)'),
        new OA\Server(url: '/', description: 'Mesmo host do servidor atual'),
    ],
)]
final readonly class OpenApiInfo {}
