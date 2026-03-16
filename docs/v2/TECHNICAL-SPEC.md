# Technical Specification - News Aggregator Backend API

**Project:** News Aggregator Backend API  
**Type:** RESTful API (Laravel 12)  
**Duration:** 3-day case study  
**Version:** 1.0  
**Date:** March 2026

---

## 1. System Overview

### 1.1 Purpose
Backend API system that aggregates news articles from multiple external sources (NewsAPI, The Guardian, New York Times) and serves them via RESTful endpoints with search, filtering, and categorization capabilities.

### 1.2 Scope
- Fetch articles from 3 external news APIs
- Store articles in local database with deduplication
- Provide REST API for frontend consumption
- Enable search and filtering capabilities
- Background job processing for article aggregation

### 1.3 Out of Scope (for MVP)
- User authentication/authorization
- Frontend application
- Real-time notifications
- Article recommendation engine
- Social sharing features

---

## 2. Technical Stack

| Component | Technology | Version | Purpose |
|-----------|------------|---------|---------|
| Backend Framework | Laravel | 12.x | API development |
| Database | MariaDB | 11.x | Data persistence |
| Cache/Queue | Redis | 7.x | Caching & job queue |
| Container | Docker | Latest | Environment consistency |
| Web Server | Nginx | Latest | HTTP server |
| PHP | PHP | 8.3+ | Runtime |
| Queue Monitor | Laravel Horizon | Latest | Queue dashboard |
| Debug Tool | Laravel Telescope | Latest | Development debugging |

---

## 3. Database Design (ERD)

### 3.1 Entity Relationship Diagram

```
┌─────────────────┐
│    sources      │
├─────────────────┤
│ id (PK)         │
│ name            │
│ slug (UNIQUE)   │
│ is_active       │
│ last_fetched_at │
└────────┬────────┘
         │
         │ 1:N
         │
         ▼
┌─────────────────────────┐         ┌──────────────────┐
│       articles          │    N:M  │    categories    │
├─────────────────────────┤◄────────┤──────────────────┤
│ id (PK)                 │         │ id (PK)          │
│ source_id (FK)          │         │ name             │
│ external_id             │         │ slug (UNIQUE)    │
│ title                   │         └──────────────────┘
│ description             │                 ▲
│ content                 │                 │
│ author                  │                 │
│ url (UNIQUE)            │                 │
│ image_url               │                 │
│ published_at            │                 │
│ fetched_at              │                 │
│ UNIQUE(source_id,       │                 │
│        external_id)     │                 │
│ FULLTEXT(title,         │                 │
│          description,   │                 │
│          content)       │                 │
└────────────┬────────────┘                 │
             │                              │
             │ N:M                          │
             │                              │
             ▼                              │
     ┌──────────────────┐                  │
     │ article_category │──────────────────┘
     ├──────────────────┤
     │ id (PK)          │
     │ article_id (FK)  │
     │ category_id (FK) │
     │ UNIQUE(article_  │
     │        id,       │
     │        category_ │
     │        id)       │
     └──────────────────┘
```

### 3.2 Table Specifications

#### **sources** Table
```sql
CREATE TABLE sources (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_fetched_at TIMESTAMP NULL,
    api_key_encrypted TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_is_active (is_active)
);
```

**Purpose:** Track news API providers  
**Records:** 3 (NewsAPI, Guardian, NYT)

#### **categories** Table
```sql
CREATE TABLE categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Purpose:** Article categorization  
**Records:** 8 (Technology, Business, Politics, Sports, Entertainment, Science, Health, World)

#### **articles** Table
```sql
CREATE TABLE articles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_id BIGINT UNSIGNED NOT NULL,
    external_id VARCHAR(255) NOT NULL,
    title VARCHAR(500) NOT NULL,
    description TEXT NULL,
    content LONGTEXT NULL,
    author VARCHAR(255) NULL,
    url VARCHAR(500) UNIQUE NOT NULL,
    image_url VARCHAR(500) NULL,
    published_at TIMESTAMP NOT NULL,
    fetched_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE CASCADE,
    UNIQUE KEY unique_source_article (source_id, external_id),
    INDEX idx_source_id (source_id),
    INDEX idx_published_at (published_at DESC),
    INDEX idx_source_published (source_id, published_at DESC),
    FULLTEXT INDEX ft_search (title, description, content)
);
```

**Purpose:** Store aggregated articles  
**Key Features:**
- Deduplication via UNIQUE(source_id, external_id)
- Full-text search via FULLTEXT index
- Performance indexes for common queries

#### **article_category** Table (Pivot)
```sql
CREATE TABLE article_category (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_article_category (article_id, category_id),
    INDEX idx_category_id (category_id)
);
```

**Purpose:** Many-to-many relationship (articles ↔ categories)

---

## 4. API Specification

### 4.1 Base URL
```
http://localhost:8080/api/v1
```

### 4.2 Endpoints

#### **GET /articles** - List Articles

**Description:** Retrieve paginated list of articles with optional filtering and search.

**Request:**
```http
GET /api/v1/articles?q=technology&source=guardian&category=tech&page=1&per_page=20
```

**Query Parameters:**
| Parameter | Type | Required | Description | Example |
|-----------|------|----------|-------------|---------|
| q | string | No | Search keyword (full-text) | `technology` |
| source | string | No | Filter by source slug | `guardian` |
| category | string | No | Filter by category slug | `technology` |
| author | string | No | Filter by author name | `john smith` |
| from | date | No | Published after date (ISO 8601) | `2024-01-01` |
| to | date | No | Published before date (ISO 8601) | `2024-12-31` |
| sort_by | string | No | Sort field (`published_at`, `title`) | `published_at` |
| sort_order | string | No | Sort direction (`asc`, `desc`) | `desc` |
| page | integer | No | Page number (default: 1) | `2` |
| per_page | integer | No | Items per page (default: 20, max: 100) | `50` |

**Response 200 OK:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "AI Breakthrough in Healthcare",
      "description": "New AI model shows promising results in early disease detection",
      "content": "Full article content here...",
      "author": "John Doe",
      "url": "https://example.com/article-slug",
      "image_url": "https://example.com/image.jpg",
      "published_at": "2024-03-15T10:30:00Z",
      "fetched_at": "2024-03-15T12:00:00Z",
      "source": {
        "id": 2,
        "name": "The Guardian",
        "slug": "guardian"
      },
      "categories": [
        {
          "id": 1,
          "name": "Technology",
          "slug": "technology"
        },
        {
          "id": 7,
          "name": "Health",
          "slug": "health"
        }
      ]
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8,
    "from": 1,
    "to": 20
  }
}
```

---

#### **GET /articles/{id}** - Get Single Article

**Description:** Retrieve detailed information for a specific article.

**Request:**
```http
GET /api/v1/articles/1
```

**Path Parameters:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | Article ID |

**Response 200 OK:**
```json
{
  "data": {
    "id": 1,
    "title": "AI Breakthrough in Healthcare",
    "description": "New AI model shows promising results",
    "content": "Full article content here...",
    "author": "John Doe",
    "url": "https://example.com/article",
    "image_url": "https://example.com/image.jpg",
    "published_at": "2024-03-15T10:30:00Z",
    "fetched_at": "2024-03-15T12:00:00Z",
    "source": {
      "id": 2,
      "name": "The Guardian",
      "slug": "guardian"
    },
    "categories": [
      {
        "id": 1,
        "name": "Technology",
        "slug": "technology"
      }
    ]
  }
}
```

**Response 404 Not Found:**
```json
{
  "message": "Article not found"
}
```

---

#### **GET /sources** - List Sources

**Description:** Retrieve all news sources.

**Request:**
```http
GET /api/v1/sources
```

**Response 200 OK:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "NewsAPI",
      "slug": "newsapi",
      "is_active": true,
      "last_fetched_at": "2024-03-15T12:00:00Z",
      "article_count": 450
    },
    {
      "id": 2,
      "name": "The Guardian",
      "slug": "guardian",
      "is_active": true,
      "last_fetched_at": "2024-03-15T12:05:00Z",
      "article_count": 523
    },
    {
      "id": 3,
      "name": "New York Times",
      "slug": "nytimes",
      "is_active": true,
      "last_fetched_at": "2024-03-15T12:10:00Z",
      "article_count": 612
    }
  ]
}
```

---

#### **GET /categories** - List Categories

**Description:** Retrieve all article categories.

**Request:**
```http
GET /api/v1/categories
```

**Response 200 OK:**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Technology",
      "slug": "technology",
      "article_count": 234
    },
    {
      "id": 2,
      "name": "Business",
      "slug": "business",
      "article_count": 189
    },
    {
      "id": 3,
      "name": "Politics",
      "slug": "politics",
      "article_count": 156
    }
  ]
}
```

---

## 5. Data Flow & System Architecture

### 5.1 Article Aggregation Flow

```
┌──────────────┐
│   Scheduler  │ (Runs hourly)
│  (Laravel)   │
└──────┬───────┘
       │
       │ Dispatches
       ▼
┌──────────────────┐
│ AggregateNews    │
│    Command       │
└──────┬───────────┘
       │
       │ Dispatches 3 jobs
       ▼
┌──────────────────┐
│ FetchArticlesJob │ (Queue: Redis)
│ - NewsAPI        │ Retries: 3x (30s, 2m, 10m)
│ - Guardian       │
│ - NYTimes        │
└──────┬───────────┘
       │
       │ Calls
       ▼
┌──────────────────────┐
│  Adapter Pattern     │
│ ┌────────────────┐   │
│ │ NewsApiAdapter │   │
│ ├────────────────┤   │
│ │GuardianAdapter │   │
│ ├────────────────┤   │
│ │ NYTimesAdapter │   │
│ └────────────────┘   │
└──────┬───────────────┘
       │
       │ fetch() & normalize()
       ▼
┌──────────────────────────┐
│ External News APIs       │
│ - NewsAPI.org            │
│ - Guardian API           │
│ - NYTimes Article Search │
└──────┬───────────────────┘
       │
       │ Returns articles
       ▼
┌─────────────────────────┐
│ NewsAggregationService  │
│ - saveArticle()         │
│ - updateOrCreate()      │
│   (Deduplication)       │
└──────┬──────────────────┘
       │
       │ Saves
       ▼
┌──────────────────┐
│    Database      │
│   (MariaDB)      │
└──────────────────┘
```

### 5.2 API Request Flow

```
┌──────────────┐
│   Client     │
│  (Frontend)  │
└──────┬───────┘
       │
       │ HTTP GET /api/v1/articles?q=tech
       ▼
┌──────────────────┐
│     Nginx        │
└──────┬───────────┘
       │
       ▼
┌──────────────────┐
│  Laravel Router  │
│  (routes/api.php)│
└──────┬───────────┘
       │
       │ Route to controller
       ▼
┌─────────────────────────┐
│  ArticleController      │
│  - index()              │
│  - Apply filters        │
│  - whereFullText()      │
│  - whereHas()           │
│  - orderBy()            │
│  - paginate()           │
└──────┬──────────────────┘
       │
       │ Query with eager loading
       ▼
┌──────────────────────────┐
│  Eloquent ORM            │
│  Article::with('source', │
│            'categories') │
└──────┬───────────────────┘
       │
       │ SQL Query
       ▼
┌──────────────────┐
│    MariaDB       │
│  (with indexes)  │
└──────┬───────────┘
       │
       │ Results
       ▼
┌──────────────────┐
│  JSON Response   │
│  (with metadata) │
└──────────────────┘
```

---

## 6. External API Integration

### 6.1 NewsAPI Integration

**Endpoint:** `https://newsapi.org/v2/everything`

**Request:**
```http
GET /v2/everything?q=technology&pageSize=100&apiKey=YOUR_KEY
```

**Field Mapping:**
| NewsAPI Field | Internal Field | Notes |
|---------------|---------------|-------|
| title | title | Direct mapping |
| description | description | Direct mapping |
| content | content | Direct mapping |
| author | author | Direct mapping |
| url | url | UNIQUE constraint |
| urlToImage | image_url | Direct mapping |
| publishedAt | published_at | ISO 8601 format |
| - | external_id | md5(url) - no stable ID |
| - | source_id | 1 (NewsAPI) |

**Deduplication:** Uses `md5(url)` as `external_id` (NewsAPI doesn't provide stable IDs)

---

### 6.2 The Guardian Integration

**Endpoint:** `https://content.guardianapis.com/search`

**Request:**
```http
GET /search?page-size=100&api-key=YOUR_KEY
```

**Field Mapping:**
| Guardian Field | Internal Field | Notes |
|----------------|---------------|-------|
| webTitle | title | Direct mapping |
| fields.body | content | Requires fields=body |
| fields.byline | author | Requires fields=byline |
| webUrl | url | UNIQUE constraint |
| fields.thumbnail | image_url | Requires fields=thumbnail |
| webPublicationDate | published_at | ISO 8601 format |
| id | external_id | Stable ID ✅ |
| sectionName | category | Map to internal categories |
| - | source_id | 2 (Guardian) |

**Deduplication:** Uses Guardian's `id` field (stable, e.g., "world/2024/mar/15/article-slug")

---

### 6.3 New York Times Integration

**Endpoint:** `https://api.nytimes.com/svc/search/v2/articlesearch.json`

**Request:**
```http
GET /articlesearch.json?q=technology&api-key=YOUR_KEY
```

**Field Mapping:**
| NYT Field | Internal Field | Notes |
|-----------|---------------|-------|
| headline.main | title | Direct mapping |
| abstract | description | Direct mapping |
| lead_paragraph | content | Or snippet |
| byline.original | author | Direct mapping |
| web_url | url | UNIQUE constraint |
| multimedia[0].url | image_url | First image |
| pub_date | published_at | ISO 8601 format |
| _id | external_id | Stable ID ✅ |
| section_name | category | Map to internal categories |
| - | source_id | 3 (NYTimes) |

**Deduplication:** Uses NYT's `_id` field (stable, e.g., "nyt://article/abc123def456")

---

## 7. Background Job Processing

### 7.1 Queue Configuration

**Queue Driver:** Redis  
**Queue Connection:** `redis`  
**Queue Name:** `default`

### 7.2 Job Specification: FetchArticlesJob

```php
class FetchArticlesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SeriesManager;

    public $tries = 3;
    public $backoff = [30, 120, 600]; // 30s, 2min, 10min
    public $timeout = 120; // 2 minutes
    
    protected $sourceSlug;
    
    public function handle()
    {
        $source = Source::where('slug', $this->sourceSlug)->first();
        $adapter = app(NewsSourceInterface::class, ['source' => $source]);
        $service = app(NewsAggregationService::class);
        
        $articles = $adapter->fetch();
        
        foreach ($articles as $articleData) {
            $service->saveArticle($source, $articleData);
        }
        
        $source->update(['last_fetched_at' => now()]);
    }
    
    public function failed(Throwable $exception)
    {
        Log::critical("Article fetch failed for {$this->sourceSlug}", [
            'exception' => $exception->getMessage()
        ]);
    }
}
```

**Retry Strategy:**
- Attempt 1: Immediate
- Attempt 2: After 30 seconds
- Attempt 3: After 2 minutes
- Attempt 4: After 10 minutes
- After 4 failures: Move to `failed_jobs` table

---

## 8. Deduplication Strategy

### 8.1 Problem
Same article might be fetched multiple times due to:
- Overlapping date ranges in API queries
- Scheduler reruns (hourly)
- Manual fetch commands

### 8.2 Solution
Composite UNIQUE constraint on `(source_id, external_id)`

### 8.3 Implementation

**Database Level:**
```sql
UNIQUE KEY unique_source_article (source_id, external_id)
```

**Application Level:**
```php
Article::updateOrCreate(
    [
        'source_id' => $source->id,
        'external_id' => $data['external_id']
    ],
    [
        'title' => $data['title'],
        'description' => $data['description'],
        // ... other fields
    ]
);
```

**Behavior:**
- First fetch: Creates new article
- Subsequent fetches: Updates existing article (no duplicate)

### 8.4 External ID Strategy

| Source | External ID | Stability |
|--------|-------------|-----------|
| Guardian | API's `id` field | ✅ Stable |
| NYTimes | API's `_id` field | ✅ Stable |
| NewsAPI | `md5(url)` | ⚠️ URL-dependent |

**Why not URL as primary key?**
- URLs can change (utm parameters, redirects)
- API-provided IDs are more reliable
- NewsAPI fallback: hash URL (no stable ID provided)

---

## 9. Search & Filtering Implementation

### 9.1 Full-Text Search

**Database:**
```sql
FULLTEXT INDEX ft_search (title, description, content)
```

**Query:**
```php
Article::whereFullText(['title', 'description', 'content'], $keyword)
    ->orderBy('published_at', 'desc')
    ->paginate(20);
```

**Search Mode:** Natural language (default)

**Trade-offs:**
- ✅ Fast for <1M articles
- ✅ No external service needed
- ❌ No typo correction
- ❌ Basic relevance ranking

---

### 9.2 Filtering

**Source Filter:**
```php
$query->whereHas('source', function($q) use ($sourceSlug) {
    $q->where('slug', $sourceSlug);
});
```

**Category Filter:**
```php
$query->whereHas('categories', function($q) use ($categorySlug) {
    $q->where('slug', $categorySlug);
});
```

**Author Filter:**
```php
$query->where('author', 'like', "%{$author}%");
```

**Date Range Filter:**
```php
$query->whereBetween('published_at', [$from, $to]);
```

**Combined Example:**
```php
$query = Article::query()
    ->with('source', 'categories');

if ($request->q) {
    $query->whereFullText(['title', 'description', 'content'], $request->q);
}

if ($request->source) {
    $query->whereHas('source', fn($q) => 
        $q->where('slug', $request->source)
    );
}

if ($request->category) {
    $query->whereHas('categories', fn($q) => 
        $q->where('slug', $request->category)
    );
}

$query->orderBy('published_at', 'desc')
      ->paginate(20);
```

---

## 10. Performance Optimization

### 10.1 Database Indexes

**Performance Indexes:**
```sql
INDEX idx_source_id (source_id)
INDEX idx_published_at (published_at DESC)
INDEX idx_source_published (source_id, published_at DESC)
INDEX idx_category_id (category_id) ON article_category
FULLTEXT INDEX ft_search (title, description, content)
```

**Query Optimization:**
```
Filter by source + sort by date:
  Uses: idx_source_published (composite index)
  
Filter by category:
  Uses: idx_category_id on pivot table
  
Search by keyword:
  Uses: ft_search (FULLTEXT index)
```

### 10.2 N+1 Query Prevention

**Problem:**
```php
// N+1: 1 query + N queries for sources
$articles = Article::all();
foreach ($articles as $article) {
    echo $article->source->name; // Query per article!
}
```

**Solution:**
```php
// 2 queries total
$articles = Article::with('source', 'categories')->get();
foreach ($articles as $article) {
    echo $article->source->name; // No additional query
}
```

### 10.3 Pagination

**Offset-based pagination:**
```php
Article::paginate(20); // Default: page 1, 20 items
```

**Response includes:**
- `current_page`
- `per_page`
- `total` (total items)
- `last_page` (total pages)
- `from` / `to` (item range)

---

## 11. Error Handling

### 11.1 API Errors

**HTTP Status Codes:**
| Code | Meaning | When |
|------|---------|------|
| 200 | OK | Successful request |
| 404 | Not Found | Article/resource doesn't exist |
| 422 | Unprocessable Entity | Validation error |
| 500 | Internal Server Error | Server error |

**Error Response Format:**
```json
{
  "message": "Validation failed",
  "errors": {
    "source": ["Invalid source slug"],
    "from": ["Date must be before 'to' date"]
  }
}
```

### 11.2 Job Failures

**Retry Logic:**
- 3 automatic retries with exponential backoff
- After final failure: stored in `failed_jobs` table
- Logged with full context

**Failed Job Handling:**
```bash
# View failed jobs
php artisan queue:failed

# Retry specific job
php artisan queue:retry JOB_ID

# Retry all failed jobs
php artisan queue:retry all
```

---

## 12. Deployment Checklist

### 12.1 Environment Setup
- [ ] Copy `.env.example` to `.env`
- [ ] Add database credentials
- [ ] Add Redis configuration
- [ ] Add NewsAPI key
- [ ] Add Guardian API key
- [ ] Add NYTimes API key
- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`

### 12.2 Database Setup
- [ ] Run migrations: `php artisan migrate`
- [ ] Run seeders: `php artisan db:seed`
- [ ] Verify 3 sources + 8 categories seeded

### 12.3 Queue Setup
- [ ] Start queue worker: `php artisan queue:work`
- [ ] Or use Supervisor for production
- [ ] Or start Horizon: `php artisan horizon`

### 12.4 Scheduler Setup
- [ ] Add cron job: `* * * * * php artisan schedule:run`
- [ ] Verify scheduler runs: `php artisan schedule:list`

### 12.5 Testing
- [ ] API endpoints respond correctly
- [ ] Article aggregation works: `php artisan news:aggregate`
- [ ] Search functionality works
- [ ] Filters work correctly
- [ ] Deduplication prevents duplicates

---

## 13. Testing Strategy

### 13.1 Unit Tests
- Adapter normalization logic
- Deduplication in service
- Model relationships

### 13.2 Feature Tests
```php
public function test_articles_endpoint_returns_paginated_json()
{
    Article::factory()->count(25)->create();
    
    $response = $this->getJson('/api/v1/articles');
    
    $response->assertStatus(200)
             ->assertJsonStructure([
                 'data' => ['*' => ['id', 'title', 'source']],
                 'meta' => ['current_page', 'total']
             ]);
}

public function test_search_filter_returns_relevant_results()
{
    Article::factory()->create(['title' => 'Tech News']);
    Article::factory()->create(['title' => 'Sports Update']);
    
    $response = $this->getJson('/api/v1/articles?q=tech');
    
    $this->assertCount(1, $response->json('data'));
}
```

### 13.3 Manual Testing
```bash
# Aggregation
php artisan news:aggregate

# API endpoints
curl localhost:8080/api/v1/articles
curl "localhost:8080/api/v1/articles?q=technology"
curl "localhost:8080/api/v1/articles?source=guardian"
```

---

## 14. Monitoring & Logging

### 14.1 Application Logs
**Location:** `storage/logs/laravel.log`

**Log Levels:**
- `emergency`: System unusable
- `critical`: Job failures after all retries
- `error`: API integration errors
- `warning`: Single article save failures
- `info`: Successful aggregation runs

### 14.2 Queue Monitoring
- Laravel Horizon dashboard: `/horizon`
- View pending, processing, completed, failed jobs
- Monitor queue throughput

### 14.3 Debug Tools
- Laravel Telescope: `/telescope` (development only)
- View all HTTP requests, queries, jobs, exceptions

---

## Appendix A: Glossary

| Term | Definition |
|------|------------|
| ERD | Entity Relationship Diagram |
| FULLTEXT | MySQL index type for natural language search |
| Adapter Pattern | Design pattern that allows incompatible interfaces to work together |
| Deduplication | Process of eliminating duplicate records |
| N+1 Problem | Database performance issue where N additional queries are made |
| Eager Loading | Loading related data upfront to prevent N+1 queries |
| Queue | Background job processing system |
| Pagination | Dividing large dataset into pages |

---

**Document Version:** 1.0  
**Last Updated:** March 2026  
**Status:** Ready for Implementation
