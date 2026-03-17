<?php

use App\Models\Category;
use App\Services\Adapters\GuardianAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

pest()->use(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// normalize()
// ---------------------------------------------------------------------------

test('normalize returns mapped article for valid data', function () {
    $adapter = new GuardianAdapter;

    $raw = [
        'id' => 'technology/2024/jan/15/ai-article-slug',
        'webUrl' => 'https://www.theguardian.com/technology/2024/jan/15/ai-article-slug',
        'apiUrl' => 'https://content.guardianapis.com/technology/2024/jan/15/ai-article-slug',
        'webTitle' => 'AI Article Title',
        'webPublicationDate' => '2024-01-15T10:00:00Z',
        'sectionId' => 'technology',
        'sectionName' => 'Technology',
        'fields' => [
            'trailText' => 'Short description of the article.',
            'bodyText' => 'Full article body content.',
            'byline' => 'Jane Doe',
            'thumbnail' => 'https://media.guim.co.uk/thumb.jpg',
        ],
    ];

    $result = $adapter->normalize($raw);

    expect($result)
        ->toHaveKeys(['external_id', 'title', 'description', 'content', 'author', 'url', 'image_url', 'published_at', 'categories'])
        ->and($result['external_id'])->toBe('technology/2024/jan/15/ai-article-slug')
        ->and($result['title'])->toBe('AI Article Title')
        ->and($result['description'])->toBe('Short description of the article.')
        ->and($result['content'])->toBe('Full article body content.')
        ->and($result['author'])->toBe('Jane Doe')
        ->and($result['url'])->toBe('https://www.theguardian.com/technology/2024/jan/15/ai-article-slug')
        ->and($result['image_url'])->toBe('https://media.guim.co.uk/thumb.jpg')
        ->and($result['categories'])->toBe(['technology']);
});

test('normalize returns empty array when id is missing', function () {
    $adapter = new GuardianAdapter;

    expect($adapter->normalize([
        'webUrl' => 'https://www.theguardian.com/article',
        'apiUrl' => 'https://content.guardianapis.com/article',
    ]))->toBe([]);
});

test('normalize returns empty array when webUrl is missing', function () {
    $adapter = new GuardianAdapter;

    expect($adapter->normalize([
        'id' => 'technology/2024/jan/15/slug',
        'apiUrl' => 'https://content.guardianapis.com/article',
    ]))->toBe([]);
});

test('normalize returns empty array when apiUrl is missing', function () {
    $adapter = new GuardianAdapter;

    expect($adapter->normalize([
        'id' => 'technology/2024/jan/15/slug',
        'webUrl' => 'https://www.theguardian.com/article',
    ]))->toBe([]);
});

test('normalize uses Untitled when webTitle is missing', function () {
    $adapter = new GuardianAdapter;

    $result = $adapter->normalize([
        'id' => 'technology/2024/jan/15/slug',
        'webUrl' => 'https://www.theguardian.com/article',
        'apiUrl' => 'https://content.guardianapis.com/article',
        'webPublicationDate' => '2024-01-15T10:00:00Z',
        'sectionId' => 'technology',
        'sectionName' => 'Technology',
    ]);

    expect($result['title'])->toBe('Untitled');
});

test('normalize sets nullable fields to null when fields are absent', function () {
    $adapter = new GuardianAdapter;

    $result = $adapter->normalize([
        'id' => 'technology/2024/jan/15/slug',
        'webUrl' => 'https://www.theguardian.com/article',
        'apiUrl' => 'https://content.guardianapis.com/article',
        'webPublicationDate' => '2024-01-15T10:00:00Z',
        'sectionId' => 'technology',
        'sectionName' => 'Technology',
    ]);

    expect($result['description'])->toBeNull()
        ->and($result['content'])->toBeNull()
        ->and($result['author'])->toBeNull()
        ->and($result['image_url'])->toBeNull();
});

test('normalize creates category if it does not exist', function () {
    $adapter = new GuardianAdapter;

    expect(Category::where('slug', 'politics')->exists())->toBeFalse();

    $adapter->normalize([
        'id' => 'politics/2024/jan/15/slug',
        'webUrl' => 'https://www.theguardian.com/politics/article',
        'apiUrl' => 'https://content.guardianapis.com/politics/article',
        'webPublicationDate' => '2024-01-15T10:00:00Z',
        'sectionId' => 'politics',
        'sectionName' => 'Politics',
    ]);

    expect(Category::where('slug', 'politics')->where('name', 'Politics')->exists())->toBeTrue();
});

test('normalize reuses existing category without creating duplicate', function () {
    Category::factory()->create(['slug' => 'technology', 'name' => 'Technology']);

    $adapter = new GuardianAdapter;

    $adapter->normalize([
        'id' => 'technology/2024/jan/15/slug',
        'webUrl' => 'https://www.theguardian.com/technology/article',
        'apiUrl' => 'https://content.guardianapis.com/technology/article',
        'webPublicationDate' => '2024-01-15T10:00:00Z',
        'sectionId' => 'technology',
        'sectionName' => 'Technology',
    ]);

    expect(Category::where('slug', 'technology')->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// fetch()
// ---------------------------------------------------------------------------

test('fetch returns articles on successful response', function () {
    Http::fake([
        '*' => Http::response([
            'response' => [
                'status' => 'ok',
                'total' => 2,
                'results' => [
                    ['id' => 'tech/slug-1', 'webTitle' => 'Article One'],
                    ['id' => 'tech/slug-2', 'webTitle' => 'Article Two'],
                ],
            ],
        ], 200),
    ]);

    $adapter = new GuardianAdapter;
    $articles = $adapter->fetch();

    expect($articles)->toHaveCount(2)
        ->and($articles[0]['webTitle'])->toBe('Article One');
});

test('fetch returns empty array when results key is absent', function () {
    Http::fake(['*' => Http::response(['response' => ['status' => 'ok']], 200)]);

    $adapter = new GuardianAdapter;

    expect($adapter->fetch())->toBe([]);
});

test('fetch returns empty array and logs error on http error', function () {
    Http::fake(['*' => Http::response(['message' => 'Too Many Requests'], 429)]);

    Log::shouldReceive('info')->once();
    Log::shouldReceive('error')->once()->with('The Guardian HTTP error', Mockery::type('array'));

    $adapter = new GuardianAdapter;

    expect($adapter->fetch())->toBe([]);
});

test('fetch re-throws and logs on connection error', function () {
    Http::fake(function () {
        throw new ConnectionException('Could not connect');
    });

    Log::shouldReceive('info')->once();
    Log::shouldReceive('error')->once()->with('The Guardian connection error', Mockery::type('array'));

    $adapter = new GuardianAdapter;

    expect(fn () => $adapter->fetch())->toThrow(ConnectionException::class);
});
