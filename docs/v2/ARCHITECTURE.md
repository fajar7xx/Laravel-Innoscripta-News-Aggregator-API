# Architecture Overview

## System Design

```
External APIs (NewsAPI, Guardian, NYT)
    ↓
Source Adapters (normalize different API formats)
    ↓
NewsAggregationService (orchestrate fetching)
    ↓
Database (MariaDB with deduplication)
    ↓
REST API (Laravel controllers)
    ↓
Frontend / API Consumers
```

## Key Components

### 1. Adapter Pattern
Each news source has different API structure. We use adapters to normalize responses.

```php
interface NewsSourceInterface {
    public function fetch(): array;
    public function normalize(array $data): array;
}
```

**Why?** Adding a new source only requires creating a new adapter. No changes to core logic.

### 2. Aggregation Service
Central service that:
- Calls all adapters
- Handles per-source errors (one failing source doesn't stop others)
- Saves articles with deduplication
- Updates source sync timestamp

**Why?** Single responsibility. Clean separation between fetching and storing.

### 3. Background Jobs
Articles are fetched asynchronously via Redis queue.

```
Scheduler (hourly) → Dispatches Jobs → Queue Workers → Fetch & Store
```

**Why?** Don't block API responses. Can retry failed jobs. Scalable.

## Critical Design Decisions

### Decision 1: Deduplication Strategy

**Problem:** Same article might be fetched multiple times (scheduler reruns, overlapping date ranges).

**Options considered:**
1. Hash article URL → ❌ URLs change (utm params, redirects)
2. Hash content → ❌ Articles get updated/corrected
3. Use API-provided ID → ✅ **CHOSEN**

**Implementation:**
```sql
UNIQUE KEY (source_id, external_id)
```

Where `external_id` is:
- Guardian: `id` field (stable)
- NYT: `_id` field (stable)
- NewsAPI: `md5(url)` (no stable ID provided)

**Trade-off:** NewsAPI deduplication less reliable, but acceptable for MVP.

---

### Decision 2: Search Implementation

**Problem:** Need full-text search on title, description, content.

**Options considered:**
1. MySQL FULLTEXT index → ✅ **CHOSEN**
2. Elasticsearch → ❌ Overkill for MVP
3. `LIKE` queries → ❌ Too slow

**Implementation:**
```sql
FULLTEXT INDEX ft_search (title, description, content)
```

```php
Article::whereFullText(['title', 'description', 'content'], $keyword)
```

**Trade-off:** 
- FULLTEXT works well for <1M articles
- No typo correction or advanced ranking
- Can migrate to Elasticsearch later if needed

**Why FULLTEXT over Elasticsearch?**
- Simpler setup (no extra service)
- Fast enough for case study scope
- Can ship today vs spending days configuring ES

---

### Decision 3: Queue vs Sync Processing

**Problem:** Fetching from 3 APIs can take 10-30 seconds.

**Options considered:**
1. Synchronous (wait for all APIs) → ❌ Blocks scheduler
2. Queue jobs per source → ✅ **CHOSEN**

**Implementation:**
```php
// Scheduler dispatches 3 jobs
FetchArticlesJob::dispatch('newsapi');
FetchArticlesJob::dispatch('guardian');
FetchArticlesJob::dispatch('nytimes');
```

Each job:
- Runs independently
- Has own retry logic (3 attempts, exponential backoff)
- Doesn't affect other sources if it fails

**Trade-off:** 
- More complex (needs Redis + queue workers)
- But much more resilient and scalable

---

### Decision 4: Scheduling Frequency

**Problem:** How often to fetch new articles?

**Options considered:**
1. Real-time (webhook/polling) → ❌ Most news APIs don't support webhooks
2. Every 15 minutes → ❌ Wastes API quota
3. Hourly → ✅ **CHOSEN**
4. Daily → ❌ Too stale for news

**Implementation:**
```php
$schedule->command('news:aggregate')->hourly();
```

**Trade-off:**
- NewsAPI free tier: 100 requests/day → 72 used (safe)
- Guardian free tier: 500 requests/day → 72 used (safe)
- NYT free tier: 1000 requests/day → 72 used (safe)

News from 1 hour ago is acceptable freshness.

---

### Decision 5: Category Handling

**Problem:** Each API has different category systems.

**Options considered:**
1. Store API's category as-is → ❌ Inconsistent
2. Map to internal categories → ✅ **CHOSEN**
3. Use machine learning to categorize → ❌ Overkill

**Implementation:**
- 8 predefined categories (Technology, Business, Politics, etc.)
- Adapters map API categories to our categories
- Articles can have multiple categories (many-to-many)

**Trade-off:** 
- Some articles won't have perfect category match
- Manual mapping needed for each source
- But users get consistent experience

---

## Data Flow Example

### Hourly Aggregation Run

1. **Scheduler triggers** (every hour)
   ```
   php artisan schedule:run
   ```

2. **Command dispatches jobs**
   ```php
   AggregateNewsCommand → Dispatch 3 jobs to queue
   ```

3. **Queue worker picks up job**
   ```php
   FetchArticlesJob(source='guardian')
   ```

4. **Adapter fetches from API**
   ```php
   GuardianAdapter::fetch()
   → HTTP GET https://content.guardianapis.com/search
   → Returns 100 articles
   ```

5. **Normalize response**
   ```php
   GuardianAdapter::normalize()
   → Maps 'webTitle' to 'title'
   → Maps 'id' to 'external_id'
   → Maps 'sectionName' to category
   ```

6. **Service saves to DB**
   ```php
   NewsAggregationService::saveArticle()
   → updateOrCreate(['source_id' => 2, 'external_id' => 'article-123'])
   → Skips if already exists (deduplication)
   → Syncs categories via pivot table
   ```

7. **Update source timestamp**
   ```sql
   UPDATE sources SET last_fetched_at = NOW() WHERE id = 2
   ```

8. **Job completes**
   - If successful: Job removed from queue
   - If failed: Retry with backoff (30s, 2m, 10m)
   - After 3 failures: Move to failed jobs table

---

## Error Handling Strategy

### Adapter Level
```php
try {
    $response = Http::timeout(30)->get($url);
} catch (Exception $e) {
    Log::error("Guardian API failed", ['error' => $e->getMessage()]);
    throw $e; // Let job retry
}
```

### Service Level
```php
foreach ($articles as $articleData) {
    try {
        $this->saveArticle($source, $articleData);
    } catch (Exception $e) {
        Log::warning("Failed to save article", ['url' => $articleData['url']]);
        continue; // Don't stop entire batch
    }
}
```

### Job Level
```php
public $tries = 3;
public $backoff = [30, 120, 600]; // 30s, 2min, 10min

public function failed(Throwable $e) {
    Log::critical("All retries exhausted", ['source' => $this->source]);
}
```

**Philosophy:** Fail gracefully. One bad article shouldn't stop the whole aggregation.

---

## Performance Considerations

### Database Indexes
```sql
-- Articles table
INDEX idx_source_id (source_id)
INDEX idx_category_id (category_id)  
INDEX idx_published_at (published_at DESC)
INDEX idx_source_published (source_id, published_at DESC)
FULLTEXT INDEX ft_search (title, description, content)
```

These indexes optimize:
- Filtering by source: `WHERE source_id = ?`
- Filtering by category: `WHERE category_id = ?`
- Sorting by date: `ORDER BY published_at DESC`
- Combined filters: `WHERE source_id = ? ORDER BY published_at DESC`
- Search: `MATCH(...) AGAINST(?)`

### Query Optimization
```php
// ✅ Good - Eager load relationships
Article::with(['source', 'category', 'categories'])
    ->whereFullText(['title'], $keyword)
    ->paginate(20);

// ❌ Bad - N+1 queries
Article::all()->each(fn($a) => $a->source); // Queries DB 100 times
```

### Caching (Future Enhancement)
Not implemented in MVP, but can add:
```php
Cache::remember("articles:{$cacheKey}", 300, fn() => 
    Article::where(...)->paginate(20)
);
```

---

## Testing Strategy

### Unit Tests
- Adapter normalization logic
- Deduplication in service
- Article model relationships

### Feature Tests  
- API endpoints return correct structure
- Filtering works (source, category, date)
- Pagination metadata correct
- Search returns relevant results

### Manual Testing
```bash
# Fetch articles manually
php artisan news:aggregate

# Check results
php artisan tinker
>>> Article::count()
>>> Article::where('source_id', 1)->count()
```

---

## Scalability Path

**Current (MVP):**
- Single server
- MySQL FULLTEXT search
- Redis queue with 3 workers
- ~10K articles capacity

**Future (if needed):**
- Load balancer + multiple app servers
- Elasticsearch for search (better relevance, typo correction)
- Separate read replicas for database
- CDN for API responses
- Cache layer (Redis) for frequent queries

**Rule:** Don't optimize prematurely. Ship MVP, scale when metrics demand it.

---

## Why This Architecture?

**Principles applied:**
- ✅ **KISS:** Simple adapter pattern, no microservices overhead
- ✅ **DRY:** Shared interface, reusable normalization logic
- ✅ **SOLID:** Single responsibility (adapter vs service vs controller)
- ✅ **Separation of Concerns:** Data fetching ≠ API layer ≠ storage

**Result:** 
- Easy to test (mock adapters)
- Easy to extend (add new sources)
- Easy to maintain (clear boundaries)
- Easy to understand (new dev can navigate in 30 min)

---

## Interview Discussion Points

Be ready to explain:
1. Why `external_id` over `url_hash`?
2. Why MySQL FULLTEXT over Elasticsearch?
3. Why adapter pattern vs direct API calls in service?
4. How does retry mechanism work?
5. What happens if Guardian API is down for 2 hours?
6. How would you add a 4th news source (e.g., BBC)?
7. How does deduplication prevent duplicate articles?

**Pro tip:** Always frame answers as trade-offs, not absolutes.
- "I chose X because of Y, but Z would be better if we had more time/budget/scale"
