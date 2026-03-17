<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ErrorResponse',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'No query results for model [App\\Models\\Article] 999'),
    ],
    type: 'object'
)]
class ErrorResponseSchema {}
