<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[
    OA\OpenApi(
        info: new OA\Info(
            version: '1.0.0',
            title: 'News Aggregator Backend API',
            description: 'REST API for aggregating and querying normalized news articles from multiple external providers.',
        ),
        servers: [new OA\Server(url: '/', description: 'Application base URL')],
        tags: [
            new OA\Tag(
                name: 'Health',
                description: 'Application health endpoints',
            ),
            new OA\Tag(
                name: 'Articles',
                description: 'Read-only article endpoints',
            ),
            new OA\Tag(
                name: 'Sources',
                description: 'Read-only source endpoints',
            ),
            new OA\Tag(
                name: 'Categories',
                description: 'Read-only category endpoints',
            ),
        ],
    ),
]
class OpenApiSpec {}
