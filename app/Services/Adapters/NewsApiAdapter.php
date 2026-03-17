<?php

namespace App\Services\Adapters;

use App\Contracts\NewsSourceInterface;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NewsApiAdapter implements NewsSourceInterface
{
    private const PAGE_SIZE = 100;

    private const MAX_PAGES = 10;

    public function __construct(private array $config = [])
    {
        $this->config = config('news.sources.newsapi');
    }

    /**
     * Fetch all articles published today from the NewsAPI /everything endpoint.
     *
     * Paginates through all available pages until every article for the day
     * is collected or the API limit is reached.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ConnectionException
     * @throws Exception
     */
    public function fetch(): array
    {
        $today = Carbon::today()->toDateString();
        $allArticles = [];
        $page = 1;

        do {
            $result = $this->fetchPage($page, $today);

            if ($result === null) {
                break;
            }

            $allArticles = array_merge($allArticles, $result['articles']);
            $totalResults = $result['totalResults'];
            $page++;

            $hasMorePages = count($allArticles) < $totalResults && ! empty($result['articles']);
        } while ($hasMorePages && $page <= self::MAX_PAGES);

        Log::info('NewsAPI: finished fetching all pages', [
            'total_fetched' => count($allArticles),
            'pages_requested' => $page - 1,
        ]);

        return $allArticles;
    }

    /**
     * Normalize a single raw NewsAPI article into the common article schema.
     *
     * @param  array{
     *     url?: string,
     *     title?: string,
     *     description?: string,
     *     content?: string,
     *     author?: string,
     *     urlToImage?: string,
     *     publishedAt?: string,
     * }  $data
     * @return array{
     *     external_id: string,
     *     title: string,
     *     description: string|null,
     *     content: string|null,
     *     author: string|null,
     *     url: string,
     *     image_url: string|null,
     *     published_at: string,
     *     categories: array<string>,
     * }
     */
    public function normalize(array $data): array
    {
        if (empty($data['url'])) {
            return [];
        }

        $externalId = md5($data['url']);
        $publishedAt = isset($data['publishedAt']) ? Carbon::parse($data['publishedAt']) : Carbon::now();
        $categories = ['world'];

        return [
            'external_id' => $externalId,
            'title' => $data['title'] ?? 'Untitled',
            'description' => $data['description'] ?? null,
            'content' => $data['content'] ?? null,
            'author' => $data['author'] ?? null,
            'url' => $data['url'] ?? '',
            'image_url' => $data['urlToImage'] ?? null,
            'published_at' => $publishedAt->toDateTimeString(),
            'categories' => $categories,
        ];
    }

    /**
     * Fetch a single page of results from NewsAPI.
     *
     * Returns null on HTTP errors (rate limit, auth), throws on connection errors.
     *
     * @return array{articles: array<int, mixed>, totalResults: int}|null
     *
     * @throws ConnectionException
     * @throws Exception
     */
    private function fetchPage(int $page, string $date): ?array
    {
        $baseUrl = $this->config['base_url'];
        $apiKey = $this->config['api_key'];
        $timeout = $this->config['timeout'];
        $endpoint = $this->config['endpoints']['everything'];

        try {
            Log::info('NewsAPI: fetching page', [
                'page' => $page,
                'date' => $date,
            ]);

            $response = Http::baseUrl($baseUrl)
                ->withQueryParameters([
                    'apiKey' => $apiKey,
                    'q' => 'AI',
                    'language' => 'en',
                    'from' => $date,
                    'to' => $date,
                    'sortBy' => 'publishedAt',
                    'pageSize' => self::PAGE_SIZE,
                    'page' => $page,
                ])->timeout($timeout)
                ->retry(3, 100)
                ->throw()
                ->get($endpoint);

            $data = $response->json();

            return [
                'articles' => $data['articles'] ?? [],
                'totalResults' => $data['totalResults'] ?? 0,
            ];
        } catch (RequestException $re) {
            Log::error('NewsAPI HTTP error', [
                'page' => $page,
                'status' => $re->response->status(),
                'message' => $re->getMessage(),
            ]);

            return null;
        } catch (ConnectionException $ce) {
            Log::error('NewsAPI connection error', ['message' => $ce->getMessage()]);

            throw $ce;
        } catch (Exception $e) {
            Log::error('NewsAPI adapter error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
