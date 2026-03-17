<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    /**
     * Return all categories.
     *
     * @return AnonymousResourceCollection<Collection<CategoryResource>>
     */
    #[
        OA\Get(
            path: '/api/v1/categories',
            operationId: 'listCategories',
            summary: 'List categories',
            tags: ['Categories'],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Category list',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(
                                property: 'data',
                                type: 'array',
                                items: new OA\Items(
                                    ref: '#/components/schemas/CategoryResource',
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
        return CategoryResource::collection(Category::get());
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
    public function store(StoreCategoryRequest $request)
    {
        //
    }

    /**
     * Return a single category.
     */
    #[
        OA\Get(
            path: '/api/v1/categories/{category}',
            operationId: 'showCategory',
            summary: 'Get category detail',
            tags: ['Categories'],
            parameters: [
                new OA\PathParameter(
                    name: 'category',
                    description: 'Category ID',
                    required: true,
                    schema: new OA\Schema(type: 'integer'),
                ),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Category detail',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(
                                property: 'data',
                                ref: '#/components/schemas/CategoryResource',
                            ),
                        ],
                        type: 'object',
                    ),
                ),
                new OA\Response(
                    response: 404,
                    description: 'Category not found',
                    content: new OA\JsonContent(
                        ref: '#/components/schemas/ErrorResponse',
                    ),
                ),
            ],
        ),
    ]
    public function show(Category $category): JsonResource
    {
        return new CategoryResource($category);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Category $category)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        //
    }
}
