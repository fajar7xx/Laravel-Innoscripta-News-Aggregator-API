# 3-Day Implementation Roadmap

## Goal: Working MVP + Clean Git History

**What Team Leader evaluates:**
- ✅ Working code (API endpoints functional)
- ✅ Clean commits (multiple commits with clear messages)
- ✅ Problem-solving (edge cases, deduplication, error handling)
- ✅ Tech standards (SOLID, DRY, KISS)
- ✅ Can explain decisions

---

## Day 1: Foundation (8 hours)

### Morning (4 hours): Database Layer

**1.1 Migrations (2h)**
```bash
php artisan make:migration create_sources_table
php artisan make:migration create_categories_table
php artisan make:migration create_articles_table
php artisan make:migration create_article_category_table
```

Key: Add deduplication constraint on articles:
```php
$table->unique(['source_id', 'external_id']);
```

**Commit after each migration:**
```bash
git add database/migrations/*sources*
git commit -m "feat: add sources migration with tracking fields"

git add database/migrations/*categories*
git commit -m "feat: add categories migration"

git add database/migrations/*articles*
git commit -m "feat: add articles migration with deduplication"

git add database/migrations/*article_category*
git commit -m "feat: add article_category pivot table"
```

**1.2 Models + Seeders (2h)**
```bash
php artisan make:model Source
php artisan make:model Category
php artisan make:model Article
php artisan make:seeder SourceSeeder
php artisan make:seeder CategorySeeder
```

Seed 3 sources (NewsAPI, Guardian, NYT) and 8 categories.

**Commit:**
```bash
git commit -m "feat: create Source, Category, Article models with relationships"
git commit -m "feat: add seeders for sources and categories"
```

### Afternoon (4 hours): Basic API

**1.3 Controllers + Routes (2h)**
```bash
php artisan make:controller Api/ArticleController
php artisan make:controller Api/SourceController
php artisan make:controller Api/CategoryController
```

Implement:
- `GET /api/v1/articles` (basic list, no filtering yet)
- `GET /api/v1/articles/{id}` (show)
- `GET /api/v1/sources` (list)
- `GET /api/v1/categories` (list)

**Commit:**
```bash
git commit -m "feat: add ArticleController with index and show methods"
git commit -m "feat: add SourceController and CategoryController"
git commit -m "feat: define API routes for v1 endpoints"
```

**1.4 Test Endpoints (1h)**
```bash
# Seed some dummy data manually
php artisan tinker
>>> Article::factory()->count(20)->create()

# Test
curl localhost:8080/api/v1/articles
curl localhost:8080/api/v1/sources
```

**1.5 Documentation (1h)**
- Update README.md with setup instructions
- Add API endpoints documentation

**Commit:**
```bash
git commit -m "docs: add setup instructions and API documentation to README"
```

**End of Day 1:** ✅ Basic CRUD works, API returns data

---

## Day 2: Data Aggregation (8 hours)

### Morning (4 hours): Adapters

**2.1 Interface + Config (30min)**
```bash
mkdir -p app/Contracts
mkdir -p app/Services/Adapters
```

Create `NewsSourceInterface.php`

Update `config/services.php` with API keys.

**Commit:**
```bash
git commit -m "feat: create NewsSourceInterface contract"
git commit -m "config: add news API providers to services config"
```

**2.2 NewsAPI Adapter (1h)**
```bash
# app/Services/Adapters/NewsApiAdapter.php
```

Implement:
- `fetch()` - HTTP call to NewsAPI
- `normalize()` - Map fields to internal structure

**Commit:**
```bash
git commit -m "feat: implement NewsApiAdapter with field normalization"
```

**2.3 Guardian Adapter (1h)**
Same pattern, different API endpoint and field mapping.

**Commit:**
```bash
git commit -m "feat: implement GuardianAdapter with Guardian API integration"
```

**2.4 NYTimes Adapter (1h)**
Same pattern.

**Commit:**
```bash
git commit -m "feat: implement NYTimesAdapter for NYT Article Search API"
```

**Test adapters:**
```bash
php artisan tinker
>>> $adapter = app(\App\Services\Adapters\NewsApiAdapter::class);
>>> $articles = $adapter->fetch();
>>> dd($articles[0]); // Check structure
```

### Afternoon (4 hours): Service + Jobs

**2.5 Aggregation Service (1.5h)**
```bash
# app/Services/NewsAggregationService.php
```

Implement:
- `fetchFromSource(Source $source)` 
- `saveArticle(Source $source, array $data)`

Handle deduplication with `updateOrCreate`.

**Commit:**
```bash
git commit -m "feat: create NewsAggregationService to orchestrate fetching"
git commit -m "feat: implement article deduplication in aggregation service"
```

**2.6 Background Job (1h)**
```bash
php artisan make:job FetchArticlesJob
```

Configure:
- `$tries = 3`
- `$backoff = [30, 120, 600]`
- Call `NewsAggregationService`

**Commit:**
```bash
git commit -m "feat: add FetchArticlesJob with retry mechanism"
```

**2.7 Artisan Command + Scheduler (1h)**
```bash
php artisan make:command AggregateNewsCommand
```

Command dispatches jobs.

Update `app/Console/Kernel.php`:
```php
$schedule->command('news:aggregate')->hourly();
```

**Commit:**
```bash
git commit -m "feat: add news:aggregate command to fetch articles"
git commit -m "feat: configure scheduler to run aggregation hourly"
```

**2.8 Manual Test (30min)**
```bash
# Make sure queue worker is running
php artisan queue:work --once

# Trigger aggregation
php artisan news:aggregate

# Check results
php artisan tinker
>>> Article::count()
>>> Source::find(1)->last_fetched_at
```

**End of Day 2:** ✅ Articles fetching from 3 APIs, stored in DB

---

## Day 3: Polish & Ship (8 hours)

### Morning (4 hours): Search & Filtering

**3.1 Full-Text Search (1h)**
Add search to `ArticleController@index`:
```php
if ($request->q) {
    $query->whereFullText(['title', 'description', 'content'], $request->q);
}
```

**Commit:**
```bash
git commit -m "feat: implement full-text search on articles"
```

**3.2 Filters (1.5h)**
Add filters:
- `source` (by slug)
- `category` (by slug)
- `author` (LIKE query)
- `from` / `to` (date range)

**Commit:**
```bash
git commit -m "feat: add filtering by source, category, author"
git commit -m "feat: add date range filtering for articles"
```

**3.3 Sorting & Pagination (30min)**
- `sort_by` and `sort_order` params
- Pagination already works via `paginate()`

**Commit:**
```bash
git commit -m "feat: add sorting by published_at and title"
git commit -m "feat: implement pagination for articles endpoint"
```

**3.4 Test Combined Filters (1h)**
```bash
curl "localhost:8080/api/v1/articles?q=tech&source=guardian&category=technology&page=1"
```

Test edge cases:
- Empty search results
- Invalid source/category
- Date validation

**Commit:**
```bash
git commit -m "fix: handle empty search results gracefully"
git commit -m "fix: validate date range parameters"
```

### Afternoon (4 hours): Testing & Final Polish

**3.5 Feature Tests (2h)**
```bash
php artisan make:test ArticleApiTest
php artisan make:test SourceApiTest
```

Write tests for:
- GET /api/v1/articles returns 200
- Filtering works correctly
- Pagination metadata present
- Article detail returns correct structure

**Commit:**
```bash
git commit -m "test: add feature tests for article API endpoints"
git commit -m "test: add tests for filtering and pagination"
```

**3.6 Code Review & Cleanup (1h)**
- Remove debug code
- Fix any TODO comments
- Ensure consistent code style
- Add missing docblocks

**Commit:**
```bash
git commit -m "refactor: clean up debug code and add docblocks"
git commit -m "style: fix code formatting and consistency"
```

**3.7 Final Documentation (1h)**
- Update README with all features
- Add ARCHITECTURE.md
- Ensure .env.example has all keys
- Add comments to complex code

**Commit:**
```bash
git commit -m "docs: add architecture decisions to ARCHITECTURE.md"
git commit -m "docs: update README with complete feature list and examples"
```

**End of Day 3:** ✅ Complete MVP ready for submission

---

## Git Commit Best Practices

### Commit Message Format
```
type: brief description (max 50 chars)

Optional detailed explanation if needed.
```

### Types
- `feat:` - New feature
- `fix:` - Bug fix
- `docs:` - Documentation only
- `test:` - Adding tests
- `refactor:` - Code restructuring
- `style:` - Code formatting
- `config:` - Configuration changes

### Examples
```bash
# Good
git commit -m "feat: add deduplication constraint on articles table"
git commit -m "fix: handle missing author field from NewsAPI"
git commit -m "docs: add API endpoint examples to README"

# Bad
git commit -m "update"
git commit -m "fix bug"
git commit -m "done"
```

### Frequency
- Commit every 30-60 minutes
- Minimum 20 commits over 3 days
- Show progression: setup → features → polish

---

## Testing Checklist

Before submission, verify:

**Functionality:**
- [ ] `docker-compose up -d` works
- [ ] `php artisan migrate --seed` runs without errors
- [ ] `php artisan news:aggregate` fetches articles
- [ ] GET /api/v1/articles returns data
- [ ] Search works: `?q=keyword`
- [ ] Filters work: `?source=guardian&category=tech`
- [ ] Pagination works: `?page=2&per_page=20`

**Code Quality:**
- [ ] No debug code (`dd()`, `var_dump()`)
- [ ] No hardcoded values (use config/env)
- [ ] Consistent naming (snake_case for DB, camelCase for PHP)
- [ ] Error handling present (try-catch)
- [ ] Code follows PSR-12

**Git:**
- [ ] 20+ commits with clear messages
- [ ] Commits show logical progression
- [ ] No large "final commit" with all code

**Documentation:**
- [ ] README.md has setup instructions
- [ ] ARCHITECTURE.md explains key decisions
- [ ] .env.example has all required variables
- [ ] Code comments explain complex logic

---

## Time Management Tips

**If running behind:**
1. **Skip nice-to-haves:**
   - API Resources (use raw Eloquent)
   - Extensive tests (prioritize feature tests over unit)
   - Advanced error codes (basic validation OK)

2. **Focus on core:**
   - Working endpoints ✅
   - Article fetching ✅
   - Basic search ✅
   - Clean commits ✅

3. **Use time wisely:**
   - Don't perfect styling
   - Don't over-optimize queries
   - Don't implement features not in requirements

**Remember:** Working code > Perfect code

---

## Final Submission Checklist

**Before pushing:**
- [ ] All tests pass
- [ ] No .env file committed (only .env.example)
- [ ] No API keys in commits
- [ ] README has clear setup instructions
- [ ] docker-compose up works from scratch
- [ ] Horizon/Telescope credentials not exposed

**Submission:**
- [ ] Push to GitHub
- [ ] Ensure all commits visible
- [ ] README renders correctly on GitHub
- [ ] Repository is public (if required)

**Email to recruiter:**
- Link to GitHub repo
- Brief summary of implementation
- Any known limitations
- Time spent: ~24 hours

---

## What Makes You Stand Out

**Code:**
- Adapter pattern for extensibility
- Proper deduplication strategy
- Clean separation of concerns
- Error handling with retries

**Git:**
- Logical commit progression
- Clear, descriptive messages
- No one giant commit

**Documentation:**
- Clear setup instructions
- Architecture decisions explained
- Trade-offs acknowledged

**Bonus (if time permits):**
- Basic feature tests
- Postman collection
- Performance considerations noted

---

## Interview Prep

Be ready to explain:
1. Why adapter pattern?
2. How does deduplication work?
3. What happens if API is down?
4. How would you add a 4th source?
5. How does the queue retry mechanism work?
6. Trade-offs in your design decisions?

**Pro tip:** Frame answers as "I chose X because Y, but Z would be better if [condition]"

Good luck! Ship it! 🚀
