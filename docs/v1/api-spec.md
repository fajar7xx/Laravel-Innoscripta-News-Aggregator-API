# News Aggregator Backend – API Specification

## 1. Overview

This document defines the REST API endpoints provided by the News Aggregator backend.

The API allows frontend applications to retrieve news articles aggregated from multiple external sources.

Capabilities include:

- retrieving articles
- searching articles
- filtering by source, category, author, and date
- pagination support

Base URL:

/api/v1

## 2. Response Format

All API responses follow a consistent JSON structure.
```
Example:
{
  "data": [],
  "meta": {},
  "links": {}
}
```

### Fields
|Field |	Description |
|-----|----|
data |	response payload
meta |	pagination metadata
links |	pagination links

## 3. Endpoints
### 3.1 Get Articles

Retrieve a paginated list of aggregated news articles.
```
GET /api/v1/articles
```

### Query Parameters
|Parameter	| Type	| Description|
|----|----|----|
q	| string|	search keyword
source|	string|	filter by source
category|	string|	filter by category
author|	string|	filter by author
from|	date|	start date filter
to|	date|	end date filter
page|	integer|	page number
per_page|	integer|	number of items per page

### Example Request
```http
GET /api/v1/articles?q=ai&source=guardian&category=technology&page=1&per_page=20
```

### Example Response
```json
{
  "data": [
    {
      "id": 12,
      "title": "AI Breakthrough in Healthcare",
      "description": "A new AI model shows promising results...",
      "author": "John Doe",
      "url": "https://example.com/article",
      "image_url": "https://example.com/image.jpg",
      "published_at": "2026-03-14T10:00:00Z",
      "source": {
        "id": 2,
        "name": "The Guardian",
        "code": "guardian"
      },
      "categories": [
        {
          "id": 1,
          "name": "Technology",
          "slug": "technology"
        }
      ]
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

### 3.2 Get Article Detail

Retrieve a single article by ID.
```http
GET /api/v1/articles/{id}
```

Example Request
```http
GET /api/v1/articles/12
````

Example Response
```json
{
  "data": {
    "id": 12,
    "title": "AI Breakthrough in Healthcare",
    "description": "A new AI model shows promising results...",
    "content": "Full article content...",
    "author": "John Doe",
    "url": "https://example.com/article",
    "image_url": "https://example.com/image.jpg",
    "published_at": "2026-03-14T10:00:00Z",
    "source": {
      "id": 2,
      "name": "The Guardian",
      "code": "guardian"
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

### 3.3 Get Sources

Retrieve the list of available news sources.
```http
GET /api/v1/sources
```

Example Response
```json
{
  "data": [
    {
      "id": 1,
      "name": "NewsAPI",
      "code": "newsapi"
    },
    {
      "id": 2,
      "name": "The Guardian",
      "code": "guardian"
    },
    {
      "id": 3,
      "name": "New York Times",
      "code": "nytimes"
    }
  ]
}
```

### 3.4 Get Categories

Retrieve available article categories.
```http
GET /api/v1/categories
```

Example Response

```json
{
  "data": [
    {
      "id": 1,
      "name": "Technology",
      "slug": "technology"
    },
    {
      "id": 2,
      "name": "Business",
      "slug": "business"
    }
  ]
}
```

## 4. Search and Filtering

The API supports flexible filtering through query parameters.

Examples:

Search by keyword
```http
GET /articles?q=ai
```

Filter by source
```http
GET /articles?source=guardian
```

Filter by category
```http
GET /articles?category=technology
```

Filter by author
```http
GET /articles?author=john
```

Filter by date range
```http
GET /articles?from=2026-03-01&to=2026-03-14
```

### 5. Pagination

Pagination follows Laravel's standard pagination format.

Parameters:
```
page
per_page
```

Example:
```http
GET /articles?page=2&per_page=20
```

## 6. Error Handling

The API returns appropriate HTTP status codes.

|Status Code|	Description|
|-----|-----|
200	|successful request
404	|resource not found
422	|invalid request parameters
500	|internal server error

Example error response:
```json
{
  "message": "Resource not found"
}
```

## 7. User Preferences Handling

The challenge mentions support for user preferences such as selected sources, categories, and authors.

Since authentication is not included in the scope, preferences are implemented as request-level query parameters rather than stored user settings.

This allows the frontend to dynamically request personalized results without maintaining user state.

Example:
```http
GET /articles?source=guardian&category=technology
```

## 8. Summary

The API is designed to be:
- RESTful
- consistent in response structure
- flexible for filtering and searching
- simple to integrate with frontend applications
