<?php

use App\Models\Article;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

test('returns a paginated list of articles', function () {
    Article::factory()->count(3)->create();

    $this->getJson('/api/v1/articles')
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [['id', 'title', 'url', 'published_at']],
            'links',
            'meta',
        ]);
});

test('returns an empty list when no articles exist', function () {
    $this->getJson('/api/v1/articles')
        ->assertSuccessful()
        ->assertJson(['data' => []]);
});

test('eager loads the source relationship on the index', function () {
    $source = Source::factory()->create();
    Article::factory()->create(['source_id' => $source->id]);

    $response = $this->getJson('/api/v1/articles')->assertSuccessful();

    expect($response->json('data.0.source.id'))->toBe($source->id);
});

test('returns a single article', function () {
    $article = Article::factory()->create();

    $this->getJson("/api/v1/articles/{$article->id}")
        ->assertSuccessful()
        ->assertJsonFragment(['id' => $article->id, 'title' => $article->title]);
});

test('loads the source relationship on show', function () {
    $source = Source::factory()->create();
    $article = Article::factory()->create(['source_id' => $source->id]);

    $response = $this->getJson("/api/v1/articles/{$article->id}")->assertSuccessful();

    expect($response->json('data.source.id'))->toBe($source->id);
});

test('returns 404 for a non-existent article', function () {
    $this->getJson('/api/v1/articles/999')->assertNotFound();
});

test('returns 404 for a soft-deleted article', function () {
    $article = Article::factory()->create();
    $article->delete();

    $this->getJson("/api/v1/articles/{$article->id}")->assertNotFound();
});
