<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSourceRequest;
use App\Http\Requests\UpdateSourceRequest;
use App\Http\Resources\SourceResource;
use App\Models\Source;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

class SourceController extends Controller
{
    /**
     * Return all news sources.
     *
     * @return AnonymousResourceCollection<Collection<SourceResource>>
     */
    #[
        OA\Get(
            path: '/api/v1/sources',
            operationId: 'listSources',
            summary: 'List sources',
            tags: ['Sources'],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Source list',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(
                                property: 'data',
                                type: 'array',
                                items: new OA\Items(
                                    ref: '#/components/schemas/SourceResource',
                                ),
                            ),
                        ],
                        type: 'object',
                    ),
                ),
            ],
        ),
    ]
    public function index(): AnonymousResourceCollection
    {
        return SourceResource::collection(Source::get());
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSourceRequest $request)
    {
        //
    }

    /**
     * Return a single news source.
     */
    #[
        OA\Get(
            path: '/api/v1/sources/{source}',
            operationId: 'showSource',
            summary: 'Get source detail',
            tags: ['Sources'],
            parameters: [
                new OA\PathParameter(
                    name: 'source',
                    description: 'Source ID',
                    required: true,
                    schema: new OA\Schema(type: 'integer'),
                ),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Source detail',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(
                                property: 'data',
                                ref: '#/components/schemas/SourceResource',
                            ),
                        ],
                        type: 'object',
                    ),
                ),
                new OA\Response(
                    response: 404,
                    description: 'Source not found',
                    content: new OA\JsonContent(
                        ref: '#/components/schemas/ErrorResponse',
                    ),
                ),
            ],
        ),
    ]
    public function show(Source $source): JsonResource
    {
        return new SourceResource($source);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Source $source)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSourceRequest $request, Source $source)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Source $source)
    {
        //
    }
}
