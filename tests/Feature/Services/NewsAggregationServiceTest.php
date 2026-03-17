<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\Source;
use App\Services\NewsAggregationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function articleData(array $overrides = []): array
{
    return array_merge([
        'external_id' => 'ext-001',
        'title' => 'Test Article Title',
        'description' => 'A description',
        'content' => 'Full content here',
        'author' => 'John Doe',
        'url' => 'https://example.com/article-001',
        'image_url' => 'https://example.com/image.jpg',
        'published_at' => '2026-01-01 00:00:00',
        'categories' => [],
    ], $overrides);
}

test('saveArticle creates a new article', function () {
    $source = Source::factory()->create();
    $service = app(NewsAggregationService::class);

    $service->saveArticle($source, articleData());

    expect(Article::count())->toBe(1)
        ->and(Article::first()->title)->toBe('Test Article Title')
        ->and(Article::first()->source_id)->toBe($source->id);
});

test('saveArticle updates an existing article instead of creating a duplicate', function () {
    $source = Source::factory()->create();
    $service = app(NewsAggregationService::class);

    $service->saveArticle($source, articleData(['title' => 'Original Title']));
    $service->saveArticle($source, articleData(['title' => 'Updated Title']));

    expect(Article::count())->toBe(1)
        ->and(Article::first()->title)->toBe('Updated Title');
});

test('saveArticle allows the same external_id for different sources', function () {
    [$sourceA, $sourceB] = Source::factory()->count(2)->create();
    $service = app(NewsAggregationService::class);

    $service->saveArticle($sourceA, articleData(['url' => 'https://example.com/a']));
    $service->saveArticle($sourceB, articleData(['url' => 'https://example.com/b']));

    expect(Article::count())->toBe(2);
});

test('saveArticle syncs categories to the article', function () {
    $source = Source::factory()->create();
    Category::factory()->create(['slug' => 'technology']);
    $service = app(NewsAggregationService::class);

    $service->saveArticle($source, articleData(['categories' => ['technology']]));

    $article = Article::first();
    expect($article->categories)->toHaveCount(1)
        ->and($article->categories->first()->slug)->toBe('technology');
});

test('saveArticle replaces categories on update', function () {
    $source = Source::factory()->create();
    Category::factory()->create(['slug' => 'technology']);
    Category::factory()->create(['slug' => 'science']);
    $service = app(NewsAggregationService::class);

    $service->saveArticle($source, articleData(['categories' => ['technology']]));
    $service->saveArticle($source, articleData(['categories' => ['science']]));

    $article = Article::first();
    expect($article->categories)->toHaveCount(1)
        ->and($article->categories->first()->slug)->toBe('science');
});

test('saveArticle ignores unknown category slugs', function () {
    $source = Source::factory()->create();
    $service = app(NewsAggregationService::class);

    $service->saveArticle($source, articleData(['categories' => ['nonexistent-category']]));

    expect(Article::count())->toBe(1)
        ->and(Article::first()->categories)->toBeEmpty();
});

test('saveArticle skips when data is empty', function () {
    $source = Source::factory()->create();
    $service = app(NewsAggregationService::class);

    $service->saveArticle($source, []);

    expect(Article::count())->toBe(0);
});

test('saveArticle skips when external_id is missing', function () {
    $source = Source::factory()->create();
    $service = app(NewsAggregationService::class);

    $service->saveArticle($source, articleData(['external_id' => '']));

    expect(Article::count())->toBe(0);
});

test('saveArticle skips when url is missing', function () {
    $source = Source::factory()->create();
    $service = app(NewsAggregationService::class);

    $service->saveArticle($source, articleData(['url' => '']));

    expect(Article::count())->toBe(0);
});
