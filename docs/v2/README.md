# News Aggregator Backend API

Laravel-based RESTful API that aggregates news articles from multiple external sources (NewsAPI, The Guardian, New York Times).

## Quick Start

```bash
# Clone & setup
git clone <your-repo-url>
cd Laravel-Innoscripta-News-Aggregator-API
cp .env.example .env

# Add your API keys to .env:
# NEWSAPI_KEY=your_key_here
# GUARDIAN_API_KEY=your_key_here
# NYTIMES_API_KEY=your_key_here

# Start Docker
docker-compose up -d

# Install dependencies
docker-compose exec app composer install

# Setup database
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate --seed

# Fetch articles (manual trigger)
docker-compose exec app php artisan news:aggregate

# Access API
curl http://localhost:8080/api/v1/articles
```

## API Endpoints

### Articles
```http
GET /api/v1/articles
  ?q=keyword               # Search
  &source=guardian         # Filter by source
  &category=technology     # Filter by category
  &author=john            # Filter by author
  &from=2026-03-01        # Date range start
  &to=2026-03-15          # Date range end
  &page=1                 # Pagination
  &per_page=20            # Items per page

GET /api/v1/articles/{id}  # Single article detail
```

### Sources & Categories
```http
GET /api/v1/sources        # List all news sources
GET /api/v1/categories     # List all categories
```

### Example Response
```json
{
  "data": [
    {
      "id": 1,
      "title": "AI Breakthrough in Healthcare",
      "description": "A new model shows promising results...",
      "url": "https://example.com/article",
      "published_at": "2026-03-14T10:00:00Z",
      "source": {
        "id": 2,
        "name": "The Guardian",
        "slug": "guardian"
      },
      "category": {
        "id": 1,
        "name": "Technology",
        "slug": "technology"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8
  }
}
```

## Tech Stack

| Component | Technology |
|-----------|------------|
| Backend | Laravel 12 |
| Database | MariaDB 11 |
| Cache/Queue | Redis 7 |
| Container | Docker |
| Queue Monitor | Laravel Horizon |

## Features

✅ Multi-source aggregation (NewsAPI, Guardian, NYT)  
✅ Deduplication (prevents same article from being stored twice)  
✅ Full-text search (MySQL FULLTEXT index)  
✅ Advanced filtering (source, category, author, date range)  
✅ Background jobs (async article fetching via Redis queue)  
✅ Scheduled aggregation (runs hourly via Laravel scheduler)  
✅ Pagination (efficient offset-based pagination)  

## Project Structure

```
app/
├── Console/Commands/
│   └── AggregateNewsCommand.php      # Manual aggregation trigger
├── Http/Controllers/Api/
│   ├── ArticleController.php         # Article endpoints
│   ├── SourceController.php          # Source endpoints
│   └── CategoryController.php        # Category endpoints
├── Jobs/
│   └── FetchArticlesJob.php          # Background article fetching
├── Models/
│   ├── Article.php
│   ├── Source.php
│   └── Category.php
├── Services/
│   ├── NewsAggregationService.php    # Orchestrates fetching
│   └── Adapters/
│       ├── NewsApiAdapter.php        # NewsAPI integration
│       ├── GuardianAdapter.php       # Guardian API integration
│       └── NYTimesAdapter.php        # NYT API integration
└── Contracts/
    └── NewsSourceInterface.php        # Adapter contract
```

## Database Schema

**sources** - News API providers (NewsAPI, Guardian, NYT)  
**categories** - Article categories (Technology, Business, etc.)  
**articles** - Aggregated news articles  
**article_category** - Many-to-many relationship  

**Key constraint:** `UNIQUE(source_id, external_id)` prevents duplicates

## Development

```bash
# Run migrations
docker-compose exec app php artisan migrate

# Seed sources & categories
docker-compose exec app php artisan db:seed

# Run queue worker (for background jobs)
docker-compose exec app php artisan queue:work

# Or use Horizon (with dashboard at /horizon)
docker-compose exec app php artisan horizon

# Manual article fetch
docker-compose exec app php artisan news:aggregate

# View logs
docker-compose logs -f app
```

## Testing

```bash
# Run tests
docker-compose exec app php artisan test

# Test API manually
curl http://localhost:8080/api/v1/articles?q=technology&source=guardian
```

## Environment Variables

Key variables in `.env`:

```env
# Database
DB_CONNECTION=mariadb
DB_DATABASE=innoscripta_news_aggregator

# Queue
QUEUE_CONNECTION=redis

# News API Keys (get from respective providers)
NEWSAPI_KEY=your_newsapi_key
GUARDIAN_API_KEY=your_guardian_key
NYTIMES_API_KEY=your_nytimes_key

# Aggregation Settings
NEWS_AGGREGATION_FREQUENCY=hourly
NEWS_ARTICLES_PER_FETCH=100
```

## Architecture Decisions

See [ARCHITECTURE.md](ARCHITECTURE.md) for design decisions and trade-offs.

## License

This is a case study project for innoscripta Backend Developer position.
