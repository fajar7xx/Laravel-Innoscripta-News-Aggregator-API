<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Category;
use App\Models\Source;
use Illuminate\Support\Facades\Log;

class NewsAggregationService
{
    /**
     * Save a normalized article to the database with deduplication.
     *
     * Uses updateOrCreate keyed on (source_id, external_id) to prevent
     * duplicate articles across scheduler reruns and overlapping fetches.
     *
     * @param  array{
     *     external_id: string,
     *     title: string,
     *     description: string|null,
     *     content: string|null,
     *     author: string|null,
     *     url: string,
     *     image_url: string|null,
     *     published_at: string,
     *     categories: array<string>,
     * }  $data
     */
    public function saveArticle(Source $source, array $data): void
    {
        if (empty($data) || empty($data['external_id']) || empty($data['url'])) {
            return;
        }

        $article = Article::updateOrCreate(
            [
                'source_id' => $source->id,
                'external_id' => $data['external_id'],
            ],
            [
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'content' => $data['content'] ?? null,
                'author' => $data['author'] ?? null,
                'url' => $data['url'],
                'image_url' => $data['image_url'] ?? null,
                'published_at' => $data['published_at'],
                'fetched_at' => now(),
            ]
        );

        if (! empty($data['categories'])) {
            $categoryIds = Category::query()
                ->whereIn('slug', $data['categories'])
                ->pluck('id');

            $article->categories()->sync($categoryIds);
        }

        Log::debug('Article saved', [
            'source' => $source->slug,
            'external_id' => $data['external_id'],
            'url' => $data['url'],
        ]);
    }
}
