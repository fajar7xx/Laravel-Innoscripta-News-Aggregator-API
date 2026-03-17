<?php

use App\Models\Article;
use App\Models\Category;
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

test('returns categories with an article on the index', function () {
    Category::factory()->count(2)->create();
    Article::factory()->withCategories(2)->create();

    $response = $this->getJson('/api/v1/articles')->assertSuccessful();

    expect($response->json('data.0.categories'))->toHaveCount(2);
});

test('returns categories with an article on show', function () {
    Category::factory()->count(3)->create();
    $article = Article::factory()->withCategories(3)->create();

    $response = $this->getJson("/api/v1/articles/{$article->id}")->assertSuccessful();

    expect($response->json('data.categories'))->toHaveCount(3);
});

test('returns an empty categories array for an article with no categories', function () {
    $article = Article::factory()->create();

    $response = $this->getJson("/api/v1/articles/{$article->id}")->assertSuccessful();

    expect($response->json('data.categories'))->toBeEmpty();
});

test('returns correct category fields with an article', function () {
    $category = Category::factory()->create();
    $article = Article::factory()->withCategories(1)->create();

    $response = $this->getJson("/api/v1/articles/{$article->id}")->assertSuccessful();

    expect($response->json('data.categories.0'))
        ->toMatchArray(['id' => $category->id, 'name' => $category->name, 'slug' => $category->slug]);
});
