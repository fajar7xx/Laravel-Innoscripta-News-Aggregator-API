<?php

use App\Models\Category;
use App\Services\Adapters\NYTimesAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

pest()->use(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// normalize()
// ---------------------------------------------------------------------------

test('normalize returns mapped article for valid data', function () {
    $adapter = new NYTimesAdapter;

    $raw = [
        '_id' => 'nyt://article/abc123def456',
        'web_url' => 'https://www.nytimes.com/2024/01/15/technology/ai-article.html',
        'snippet' => 'Short description of the article.',
        'headline' => ['main' => 'AI Article Title'],
        'byline' => ['original' => 'By Jane Doe'],
        'pub_date' => '2024-01-15T10:00:00+0000',
        'section_name' => 'Technology',
        'multimedia' => [
            'default' => [
                'url' => 'https://static01.nyt.com/images/2024/01/15/article/image.jpg',
                'height' => 400,
                'width' => 600,
            ],
        ],
    ];

    $result = $adapter->normalize($raw);

    expect($result)
        ->toHaveKeys(['external_id', 'title', 'description', 'content', 'author', 'url', 'image_url', 'published_at', 'categories'])
        ->and($result['external_id'])->toBe('nyt://article/abc123def456')
        ->and($result['title'])->toBe('AI Article Title')
        ->and($result['description'])->toBe('Short description of the article.')
        ->and($result['author'])->toBe('Jane Doe')
        ->and($result['url'])->toBe('https://www.nytimes.com/2024/01/15/technology/ai-article.html')
        ->and($result['image_url'])->toBe('https://static01.nyt.com/images/2024/01/15/article/image.jpg')
        ->and($result['categories'])->toBe(['technology']);
});

test('normalize returns empty array when _id is missing', function () {
    $adapter = new NYTimesAdapter;

    expect($adapter->normalize([
        'web_url' => 'https://www.nytimes.com/article',
    ]))->toBe([]);
});

test('normalize returns empty array when web_url is missing', function () {
    $adapter = new NYTimesAdapter;

    expect($adapter->normalize([
        '_id' => 'nyt://article/abc123',
    ]))->toBe([]);
});

test('normalize uses Untitled when headline is missing', function () {
    $adapter = new NYTimesAdapter;

    $result = $adapter->normalize([
        '_id' => 'nyt://article/abc123',
        'web_url' => 'https://www.nytimes.com/article',
        'pub_date' => '2024-01-15T10:00:00+0000',
        'section_name' => 'Technology',
    ]);

    expect($result['title'])->toBe('Untitled');
});

test('normalize sets nullable fields to null when absent', function () {
    $adapter = new NYTimesAdapter;

    $result = $adapter->normalize([
        '_id' => 'nyt://article/abc123',
        'web_url' => 'https://www.nytimes.com/article',
        'pub_date' => '2024-01-15T10:00:00+0000',
        'section_name' => 'Technology',
    ]);

    expect($result['description'])->toBeNull()
        ->and($result['content'])->toBeNull()
        ->and($result['author'])->toBeNull()
        ->and($result['image_url'])->toBeNull();
});

test('normalize strips "By " prefix from byline', function () {
    $adapter = new NYTimesAdapter;

    $result = $adapter->normalize([
        '_id' => 'nyt://article/abc123',
        'web_url' => 'https://www.nytimes.com/article',
        'pub_date' => '2024-01-15T10:00:00+0000',
        'section_name' => 'Technology',
        'byline' => ['original' => 'By Jane Doe and Bob Smith'],
    ]);

    expect($result['author'])->toBe('Jane Doe and Bob Smith');
});

test('normalize keeps author as-is when no "By " prefix', function () {
    $adapter = new NYTimesAdapter;

    $result = $adapter->normalize([
        '_id' => 'nyt://article/abc123',
        'web_url' => 'https://www.nytimes.com/article',
        'pub_date' => '2024-01-15T10:00:00+0000',
        'section_name' => 'Technology',
        'byline' => ['original' => 'Reuters'],
    ]);

    expect($result['author'])->toBe('Reuters');
});

test('normalize converts section_name with special characters to slug', function () {
    $adapter = new NYTimesAdapter;

    $result = $adapter->normalize([
        '_id' => 'nyt://article/abc123',
        'web_url' => 'https://www.nytimes.com/article',
        'pub_date' => '2024-01-15T10:00:00+0000',
        'section_name' => 'U.S.',
    ]);

    expect($result['categories'])->toBe(['us']);
});

test('normalize falls back to general category when section_name is missing', function () {
    $adapter = new NYTimesAdapter;

    $result = $adapter->normalize([
        '_id' => 'nyt://article/abc123',
        'web_url' => 'https://www.nytimes.com/article',
        'pub_date' => '2024-01-15T10:00:00+0000',
    ]);

    expect($result['categories'])->toBe(['general']);
});

test('normalize creates category if it does not exist', function () {
    $adapter = new NYTimesAdapter;

    expect(Category::where('slug', 'technology')->exists())->toBeFalse();

    $adapter->normalize([
        '_id' => 'nyt://article/abc123',
        'web_url' => 'https://www.nytimes.com/article',
        'pub_date' => '2024-01-15T10:00:00+0000',
        'section_name' => 'Technology',
    ]);

    expect(Category::where('slug', 'technology')->where('name', 'Technology')->exists())->toBeTrue();
});

test('normalize reuses existing category without creating duplicate', function () {
    Category::factory()->create(['slug' => 'technology', 'name' => 'Technology']);

    $adapter = new NYTimesAdapter;

    $adapter->normalize([
        '_id' => 'nyt://article/abc123',
        'web_url' => 'https://www.nytimes.com/article',
        'pub_date' => '2024-01-15T10:00:00+0000',
        'section_name' => 'Technology',
    ]);

    expect(Category::where('slug', 'technology')->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// fetch()
// ---------------------------------------------------------------------------

test('fetch returns sliced articles on successful response', function () {
    $docs = array_map(fn ($i) => [
        '_id' => "nyt://article/id-{$i}",
        'headline' => ['main' => "Article {$i}"],
    ], range(1, 20));

    Http::fake([
        '*' => Http::response([
            'response' => [
                'meta' => ['hits' => 20],
                'docs' => $docs,
            ],
        ], 200),
    ]);

    $adapter = new NYTimesAdapter;
    $articles = $adapter->fetch();

    expect($articles)->toHaveCount(10)
        ->and($articles[0]['headline']['main'])->toBe('Article 1');
});

test('fetch returns empty array when docs key is absent', function () {
    Http::fake(['*' => Http::response(['response' => ['meta' => ['hits' => 0]]], 200)]);

    $adapter = new NYTimesAdapter;

    expect($adapter->fetch())->toBe([]);
});

test('fetch returns empty array and logs error on http error', function () {
    Http::fake(['*' => Http::response(['fault' => ['faultstring' => 'Rate limit exceeded']], 429)]);

    Log::shouldReceive('info')->once();
    Log::shouldReceive('error')->once()->with('NYTimes HTTP error', Mockery::type('array'));

    $adapter = new NYTimesAdapter;

    expect($adapter->fetch())->toBe([]);
});

test('fetch re-throws and logs on connection error', function () {
    Http::fake(function () {
        throw new ConnectionException('Could not connect');
    });

    Log::shouldReceive('info')->once();
    Log::shouldReceive('error')->once()->with('NYTimes connection error', Mockery::type('array'));

    $adapter = new NYTimesAdapter;

    expect(fn () => $adapter->fetch())->toThrow(ConnectionException::class);
});
