# News Aggregator Backend – Database Design

## 1. Overview

The database schema is designed to support the core functionality of a news aggregator system.  
It stores articles fetched from multiple external news APIs and allows efficient querying for search and filtering.

The design focuses on:

- maintaining a normalized data model
- avoiding duplicate articles
- supporting flexible filtering (source, category, author, date)

The database uses **MySQL/MariaDB**.

---

## 2. Entity Relationship Diagram
```text
sources
   │
   │ 1
   │
   └───────< articles >───────┐
                               │
                               │
                               ▼
                        article_category
                               ▲
                               │
                               │
                          categories
```
---

## 3. Tables

### 3.1 sources

Stores the list of external news providers.

| Column | Type | Description |
|------|------|-------------|
id | bigint | primary key |
name | string | source name |
code | string | unique identifier for the source |
base_url | string | base API URL |
created_at | timestamp | record creation |
updated_at | timestamp | record update |

Example records:

| id | name | code |
|---|---|---|
1 | NewsAPI | newsapi |
2 | The Guardian | guardian |
3 | New York Times | nytimes |

---

### 3.2 articles

Stores normalized article data aggregated from external sources.

| Column | Type | Description |
|------|------|-------------|
id | bigint | primary key |
source_id | bigint | reference to source |
external_id | string | ID from the source API |
title | string | article title |
description | text | short description |
content | text | article content |
author | string | article author |
url | string | original article URL |
url_hash | string | hash used for deduplication |
image_url | string | article image |
published_at | datetime | publication date |
created_at | timestamp | record creation |
updated_at | timestamp | record update |

---

### Deduplication Strategy

Articles may appear multiple times due to:

- repeated API ingestion
- overlapping sources

To prevent duplicates, the system generates:

url_hash = md5(url)

The `url_hash` column has a **unique constraint**, ensuring the same article is not stored multiple times.

---

### Index Strategy

Indexes are added to improve query performance.

| Index | Purpose |
|------|---------|
source_id | filter by source |
author | filter by author |
published_at | filter by date |
url_hash | deduplication |

---

### 3.3 categories

Stores article categories.

| Column | Type | Description |
|------|------|-------------|
id | bigint | primary key |
name | string | category name |
slug | string | URL-friendly identifier |
created_at | timestamp | record creation |
updated_at | timestamp | record update |

Example categories:

| id | name |
|---|---|
1 | Technology |
2 | Business |
3 | Politics |

---

### 3.4 article_category

Pivot table for the many-to-many relationship between articles and categories.

| Column | Type | Description |
|------|------|-------------|
article_id | bigint | reference to article |
category_id | bigint | reference to category |

Primary key:
(article_id, category_id)

This design allows an article to belong to multiple categories.

---

## 4. Relationships

| Relationship | Description |
|------|-------------|
Source → Articles | one-to-many |
Articles → Categories | many-to-many |
Categories → Articles | many-to-many |

---

## 5. Example Data Flow

When a new article is fetched from an external API:

1. The adapter retrieves article data from the API.
2. The aggregation service normalizes the data.
3. The system generates a `url_hash`.
4. The database checks if the hash already exists.
5. If not, the article is inserted into the `articles` table.
6. Associated categories are inserted into `article_category`.

---

## 6. Design Considerations

### Data Normalization

The schema separates:

- sources
- articles
- categories

to reduce redundancy and maintain flexibility.

---

### Flexible Filtering

This schema supports efficient filtering by:

- source
- category
- author
- publication date

---

### Scalability

While the current schema is minimal, it allows future improvements such as:

- tagging systems
- user personalization
- article ranking or scoring

without major structural changes.

---

## 7. Summary

The database schema supports the main requirements of the news aggregator:

- aggregating articles from multiple sources
- storing normalized article data
- preventing duplicates
- enabling efficient search and filtering


