# News Aggregator Backend – Architecture

## 1. Overview

This project implements a backend service for a news aggregator platform.  
The system collects articles from multiple external news APIs, stores them locally, and exposes them through a RESTful API for frontend applications.

The primary goals of this architecture are:

- Aggregate news articles from multiple sources
- Normalize and store article data in a consistent schema
- Provide a flexible API for searching and filtering articles
- Maintain a simple and maintainable system design

The system is implemented using **Laravel**, with **MySQL/MariaDB** as the database and **Docker** for the development environment.

---

## 2. High-Level Architecture

The system consists of three main layers:

1. External Data Sources
2. Data Aggregation Layer
3. API Layer

---
```test
External News APIs
   ├─ NewsAPI
   ├─ The Guardian
   └─ New York Times
          │
          ▼
News Source Adapters
          │
          ▼
Aggregation Service
          │
          ▼
Normalization & Deduplication
          │
          ▼
Database (MySQL / MariaDB)
          │
          ▼
REST API (Laravel Controllers)
          │
          ▼
Frontend Application
```
---

## 3. System Components

### 3.1 News Source Adapters

Each external API returns data in a different format.  
To handle this, the system uses an **adapter pattern** to isolate integration logic for each source.

Each adapter is responsible for:

- Fetching articles from an external API
- Mapping source-specific fields to the internal article structure

Example adapters:

- `NewsApiAdapter`
- `GuardianAdapter`
- `NYTimesAdapter`

All adapters implement a common interface:

---

## 3. System Components

### 3.1 News Source Adapters

Each external API returns data in a different format.  
To handle this, the system uses an **adapter pattern** to isolate integration logic for each source.

Each adapter is responsible for:

- Fetching articles from an external API
- Mapping source-specific fields to the internal article structure

Example adapters:

- `NewsApiAdapter`
- `GuardianAdapter`
- `NYTimesAdapter`

All adapters implement a common interface:

This design ensures that adding a new news provider requires minimal changes to the rest of the system.

---

### 3.2 Aggregation Service

The **Aggregation Service** coordinates the process of collecting articles from all configured sources.

Responsibilities:

- Invoke all source adapters
- Normalize incoming article data
- Apply deduplication rules
- Persist articles to the database

This logic is implemented in:

app/Services/Aggregation/NewsAggregationService

---

### 3.3 Normalization Layer

Since each API has a different schema, article data must be transformed into a **common internal format**.

Example mappings:

| Source | Field | Internal Field |
|------|------|------|
NewsAPI | `title` | `title`
Guardian | `webTitle` | `title`
NYTimes | `headline.main` | `title`

Normalized article structure:

- title
- description
- content
- author
- url
- image_url
- published_at
- source
- category

This ensures the application can query and display articles consistently regardless of their source.

---

### 3.4 Deduplication Strategy

News aggregators may receive the same article multiple times from:

- repeated API pulls
- overlapping data sources

To prevent duplicates, the system generates a **URL hash**:

url_hash = md5(url)

The database enforces uniqueness using a unique constraint on url_hash.

This ensures that the same article is stored only once.

3.5 Scheduler and Background Jobs

The system periodically fetches articles from external APIs using the Laravel scheduler.

Workflow:

```text
Scheduler Trigger (daily)
        ↓
FetchArticlesJob
        ↓
Aggregation Service
        ↓
Database Update
```

The scheduled job ensures the system keeps its article database up to date with minimal manual intervention.

3.6 REST API Layer

The REST API provides endpoints for the frontend application to access aggregated news articles.

Supported capabilities:

Article listing

Search

Filtering

Pagination

Example endpoint:

```http
GET /api/v1/articles
```

Query parameters:

```text
q        → search keyword
source   → filter by source
category → filter by category
author   → filter by author
from     → start date
to       → end date
page     → pagination page
per_page → number of items per page
```

User preferences mentioned in the challenge are implemented as query parameters, allowing the frontend to request personalized results without requiring user authentication.

4. Technology Stack

|Component	| Technology |
|----|-----|
Backend Framework |	Laravel
Database	| MySQL / MariaDB
Containerization |	Docker
HTTP Client	| Laravel HTTP Client
Scheduler	| Laravel Task Scheduler

5. Design Principles

The system follows several software engineering best practices:

DRY (Don't Repeat Yourself)

Shared logic such as article normalization and aggregation is centralized in service classes.

KISS (Keep It Simple, Stupid)

The architecture avoids unnecessary complexity such as microservices or event streaming.

SOLID Principles

Key SOLID principles are applied:

Single Responsibility – adapters, services, and controllers each have distinct roles.

Open/Closed Principle – new news sources can be added without modifying existing logic.

Dependency Inversion – the system relies on interfaces rather than concrete implementations.

---
6. Scalability Considerations

While the system is intentionally simple for the scope of the challenge, the architecture allows future improvements such as:

adding more news sources

caching frequently accessed queries

increasing ingestion frequency

implementing queue workers for parallel data fetching

---
7. Summary

This architecture separates the responsibilities of:

- data ingestion
- data normalization
- persistence
- API delivery

The result is a backend system that is:
- maintainable
- extensible
- easy to reason about
- aligned with the requirements of the case study.

