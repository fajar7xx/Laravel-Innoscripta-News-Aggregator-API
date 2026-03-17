<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SourceResource',
    required: ['id', 'name', 'slug', 'is_active'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'The Guardian'),
        new OA\Property(property: 'slug', type: 'string', example: 'guardian'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'last_fetched_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
class SourceResourceSchema {}
