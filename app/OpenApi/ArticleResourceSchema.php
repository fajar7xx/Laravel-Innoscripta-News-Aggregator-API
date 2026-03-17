<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ArticleResource',
    required: ['id', 'source_id', 'external_id', 'title', 'url', 'published_at', 'fetched_at'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'source_id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'external_id', type: 'string', example: 'guardian-123'),
        new OA\Property(property: 'title', type: 'string', example: 'Example article'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Short summary'),
        new OA\Property(property: 'content', type: 'string', nullable: true, example: 'Normalized article content'),
        new OA\Property(property: 'author', type: 'string', nullable: true, example: 'Reporter Name'),
        new OA\Property(property: 'url', type: 'string', format: 'uri', example: 'https://example.com/article'),
        new OA\Property(property: 'image_url', type: 'string', format: 'uri', nullable: true, example: 'https://example.com/image.jpg'),
        new OA\Property(property: 'published_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'fetched_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'source', ref: '#/components/schemas/SourceResource'),
        new OA\Property(
            property: 'categories',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/CategoryResource')
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
class ArticleResourceSchema {}
