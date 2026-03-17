<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListArticlesRequest;
use App\Http\Requests\StoreArticleRequest;
use App\Http\Requests\UpdateArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ArticleController extends Controller
{
    public function index(ListArticlesRequest $request): AnonymousResourceCollection
    {
        $query = Article::with(['source', 'categories']);

        if ($q = $request->string('q')->trim()->value()) {
            $query->whereFullText(['title', 'description', 'content'], $q);
        }

        if ($source = $request->string('source')->trim()->value()) {
            $query->whereHas('source', fn ($q) => $q->where('slug', $source));
        }

        if ($category = $request->string('category')->trim()->value()) {
            $query->whereHas('categories', fn ($q) => $q->where('slug', $category));
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
