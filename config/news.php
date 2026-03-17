<?php

return [
    /*
    |--------------------------------------------------------------------------
    | News API Sources
    |--------------------------------------------------------------------------
    |
    | Configuration for external news API providers. Each source requires
    | an API key which should be stored in your .env file.
    |
    | Get API keys from:
    | - NewsAPI: https://newsapi.org/register
    | - Guardian: https://open-platform.theguardian.com/access/
    | - NYTimes: https://developer.nytimes.com/get-started
    |
    */
    'sources' => [
        'newsapi' => [
            'name' => 'NewsAPI',
            'slug' => 'newsapi',
            'api_key' => env('NEWS_API_KEY'),
            'base_url' => env('NEWS_API_BASE_URL', ''),
            'enabled' => env('NEWS_SOURCE_NEWSAPI_ENABLED', true),
            'timeout' => 30, // HTTP Timeout in seconds
            'endpoints' => [
                'top_headlines' => '/top-headlines',
                'everything' => '/everything',
            ],
        ],

        'guardian' => [
            'name' => 'The Guardian',
            'slug' => 'guardian',
            'api_key' => env('GUARDIAN_API_KEY'),
            'base_url' => env('GUARDIAN_BASE_URL', ''),
            'enabled' => env('NEWS_SOURCE_GUARDIAN_ENABLED', true),
            'timeout' => 30,
            'endpoints' => [
                'search' => '/search',
            ],
        ],

        'nytimes' => [
            'name' => 'New York Times',
            'slug' => 'nytimes',
            'api_key' => env('NYTIMES_API_KEY'),
            'base_url' => env('NYTIMES_BASE_URL', ''),
            'enabled' => env('NEWS_SOURCE_NYTIMES_ENABLED', true),
            'timeout' => 30,
            'endpoints' => [
                'archive' => '/svc/archive/v1',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Aggregation Settings
    |--------------------------------------------------------------------------
    |
    | Controls how often and how many articles are fetched from each source.
    |
    */
    'aggregation' => [
        'frequency' => env('NEWS_AGGREGATION_FREQUENCY', 'hourly'), // hourly,daily,weekly,monthly
        'articles_per_fetch' => env('NEWS_ARTICLES_PER_FETCH', 100),
        'retry_failed_after' => 3600, // retry failed job after 1 hour
        'max_retries' => 3, // maximum number of retry attempts
        'backoff_multiplier' => 2, // exponentially backoff multiplier
    ],

    /*
    |--------------------------------------------------------------------------
    | API Settings
    |--------------------------------------------------------------------------
    |
    | Settings for the public API endpoints.
    |
    */
    'api' => [
        'default_per_page' => env('API_DEFAULT_PER_PAGE', 20),
        'max_per_page' => env('API_MAX_PER_PAGE', 100),
        'cache_ttl' => env('API_CACHE_TTL', 300), // 5 minutes
        'cache_enabled' => env('API_CACHE_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Category Mapping
    |--------------------------------------------------------------------------
    |
    | Maps external API categories to internal category slugs.
    |
    */
    'category_mapping' => [
        // NewsAPI categories → internal slugs
        'newsapi' => [
            'technology' => 'technology',
            'business' => 'business',
            'sports' => 'sports',
            'entertainment' => 'entertainment',
            'health' => 'health',
            'science' => 'science',
            'general' => 'world',
            'politics' => 'politics',
        ],

        // Guardian sections → internal slugs
        'guardian' => [
            'technology' => 'technology',
            'business' => 'business',
            'sport' => 'sports',
            'football' => 'sports',
            'culture' => 'entertainment',
            'film' => 'entertainment',
            'music' => 'entertainment',
            'lifeandstyle' => 'health',
            'science' => 'science',
            'politics' => 'politics',
            'world' => 'world',
            'uk-news' => 'world',
            'us-news' => 'world',
        ],

        // NYTimes sections → internal slugs
        'nytimes' => [
            'Technology' => 'technology',
            'Business Day' => 'business',
            'Business' => 'business',
            'Sports' => 'sports',
            'Arts' => 'entertainment',
            'Movies' => 'entertainment',
            'Theater' => 'entertainment',
            'Health' => 'health',
            'Science' => 'science',
            'Politics' => 'politics',
            'World' => 'world',
            'U.S.' => 'world',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Search Parameters
    |--------------------------------------------------------------------------
    |
    | Default parameters used when fetching articles from external APIs.
    |
    */
    'search_defaults' => [
        'newsapi' => [
            'language' => 'en',
            'sortBy' => 'publishedAt',
            'pageSize' => 100,
        ],

        'guardian' => [
            'page-size' => 100,
            'order-by' => 'newest',
            'show-fields' => 'all',
        ],

        'nytimes' => [
            'sort' => 'newest',
            'page' => 0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limit settings for external API calls to prevent hitting API
    | provider quotas.
    |
    */
    'rate_limits' => [
        'newsapi' => [
            'requests_per_day' => 1000, // Free tier limit
            'requests_per_hour' => 100,
        ],

        'guardian' => [
            'requests_per_day' => 5000, // Developer tier limit
            'requests_per_second' => 5,
        ],

        'nytimes' => [
            'requests_per_day' => 4000, // Developer tier limit
            'requests_per_minute' => 10,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging behavior for news aggregation operations.
    |
    */
    'logging' => [
        'channel' => env('NEWS_LOG_CHANNEL', 'stack'),
        'log_successful_fetches' => env('NEWS_LOG_SUCCESS', true),
        'log_api_requests' => env('NEWS_LOG_API_REQUESTS', false),
    ],
];
