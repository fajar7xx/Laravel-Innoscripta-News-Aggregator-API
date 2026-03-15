# News Aggregator Backend

A backend service that aggregates news articles from multiple external APIs and exposes them through a RESTful API.

The system periodically fetches articles from multiple news providers, stores them locally, and allows frontend applications to search and filter the aggregated content.

---

# Overview

This service collects news articles from multiple external sources:

* NewsAPI
* The Guardian
* New York Times

Articles are normalized into a consistent internal format and stored in a relational database. The API layer allows clients to retrieve and filter articles efficiently.

Core capabilities:

* Aggregate news from multiple external APIs
* Normalize heterogeneous API responses
* Prevent duplicate article storage
* Provide RESTful endpoints for querying articles
* Support flexible filtering and search
* Scheduled article ingestion

---

# Documentation

Before implementing the system, several design documents were created to define the architecture, data model, API contract, and aggregation workflow.

These documents can be found in the `docs/` directory:

| Document                 | Description                              |
| ------------------------ | ---------------------------------------- |
| docs/architecture.md     | System architecture and component design |
| docs/erd.md              | Database schema and entity relationships |
| docs/api-spec.md         | REST API specification                   |
| docs/aggregation-flow.md | Data ingestion and aggregation pipeline  |

Task breakdown used during development is available in:

```
docs/tasks/
```

Each task document corresponds to a development issue used to implement the system incrementally.

---

# Architecture

High-level system flow:
```
External APIs
→ Source Adapters
→ Aggregation Service
→ Normalization & Deduplication
→ Database
→ REST API
```

Key components:

**Source Adapters**

Each news provider has a dedicated adapter responsible for fetching and mapping API responses.

Adapters implemented:

* NewsApiAdapter
* GuardianAdapter
* NYTimesAdapter

---

**Aggregation Service**

The aggregation service orchestrates the process of collecting and storing articles.

Responsibilities:

* call external news APIs through adapters
* normalize article data
* deduplicate articles
* persist articles to the database

---

**Scheduler**

The system uses Laravel's scheduler to periodically ingest articles.

Workflow:

Scheduler
→ FetchArticlesJob
→ Aggregation Service
→ Database update

---

# API Endpoints

Base path:

```
/api/v1
```

### Health Check

```
GET /api/health
```

Response:

```json
{
    "status": "OK",
    "service": "news-aggregator-backend-api",
    "version": "1.0.0",
    "timestamp": "2026-03-15T00:00:00+00:00"
}
```

---

### Get Articles

Retrieve paginated articles.

```
GET /api/v1/articles
```

Query parameters:

| Parameter | Description        |
| --------- | ------------------ |
| q         | search keyword     |
| source    | filter by source   |
| category  | filter by category |
| author    | filter by author   |
| from      | start date         |
| to        | end date           |
| page      | page number        |
| per_page  | number of results  |

---

### Get Article Detail

```
GET /api/v1/articles/{id}
```

---

### Get Sources

```
GET /api/v1/sources
```

---

### Get Categories

```
GET /api/v1/categories
```

---

# Technology Stack

| Component        | Technology             |
| ---------------- | ---------------------- |
| Backend          | Laravel 12             |
| Database         | MariaDB 11             |
| Cache / Queue    | Redis                  |
| Web Server       | Nginx                  |
| Containerization | Docker                 |
| HTTP Client      | Laravel HTTP Client    |
| Scheduler        | Laravel Task Scheduler |

---

# Docker Services

| Service  | Description                        | Port (host) |
| -------- | ---------------------------------- | ----------- |
| app      | PHP 8.4-FPM (Laravel application)  | —           |
| nginx    | Web server (reverse proxy to app)  | 8080        |
| mariadb  | Database                           | 3307        |
| redis    | Cache & queue driver               | 6380        |
| queue    | Horizon worker                     | —           |

---

# Setup Instructions

### 1. Clone Repository

```bash
git clone <repository-url>
cd news-aggregator-backend
```

---

### 2. Configure Environment

```bash
cp .env.example .env
```

Update the following values in `.env` before starting Docker:

```env
DB_DATABASE=innoscripta_news_aggregator
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
```

---

### 3. Build and Start Docker Environment

```bash
docker compose up -d --build
```

> Subsequent starts (no Dockerfile changes): `docker compose up -d`

---

### 4. Install PHP Dependencies

```bash
docker compose exec app composer install
```

> The volume mount at runtime replaces the image's `/var/www` with your local directory. Running `composer install` inside the container ensures `vendor/` is populated.

---

### 5. Generate Application Key

```bash
docker compose exec app php artisan key:generate
```

---

### 6. Run Migrations

```bash
docker compose exec app php artisan migrate
```

---

### 7. Access the Application

```
http://localhost:8080
```

---

# Docker Commands

```bash
# View running containers
docker compose ps

# Follow application logs
docker compose logs -f app

# Open shell inside app container
docker compose exec app bash

# Run Artisan commands
docker compose exec app php artisan <command>

# Stop all containers
docker compose down

# Stop and remove volumes (resets database)
docker compose down -v
```

---

# Running the Scheduler

The `queue` container runs **Horizon**, which processes queued jobs from Redis. The Laravel scheduler (for periodic tasks like article ingestion) is a separate process and must be started manually:

```bash
docker compose exec app php artisan schedule:work
```

---

# Summary

This project demonstrates how to design and implement a news aggregation backend with:

* clean architecture
* normalized data ingestion
* extensible API integrations
* flexible search and filtering
* maintainable code structure
