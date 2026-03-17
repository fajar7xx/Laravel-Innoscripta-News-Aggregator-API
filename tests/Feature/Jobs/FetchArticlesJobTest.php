<?php

use App\Jobs\FetchArticlesJob;
use App\Models\Article;
use App\Models\Source;
use App\Services\Adapters\GuardianAdapter;
use App\Services\Adapters\NewsApiAdapter;
use App\Services\Adapters\NYTimesAdapter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

function rawArticle(array $overrides = []): array
{
    return array_merge([
        'external_id' => 'ext-job-001',
        'title' => 'Job Test Article',
        'description' => 'Description',
        'content' => 'Content',
        'author' => 'Author',
        'url' => 'https://example.com/job-article',
        'image_url' => null,
        'published_at' => '2026-01-01 00:00:00',
        'categories' => [],
    ], $overrides);
}

test('job has correct retry configuration', function () {
    $job = new FetchArticlesJob('newsapi');

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([30, 120, 600])
        ->and($job->timeout)->toBe(120);
});

test('job saves articles from the adapter to the database', function () {
    $source = Source::factory()->create(['slug' => 'newsapi']);

    $this->mock(NewsApiAdapter::class, function ($mock) {
        $mock->shouldReceive('fetch')->once()->andReturn([['raw' => 'data']]);
        $mock->shouldReceive('normalize')->once()->andReturn(rawArticle());
    });

    (new FetchArticlesJob('newsapi'))->handle(app(\App\Services\NewsAggregationService::class));

    expect(Article::count())->toBe(1);
});

test('job updates last_fetched_at on the source after processing', function () {
    $source = Source::factory()->create(['slug' => 'guardian', 'last_fetched_at' => null]);

    $this->mock(GuardianAdapter::class, function ($mock) {
        $mock->shouldReceive('fetch')->once()->andReturn([]);
    });

    (new FetchArticlesJob('guardian'))->handle(app(\App\Services\NewsAggregationService::class));

    expect($source->fresh()->last_fetched_at)->not->toBeNull();
});

test('job continues processing remaining articles when one fails to save', function () {
    $source = Source::factory()->create(['slug' => 'nytimes']);

    $this->mock(NYTimesAdapter::class, function ($mock) {
        $mock->shouldReceive('fetch')->once()->andReturn([['raw' => 'bad'], ['raw' => 'good']]);
        $mock->shouldReceive('normalize')
            ->twice()
            ->andReturn([], rawArticle());
    });

    (new FetchArticlesJob('nytimes'))->handle(app(\App\Services\NewsAggregationService::class));

    expect(Article::count())->toBe(1);
});

test('job throws when source slug does not exist in the database', function () {
    expect(fn () => (new FetchArticlesJob('unknown-source'))
        ->handle(app(\App\Services\NewsAggregationService::class))
    )->toThrow(ModelNotFoundException::class);
});

test('job throws when source is inactive', function () {
    Source::factory()->inactive()->create(['slug' => 'newsapi']);

    expect(fn () => (new FetchArticlesJob('newsapi'))
        ->handle(app(\App\Services\NewsAggregationService::class))
    )->toThrow(ModelNotFoundException::class);
});

test('failed method logs a critical message', function () {
    Log::shouldReceive('critical')->once()->with(
        'FetchArticlesJob exhausted all retries',
        Mockery::subset(['source' => 'guardian'])
    );

    $job = new FetchArticlesJob('guardian');
    $job->failed(new RuntimeException('Connection refused'));
});

test('job can be dispatched to the queue', function () {
    Queue::fake();

    FetchArticlesJob::dispatch('newsapi');

    Queue::assertPushed(FetchArticlesJob::class, function ($job) {
        return $job->sourceSlug === 'newsapi';
    });
});
