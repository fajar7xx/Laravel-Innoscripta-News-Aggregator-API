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
| Backend          | Laravel                |
| Database         | MySQL / MariaDB        |
| Containerization | Docker                 |
| HTTP Client      | Laravel HTTP Client    |
| Scheduler        | Laravel Task Scheduler |

---

# Setup Instructions

### 1. Clone Repository

```
git clone <repository-url>
cd news-aggregator-backend
```

---

### 2. Start Docker Environment

```
docker compose up -d
```

---

### 3. Install Dependencies

```
docker compose exec app composer install
```

---

### 4. Configure Environment

```
cp .env.example .env
php artisan key:generate
```

---

### 5. Run Migrations

```
php artisan migrate
```

---

# Running the Scheduler

To enable automated article ingestion:

```
php artisan schedule:work
```

---

# Summary

This project demonstrates how to design and implement a news aggregation backend with:

* clean architecture
* normalized data ingestion
* extensible API integrations
* flexible search and filtering
* maintainable code structure
