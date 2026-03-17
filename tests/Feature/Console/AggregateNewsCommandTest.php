<?php

use App\Jobs\FetchArticlesJob;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('command dispatches a job for each active source', function () {
    Queue::fake();

    $sources = Source::factory()->count(3)->create();

    $this->artisan('news:aggregate')->assertSuccessful();

    Queue::assertPushed(FetchArticlesJob::class, 3);

    $sources->each(function ($source) {
        Queue::assertPushed(FetchArticlesJob::class, fn ($job) => $job->sourceSlug === $source->slug);
    });
});

test('command does not dispatch jobs for inactive sources', function () {
    Queue::fake();

    Source::factory()->create(['slug' => 'active-source']);
    Source::factory()->inactive()->create(['slug' => 'inactive-source']);

    $this->artisan('news:aggregate')->assertSuccessful();

    Queue::assertPushed(FetchArticlesJob::class, 1);
    Queue::assertPushed(FetchArticlesJob::class, fn ($job) => $job->sourceSlug === 'active-source');
    Queue::assertNotPushed(FetchArticlesJob::class, fn ($job) => $job->sourceSlug === 'inactive-source');
});

test('command outputs warning and exits successfully when no active sources exist', function () {
    Queue::fake();

    $this->artisan('news:aggregate')
        ->assertSuccessful()
        ->expectsOutput('No active sources found.');

    Queue::assertNothingPushed();
});

test('command outputs the dispatched source names', function () {
    Queue::fake();

    $source = Source::factory()->create(['name' => 'The Guardian', 'slug' => 'guardian']);

    $this->artisan('news:aggregate')
        ->assertSuccessful()
        ->expectsOutput("Dispatched job for: {$source->name}");
});
