<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListArticlesRequest;
use App\Http\Requests\StoreArticleRequest;
use App\Http\Requests\UpdateArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class ArticleController extends Controller
{
    #[
        OA\Get(
            path: '/api/v1/articles',
            operationId: 'listArticles',
            summary: 'List articles',
            description: 'Returns a paginated list of articles with optional search, filters, and sorting.',
            tags: ['Articles'],
            parameters: [
                new OA\QueryParameter(
                    name: 'q',
                    description: 'Full-text search keyword',
                    schema: new OA\Schema(type: 'string'),
                ),
                new OA\QueryParameter(
                    name: 'source',
                    description: 'Filter by source slug',
                    schema: new OA\Schema(type: 'string'),
                ),
                new OA\QueryParameter(
                    name: 'category',
                    description: 'Filter by category slug',
                    schema: new OA\Schema(type: 'string'),
                ),
                new OA\QueryParameter(
                    name: 'author',
                    description: 'Filter by exact author name',
                    schema: new OA\Schema(type: 'string'),
                ),
                new OA\QueryParameter(
                    name: 'from',
                    description: 'Published date from',
                    schema: new OA\Schema(type: 'string', format: 'date'),
                ),
                new OA\QueryParameter(
                    name: 'to',
                    description: 'Published date to',
                    schema: new OA\Schema(type: 'string', format: 'date'),
                ),
                new OA\QueryParameter(
                    name: 'sort_by',
                    description: 'Sort column',
                    schema: new OA\Schema(
                        type: 'string',
                        enum: ['published_at', 'title', 'created_at'],
                    ),
                ),
                new OA\QueryParameter(
                    name: 'sort_order',
                    description: 'Sort direction',
                    schema: new OA\Schema(
                        type: 'string',
                        enum: ['asc', 'desc'],
                    ),
                ),
                new OA\QueryParameter(
                    name: 'page',
                    description: 'Pagination page',
                    schema: new OA\Schema(type: 'integer'),
                ),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Paginated article list',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(
                                property: 'data',
                                type: 'array',
                                items: new OA\Items(
                                    ref: '#/components/schemas/ArticleResource',
                                ),
                            ),
                            new OA\Property(property: 'links', type: 'object'),
                            new OA\Property(property: 'meta', type: 'object'),
                        ],
                        type: 'object',
                    ),
                ),
                new OA\Response(response: 422, description: 'Validation error'),
            ],
        ),
    ]
    public function index(
        ListArticlesRequest $request,
    ): AnonymousResourceCollection {
        $query = Article::with(['source', 'categories']);

        if ($q = $request->string('q')->trim()->value()) {
            $query->whereFullText(['title', 'description', 'content'], $q);
        }

        if ($source = $request->string('source')->trim()->value()) {
            $query->whereHas('source', fn ($q) => $q->where('slug', $source));
        }

        if ($category = $request->string('category')->trim()->value()) {
            $query->whereHas(
                'categories',
                fn ($q) => $q->where('slug', $category),
            );
        }

        if ($author = $request->string('author')->trim()->value()) {
            $query->where('author', $author);
        }

        if ($from = $request->input('from')) {
            $query->whereDate('published_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('published_at', '<=', $to);
        }

        $sortBy = $request->input('sort_by', 'published_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        return ArticleResource::collection($query->paginate());
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
    public function store(StoreArticleRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    #[
        OA\Get(
            path: '/api/v1/articles/{article}',
            operationId: 'showArticle',
            summary: 'Get article detail',
            tags: ['Articles'],
            parameters: [
                new OA\PathParameter(
                    name: 'article',
                    description: 'Article ID',
                    required: true,
                    schema: new OA\Schema(type: 'integer'),
                ),
            ],
            responses: [
                new OA\Response(
                    response: 200,
                    description: 'Article detail',
                    content: new OA\JsonContent(
                        properties: [
                            new OA\Property(
                                property: 'data',
                                ref: '#/components/schemas/ArticleResource',
                            ),
                        ],
                        type: 'object',
                    ),
                ),
                new OA\Response(
                    response: 404,
                    description: 'Article not found',
                    content: new OA\JsonContent(
                        ref: '#/components/schemas/ErrorResponse',
                    ),
                ),
            ],
        ),
    ]
    public function show(Article $article)
    {
        $article->load(['source', 'categories']);

        return new ArticleResource($article);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Article $article)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateArticleRequest $request, Article $article)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Article $article)
    {
        //
    }
}
