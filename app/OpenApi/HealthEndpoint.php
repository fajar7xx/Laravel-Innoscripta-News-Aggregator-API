<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class HealthEndpoint
{
    #[OA\Get(
        path: '/api/health',
        operationId: 'healthCheck',
        summary: 'Health check',
        tags: ['Health'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful health response',
                content: new OA\JsonContent(ref: '#/components/schemas/HealthCheckResponse')
            ),
        ]
    )]
    public function __invoke(): void {}
}
