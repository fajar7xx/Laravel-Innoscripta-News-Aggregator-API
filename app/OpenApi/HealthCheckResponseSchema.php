<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'HealthCheckResponse',
    required: ['status', 'service', 'version', 'timestamp'],
    properties: [
        new OA\Property(property: 'status', type: 'string', example: 'OK'),
        new OA\Property(property: 'service', type: 'string', example: 'news-aggregator-backend-api'),
        new OA\Property(property: 'version', type: 'string', example: '1.0.0'),
        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time', example: '2026-03-17T00:00:00+00:00'),
    ],
    type: 'object'
)]
class HealthCheckResponseSchema {}
