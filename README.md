# News Aggregator Backend API

Laravel 12 REST API for aggregating articles from multiple external providers into a single searchable dataset.

Currently integrated sources:

- NewsAPI
- The Guardian
- New York Times

The application fetches raw articles through source-specific adapters, normalizes them into a shared schema, stores them in MariaDB, and exposes public read-only endpoints for articles, sources, and categories.

## What This Project Does

- Aggregates articles from multiple providers through dedicated adapters
- Normalizes heterogeneous payloads into one internal article model
- Deduplicates articles with unique constraints on `url` and `(source_id, external_id)`
- Stores article-to-category relationships in a pivot table
- Exposes paginated article listing and detail endpoints
- Supports search, filtering, and sorting on article queries
- Dispatches per-source fetch jobs to Redis queues
- Runs scheduled aggregation through Laravel scheduler
- Monitors queue activity with Laravel Horizon

## Stack

| Component | Technology |
| --- | --- |
| Backend | Laravel 12 |
| Language | PHP 8.4 |
| Database | MariaDB 11 |
| Queue / Cache | Redis |
| Queue Dashboard | Laravel Horizon |
| HTTP Client | Laravel HTTP Client |
| Testing | Pest 4 |
| Local Container Setup | Docker Compose |
| Frontend Asset Tooling | Vite + Tailwind CSS v4 |

## Project Structure

```text
app/
├── Console/Commands/AggregateNewsCommand.php
├── Contracts/NewsSourceInterface.php
├── Http/Controllers/API/V1/
├── Http/Requests/
├── Http/Resources/
├── Jobs/FetchArticlesJob.php
├── Models/
├── Providers/HorizonServiceProvider.php
└── Services/
    ├── NewsAggregationService.php
    └── Adapters/
        ├── GuardianAdapter.php
        ├── NYTimesAdapter.php
        └── NewsApiAdapter.php
database/
├── factories/
├── migrations/
└── seeders/
docs/
├── v1/
└── v2/
routes/
├── api.php
└── console.php
```

## API Overview

Base path:

```text
/api/v1
```

Public endpoints:

- `GET /api/health`
- `GET /api/v1/articles`
- `GET /api/v1/articles/{article}`
- `GET /api/v1/sources`
- `GET /api/v1/sources/{source}`
- `GET /api/v1/categories`
- `GET /api/v1/categories/{category}`

API documentation endpoints:

- `GET /api/documentation` for Swagger UI
- `GET /docs` for the generated OpenAPI document
- Postman collection file: `Innoscripta News Aggregator.postman_collection.json`

Swagger UI route:

```text
http://localhost:8080/api/documentation
```

Laravel also exposes the framework health endpoint at:

```text
GET /up
```

### Health Response

```json
{
    "status": "OK",
    "service": "news-aggregator-backend-api",
    "version": "1.0.0",
    "timestamp": "2026-03-17T00:00:00+00:00"
}
```

### Article Query Parameters

`GET /api/v1/articles`

| Parameter | Description |
| --- | --- |
| `q` | Full-text search on `title`, `description`, and `content` |
| `source` | Filter by source slug |
| `category` | Filter by category slug |
| `author` | Filter by exact author value |
| `from` | Filter `published_at >= YYYY-MM-DD` |
| `to` | Filter `published_at <= YYYY-MM-DD` |
| `sort_by` | One of `published_at`, `title`, `created_at` |
| `sort_order` | `asc` or `desc` |
| `page` | Pagination page |

Example:

```bash
curl "http://localhost:8080/api/v1/articles?q=technology&source=guardian&category=world&sort_by=published_at&sort_order=desc"
```

### Article Response Shape

```json
{
    "data": [
        {
            "id": 1,
            "source_id": 2,
            "external_id": "guardian-123",
            "title": "Example article",
            "description": "Short summary",
            "content": "Normalized article content",
            "author": "Reporter Name",
            "url": "https://example.com/article",
            "image_url": "https://example.com/image.jpg",
            "published_at": "2026-03-17T08:00:00.000000Z",
            "fetched_at": "2026-03-17T08:05:00.000000Z",
            "source": {
                "id": 2,
                "name": "The Guardian",
                "slug": "guardian",
                "is_active": true,
                "last_fetched_at": "2026-03-17T08:05:00.000000Z",
                "created_at": "2026-03-16T00:00:00.000000Z",
                "updated_at": "2026-03-17T08:05:00.000000Z"
            },
            "categories": [
                {
                    "id": 1,
                    "name": "Technology",
                    "slug": "technology",
                    "created_at": "2026-03-16T00:00:00.000000Z",
                    "updated_at": "2026-03-16T00:00:00.000000Z"
                }
            ],
            "created_at": "2026-03-17T08:05:00.000000Z",
            "updated_at": "2026-03-17T08:05:00.000000Z"
        }
    ],
    "links": {},
    "meta": {}
}
```

## API Documentation

This project uses `darkaonline/l5-swagger` with OpenAPI 3 attributes.

Open the interactive documentation at:

```text
http://localhost:8080/api/documentation
```

If you run the project with a different host or port, adjust the URL accordingly.

The generated spec is served from:

```text
http://localhost:8080/docs
```

If you prefer Postman, import the collection file that is already included in this repository:

```text
Innoscripta News Aggregator.postman_collection.json
```

After importing it into Postman, adjust the collection variables or request base URL to match your local environment.

Regenerate the documentation after changing endpoint attributes:

```bash
php artisan l5-swagger:generate
```

The generated file is stored at:

```text
storage/api-docs/api-docs.json
```

## Authentication

The current API endpoints are public and do not apply auth middleware.

`laravel/sanctum` is installed in the project, but token-protected API routes are not implemented yet in the current codebase.

## Architecture

High-level flow:

```text
External APIs
-> Source Adapters
-> FetchArticlesJob
-> NewsAggregationService
-> MariaDB
-> REST API Resources
```

### Source Adapters

Each provider has its own adapter class implementing `App\Contracts\NewsSourceInterface`.

- `App\Services\Adapters\NewsApiAdapter`
- `App\Services\Adapters\GuardianAdapter`
- `App\Services\Adapters\NYTimesAdapter`

Each adapter is responsible for:

- Calling the upstream API
- Mapping provider-specific fields
- Returning normalized article arrays for persistence

### Aggregation Service

`App\Services\NewsAggregationService` persists normalized data and syncs categories.

Deduplication is handled through:

- `Article::updateOrCreate()` keyed by `(source_id, external_id)`
- A unique database constraint on article `url`

### Queue Jobs

`App\Jobs\FetchArticlesJob` runs one job per active source.

Current job configuration:

- `tries = 3`
- `backoff = [30, 120, 600]`
- `timeout = 120`

If one source fails, the other sources are unaffected because jobs are dispatched independently.

### Scheduler

The scheduler is defined in `routes/console.php` and currently dispatches:

```bash
php artisan news:aggregate
```

Schedule:

- Daily at `00:00`

### Search and Filtering

Article search uses `whereFullText()` on:

- `title`
- `description`
- `content`

The articles migration also creates database indexes for:

- full-text search
- sort by `published_at`
- filter by `source_id` and sort by `published_at`

## Database Notes

Core tables:

- `sources`
- `categories`
- `articles`
- `article_category`
- `jobs`
- `failed_jobs`
- `personal_access_tokens`

Seeded reference data:

- 3 initial sources from `config/news.php`
- 8 default categories from `CategorySeeder`

Important behaviors:

- `articles`, `sources`, and `categories` use soft deletes
- article categories are many-to-many
- source activation is controlled by `sources.is_active`

## Environment Variables

Copy the example file first:

```bash
cp .env.example .env
```

Important values:

```env
APP_URL=http://localhost

DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_innoscripta_news_aggregator_api
DB_USERNAME=root
DB_PASSWORD=
DB_ROOT_PASSWORD=root_secret

QUEUE_CONNECTION=redis
CACHE_STORE=redis

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

NEWS_API_KEY=
NEWS_API_BASE_URL=
NEWS_SOURCE_NEWSAPI_ENABLED=true

GUARDIAN_API_KEY=
GUARDIAN_API_BASE_URL=
NEWS_SOURCE_GUARDIAN_ENABLED=true

NYTIMES_API_KEY=
NYTIMES_BASE_URL=
NEWS_SOURCE_NYTIMES_ENABLED=true

NEWS_AGGREGATION_FREQUENCY=hourly
NEWS_ARTICLES_PER_FETCH=100

API_DEFAULT_PER_PAGE=20
API_MAX_PER_PAGE=100
API_CACHE_TTL=300
API_CACHE_ENABLED=true
```

Provider base URLs are intentionally configurable through `.env`.

## Running With Docker

### 1. Start containers

```bash
docker compose up -d --build
```

Services defined in `docker-compose.yml`:

| Service | Description | Host Port |
| --- | --- | --- |
| `app` | PHP-FPM application container | - |
| `nginx` | HTTP entrypoint | `8080` |
| `mariadb` | MariaDB database | `3307` |
| `redis` | Redis cache / queue | `6380` |
| `queue` | Horizon worker container | - |

### 2. Install PHP dependencies

```bash
docker compose exec app composer install
```

### 3. Generate app key

```bash
docker compose exec app php artisan key:generate
```

### 4. Run migrations and seeders

```bash
docker compose exec app php artisan migrate --seed
```

### 5. Install frontend dependencies and build assets

```bash
docker compose exec app npm install
docker compose exec app npm run build
```

### 6. Access the application

```text
http://localhost:8080
```

### Useful Docker commands

```bash
docker compose ps
docker compose logs -f app
docker compose logs -f queue
docker compose exec app bash
docker compose exec app php artisan route:list
docker compose down
docker compose down -v
```

### Docker permission issue

Because this project uses a Docker bind mount for the application directory, you may hit filesystem permission issues on `storage/` or `bootstrap/cache/`.

Example error:

```text
The stream or file "/home/fajarsiagian/projects/laravel-innoscripta-news-aggregator-api/storage/logs/laravel.log" could not be opened in append mode: Failed to open stream: Permission denied
The exception occurred while attempting to log: The /home/fajarsiagian/projects/laravel-innoscripta-news-aggregator-api/bootstrap/cache directory must be present and writable.
Context: {"exception":{}}
```

Temporary workaround:

```bash
sudo chown -R $USER:$USER .
```

This is related to the current Docker setup and will be improved later.

## Running Locally Without Docker

### First-time setup

```bash
composer setup
php artisan migrate --seed
```

`composer setup` installs Composer dependencies, prepares `.env`, generates the app key, runs migrations, installs NPM packages, and builds assets.

### Start the local development stack

```bash
composer dev
```

This starts:

- Laravel development server
- queue listener
- log watcher with Pail
- Vite dev server

## Aggregation Workflow

### Trigger aggregation manually

```bash
php artisan news:aggregate
```

Inside Docker:

```bash
docker compose exec app php artisan news:aggregate
```

The command:

- loads all active sources
- dispatches one `FetchArticlesJob` per source
- skips inactive sources

### Run the scheduler loop

Local:

```bash
php artisan schedule:work
```

Docker:

```bash
docker compose exec app php artisan schedule:work
```

### Verify scheduled commands

```bash
php artisan schedule:list
```

## Horizon

The queue worker container runs:

```bash
php artisan horizon
```

Dashboard URL in local development:

```text
http://localhost:8080/horizon
```

Notes:

- Horizon uses the `web` middleware group
- In non-local environments, access is gated by `App\Providers\HorizonServiceProvider`
- The allowlist in that provider is currently empty, so production access needs explicit configuration

## Testing and Quality

Run the test suite:

```bash
php artisan test --compact
```

Inside Docker:

```bash
docker compose exec app php artisan test --compact
```

Run formatting:

```bash
vendor/bin/pint --dirty --format agent
```

Inside Docker:

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
```

## Makefile Shortcuts

The repository includes a `Makefile` with shortcuts for common Docker tasks:

```bash
make up
make down
make ps
make logs
make shell
make migrate
make fresh
make test
make pint
```

## Additional Documentation

Architecture and planning notes are available in:

- `docs/v1/`
- `docs/v2/`
- `docs/task/backend-case-study.md`
- `docs/third-party-response/`

## Summary

This project is a Laravel-based news aggregation backend with:

- adapter-driven ingestion from multiple providers
- asynchronous fetching through queues
- deduplicated article persistence
- searchable and filterable public read APIs
- Docker-based local development
- Horizon-backed queue monitoring
