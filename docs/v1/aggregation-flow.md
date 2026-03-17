# News Aggregator Backend – Data Aggregation Flow

## 1. Overview

The news aggregator periodically collects articles from multiple external news APIs and stores them in a local database.

This process ensures that the system maintains an updated dataset of articles that can be efficiently queried by the API layer.

The aggregation workflow consists of:

- scheduled execution
- fetching articles from external APIs
- normalizing article data
- deduplicating articles
- persisting new records

---

## 2. Aggregation Pipeline

The data aggregation pipeline is triggered by the Laravel scheduler.

Flow overview:
```
External APIs
   ├─ NewsAPI
   ├─ The Guardian
   └─ New York Times
          │
          ▼
Scheduler Trigger (Daily)
          │
          ▼
FetchArticlesJob
          │
          ▼
News Aggregation Service
          │
          ▼
Source Adapters
          │
          ▼
Article Normalization
          │
          ▼
Deduplication
          │
          ▼
Database Persistence
```

## 3. Scheduler

The system uses Laravel's built-in scheduler to run the aggregation job periodically.

Example scheduler configuration:
```php
$schedule->job(new FetchArticlesJob())->daily();
```

Responsibilities of the scheduler:
- trigger article ingestion
- ensure consistent data updates
- run the aggregation pipeline automatically

## 4. Fetch Articles Job

The scheduler triggers a background job responsible for orchestrating the aggregation process.

Location:
```
app/Jobs/FetchArticlesJob
```
Responsibilities:
- initiate aggregation
- iterate through configured sources
- call source adapters
- pass results to the aggregation service

Simplified workflow:
```
FetchArticlesJob
    ↓
call AggregationService
```

## 5. News Aggregation Service

The aggregation service coordinates the fetching and processing of articles from all configured sources.

Responsibilities:
- execute all source adapters
- normalize incoming article data
- apply deduplication rules
- store articles in the database

Location:
```
app/Services/Aggregation/NewsAggregationService
```

## 6. Source Adapters

Each news provider has its own API structure.
To isolate integration logic, each provider is implemented using an adapter.

Adapters include:
```
NewsApiAdapter
GuardianAdapter
NYTimesAdapter
```

All adapters implement a shared interface:

```NewsSourceInterface```

Responsibilities of adapters:
- call external APIs
- transform responses into a common structure
- return article collections

## 7. Article Normalization

Since different APIs return different field names, responses are transformed into a unified internal schema.

Example field mapping:

|Source	|Field	|Normalized Field|
|----|----|----|
NewsAPI	|title	|title
Guardian|	webTitle|	title
NYTimes	|headline.main|	title

Normalized article structure:
```
title
description
content
author
url
image_url
published_at
source
category
```
This ensures that the system stores articles consistently regardless of their origin.

## 8. Deduplication Strategy

News aggregators may receive duplicate articles due to:
- repeated ingestion runs
- overlapping content from multiple sources
- To prevent duplicates, the system generates a hash from the article URL.

```url_hash = md5(url)```

The database enforces a unique constraint on url_hash.

Process:
-Generate hash from article URL
-Check if the hash exists in the database
-Insert article only if it does not exist

## 9. Database Persistence

Once articles are normalized and validated, they are stored in the database.

Tables involved:
```
sources
articles
categories
article_category
```
Steps:
-Insert or identify the source
-Insert article record
-Attach categories using pivot table

## 10. Error Handling Strategy

External API integrations may fail due to:
- network issues
- API rate limits
- temporary service outages

The aggregation process handles errors gracefully:
- failed sources do not stop the entire pipeline
- errors are logged
- processing continues with other sources

This ensures resilience in the ingestion process.

## 11. Example Aggregation Flow

Example scenario:
1. Scheduler triggers the aggregation job.
2. The job invokes the aggregation service.
3. The service calls all configured adapters.
4. Each adapter fetches articles from its respective API.
5. Articles are normalized into the internal schema.
6. URL hashes are generated for deduplication.
7. New articles are stored in the database.
8. Categories are associated via the pivot table.

## 12. Summary

The aggregation pipeline ensures that the system continuously collects and stores news articles from multiple external sources.

Key properties of the pipeline:
- automated ingestion through scheduled jobs
- isolated API integrations using adapters
- normalized article data
- deduplication for data integrity
- resilient error handling
