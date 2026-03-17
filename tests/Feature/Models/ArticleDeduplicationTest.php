<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\Source;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows inserting an article with a unique source_id and external_id', function () {
    $article = Article::factory()->create();

    expect(Article::count())->toBe(1)
        ->and($article->exists)->toBeTrue();
});

it('prevents duplicate external_id within the same source', function () {
    $source = Source::factory()->create();

    Article::factory()->create([
        'source_id' => $source->id,
        'external_id' => 'ext-001',
    ]);

    expect(fn () => Article::factory()->create([
        'source_id' => $source->id,
        'external_id' => 'ext-001',
    ]))->toThrow(QueryException::class);
});

it('allows the same external_id across different sources', function () {
    [$sourceA, $sourceB] = Source::factory()->count(2)->create();

    Article::factory()->create(['source_id' => $sourceA->id, 'external_id' => 'ext-001']);
    Article::factory()->create(['source_id' => $sourceB->id, 'external_id' => 'ext-001']);

    expect(Article::count())->toBe(2);
});

it('prevents duplicate article URL', function () {
    $url = 'https://example.com/article/unique-slug';

    Article::factory()->create(['url' => $url]);

    expect(fn () => Article::factory()->create(['url' => $url]))
        ->toThrow(QueryException::class);
});

it('belongs to a source', function () {
    $source = Source::factory()->create();
    $article = Article::factory()->create(['source_id' => $source->id]);

    expect($article->source->id)->toBe($source->id);
});

it('can be associated with multiple categories', function () {
    $article = Article::factory()->create();
    $categories = Category::factory()->count(3)->create();

    $article->categories()->attach($categories->pluck('id'));

    expect($article->categories)->toHaveCount(3);
});

it('source has many articles', function () {
    $source = Source::factory()->create();
    Article::factory()->count(5)->create(['source_id' => $source->id]);

    expect($source->articles)->toHaveCount(5);
});
