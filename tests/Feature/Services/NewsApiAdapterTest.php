<?php

use App\Services\Adapters\NewsApiAdapter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// ---------------------------------------------------------------------------
// normalize()
// ---------------------------------------------------------------------------

test('normalize returns mapped article for valid data', function () {
    $adapter = new NewsApiAdapter;

    $raw = [
        'url' => 'https://example.com/article-1',
        'title' => 'Test Article',
        'description' => 'A short description.',
        'content' => 'Full article content.',
        'author' => 'Jane Doe',
        'urlToImage' => 'https://example.com/image.jpg',
        'publishedAt' => '2024-01-15T10:00:00Z',
    ];

    $result = $adapter->normalize($raw);

    expect($result)
        ->toHaveKeys(['external_id', 'title', 'description', 'content', 'author', 'url', 'image_url', 'published_at', 'categories'])
        ->and($result['external_id'])->toBe(md5('https://example.com/article-1'))
        ->and($result['title'])->toBe('Test Article')
        ->and($result['url'])->toBe('https://example.com/article-1')
        ->and($result['image_url'])->toBe('https://example.com/image.jpg')
        ->and($result['categories'])->toBe(['world']);
});

test('normalize returns empty array when url is missing', function () {
    $adapter = new NewsApiAdapter;

    expect($adapter->normalize([]))->toBe([])
        ->and($adapter->normalize(['url' => '']))->toBe([])
        ->and($adapter->normalize(['title' => 'No URL here']))->toBe([]);
});

test('normalize uses Untitled when title is missing', function () {
    $adapter = new NewsApiAdapter;

    $result = $adapter->normalize(['url' => 'https://example.com/no-title']);

    expect($result['title'])->toBe('Untitled');
});

test('normalize sets nullable fields to null when absent', function () {
    $adapter = new NewsApiAdapter;

    $result = $adapter->normalize(['url' => 'https://example.com/minimal']);

    expect($result['description'])->toBeNull()
        ->and($result['content'])->toBeNull()
        ->and($result['author'])->toBeNull()
        ->and($result['image_url'])->toBeNull();
});

test('normalize falls back to current time when publishedAt is missing', function () {
    $adapter = new NewsApiAdapter;

    $before = now()->subSecond();
    $result = $adapter->normalize(['url' => 'https://example.com/no-date']);
    $after = now()->addSecond();

    $publishedAt = \Carbon\Carbon::parse($result['published_at']);

    expect($publishedAt->between($before, $after))->toBeTrue();
});

// ---------------------------------------------------------------------------
// fetch()
// ---------------------------------------------------------------------------

test('fetch returns articles on successful response', function () {
    Http::fake([
        '*' => Http::response([
            'status' => 'ok',
            'totalResults' => 2,
            'articles' => [
                ['title' => 'Article One', 'url' => 'https://example.com/1'],
                ['title' => 'Article Two', 'url' => 'https://example.com/2'],
            ],
        ], 200),
    ]);

    $adapter = new NewsApiAdapter;
    $articles = $adapter->fetch();

    expect($articles)->toHaveCount(2)
        ->and($articles[0]['title'])->toBe('Article One');
});

test('fetch returns empty array when articles key is absent', function () {
    Http::fake(['*' => Http::response(['status' => 'ok'], 200)]);

    $adapter = new NewsApiAdapter;

    expect($adapter->fetch())->toBe([]);
});

test('fetch returns empty array and logs error on http error', function () {
    Http::fake(['*' => Http::response(['message' => 'Too Many Requests'], 429)]);

    Log::shouldReceive('info')->once();
    Log::shouldReceive('error')->once()->with('NewsAPI HTTP error', \Mockery::type('array'));

    $adapter = new NewsApiAdapter;

    expect($adapter->fetch())->toBe([]);
});

test('fetch re-throws and logs on connection error', function () {
    Http::fake(function () {
        throw new ConnectionException('Could not connect');
    });

    Log::shouldReceive('info')->once();
    Log::shouldReceive('error')->once()->with('NewsAPI connection error', \Mockery::type('array'));

    $adapter = new NewsApiAdapter;

    expect(fn () => $adapter->fetch())->toThrow(ConnectionException::class);
});
