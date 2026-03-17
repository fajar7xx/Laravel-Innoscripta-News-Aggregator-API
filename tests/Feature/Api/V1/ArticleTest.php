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

// --- Full-Text Search (#20) ---

test('accepts a search query and returns a successful response', function () {
    Article::factory()->count(3)->create();

    $this->getJson('/api/v1/articles?q=technology')
        ->assertSuccessful()
        ->assertJsonStructure(['data', 'meta']);
})->skip(
    fn () => config('database.default') === 'sqlite',
    'whereFullText is not supported in SQLite; runs on MariaDB in production.'
);

test('returns all articles when q param is empty', function () {
    Article::factory()->count(5)->create();

    $withoutQ = $this->getJson('/api/v1/articles')->assertSuccessful();
    $withEmptyQ = $this->getJson('/api/v1/articles?q=')->assertSuccessful();

    expect($withoutQ->json('meta.total'))->toBe($withEmptyQ->json('meta.total'));
});

test('returns all articles when q is only whitespace', function () {
    Article::factory()->count(3)->create();

    $withoutQ = $this->getJson('/api/v1/articles')->assertSuccessful();
    // URL-encode spaces (%20) to form a valid URI
    $withWhitespace = $this->getJson('/api/v1/articles?q=%20%20%20')->assertSuccessful();

    expect($withoutQ->json('meta.total'))->toBe($withWhitespace->json('meta.total'));
})->skip(
    fn () => config('database.default') === 'sqlite',
    'whereFullText is not supported in SQLite; runs on MariaDB in production.'
);

test('returns 422 when q exceeds maximum length', function () {
    $this->getJson('/api/v1/articles?q='.str_repeat('a', 256))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['q']);
});

// --- Filtering & Sorting (#21) ---

test('filters articles by source slug', function () {
    $source = Source::factory()->create(['slug' => 'the-guardian']);
    $otherSource = Source::factory()->create();
    Article::factory()->count(2)->create(['source_id' => $source->id]);
    Article::factory()->count(3)->create(['source_id' => $otherSource->id]);

    $response = $this->getJson('/api/v1/articles?source=the-guardian')->assertSuccessful();

    expect($response->json('meta.total'))->toBe(2);
});

test('filters articles by category slug', function () {
    $category = Category::factory()->create(['slug' => 'technology']);
    $articles = Article::factory()->count(2)->create();
    $articles->each(fn ($a) => $a->categories()->attach($category));
    Article::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/articles?category=technology')->assertSuccessful();

    expect($response->json('meta.total'))->toBe(2);
});

test('filters articles by author', function () {
    Article::factory()->count(2)->create(['author' => 'John Doe']);
    Article::factory()->count(3)->create(['author' => 'Jane Smith']);

    $response = $this->getJson('/api/v1/articles?author=John+Doe')->assertSuccessful();

    expect($response->json('meta.total'))->toBe(2);
});

test('filters articles by from date', function () {
    Article::factory()->count(2)->create(['published_at' => '2024-06-15']);
    Article::factory()->count(2)->create(['published_at' => '2024-01-01']);

    $response = $this->getJson('/api/v1/articles?from=2024-06-01')->assertSuccessful();

    expect($response->json('meta.total'))->toBe(2);
});

test('filters articles by to date', function () {
    Article::factory()->count(2)->create(['published_at' => '2024-01-01']);
    Article::factory()->count(2)->create(['published_at' => '2024-06-15']);

    $response = $this->getJson('/api/v1/articles?to=2024-03-01')->assertSuccessful();

    expect($response->json('meta.total'))->toBe(2);
});

test('filters articles within a date range', function () {
    Article::factory()->count(3)->create(['published_at' => '2024-03-15']);
    Article::factory()->count(2)->create(['published_at' => '2024-01-01']);
    Article::factory()->count(2)->create(['published_at' => '2024-12-01']);

    $response = $this->getJson('/api/v1/articles?from=2024-02-01&to=2024-06-01')->assertSuccessful();

    expect($response->json('meta.total'))->toBe(3);
});

test('combines filters with AND logic', function () {
    $source = Source::factory()->create(['slug' => 'newsapi']);
    $otherSource = Source::factory()->create();
    $category = Category::factory()->create(['slug' => 'sports']);

    $matching = Article::factory()->create(['source_id' => $source->id]);
    $matching->categories()->attach($category);

    Article::factory()->create(['source_id' => $source->id]);

    $other = Article::factory()->create(['source_id' => $otherSource->id]);
    $other->categories()->attach($category);

    $response = $this->getJson('/api/v1/articles?source=newsapi&category=sports')->assertSuccessful();

    expect($response->json('meta.total'))->toBe(1);
});

test('sorts articles by published_at descending by default', function () {
    Article::factory()->create(['published_at' => '2024-01-01']);
    Article::factory()->create(['published_at' => '2024-06-01']);
    Article::factory()->create(['published_at' => '2024-12-01']);

    $response = $this->getJson('/api/v1/articles')->assertSuccessful();

    $dates = collect($response->json('data'))->pluck('published_at');

    expect($dates[0])->toBeGreaterThan($dates[1])
        ->and($dates[1])->toBeGreaterThan($dates[2]);
});

test('sorts articles ascending when sort_order=asc', function () {
    Article::factory()->create(['published_at' => '2024-01-01']);
    Article::factory()->create(['published_at' => '2024-06-01']);
    Article::factory()->create(['published_at' => '2024-12-01']);

    $response = $this->getJson('/api/v1/articles?sort_by=published_at&sort_order=asc')->assertSuccessful();

    $dates = collect($response->json('data'))->pluck('published_at');

    expect($dates[0])->toBeLessThan($dates[1])
        ->and($dates[1])->toBeLessThan($dates[2]);
});

test('returns 422 when sort_by is an invalid column', function () {
    $this->getJson('/api/v1/articles?sort_by=invalid_column')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sort_by']);
});

test('returns 422 when sort_order is invalid', function () {
    $this->getJson('/api/v1/articles?sort_order=random')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sort_order']);
});

test('returns 422 when from is not a valid date', function () {
    $this->getJson('/api/v1/articles?from=not-a-date')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['from']);
});

test('returns 422 when to is not a valid date', function () {
    $this->getJson('/api/v1/articles?to=not-a-date')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to']);
});
