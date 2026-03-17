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
    public function __construct(private array $config = [])
    {
        $this->config = config('news.sources.newsapi');
    }

    /**
     * Fetch raw articles from the NewsAPI /everything endpoint.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws RequestException
     * @throws ConnectionException
     * @throws Exception
     */
    public function fetch(): array
    {
        $baseUrl = $this->config['base_url'];
        $apiKey = $this->config['api_key'];
        $timeout = $this->config['timeout'];
        $endpoint = $this->config['endpoints']['everything'];

        try {
            Log::info('NewsAPI: Fetching articles', [
                'endpoint' => '/everything',
                'fetched_at' => Carbon::now()->toIso8601String(),
            ]);

            $response = Http::baseUrl($baseUrl)
                ->withQueryParameters([
                    'apiKey' => $apiKey,
                    'q' => 'AI',
                    'language' => 'en',
                    'sortBy' => 'publishedAt',
                    'pageSize' => 10,
                    'page' => 1,
                ])->timeout($timeout)
                ->retry(3, 100)
                ->throw()
                ->get($endpoint);

            $data = $response->json();
            $articles = $data['articles'] ?? [];

            Log::info('NewsAPI: fetched articles successfully', [
                'count' => count($articles),
                'total_results' => $data['totalResults'] ?? 0,
            ]);

            return $articles;
        } catch (RequestException $re) {
            Log::error('NewsAPI HTTP error', [
                'status' => $re->response->status(),
                'message' => $re->getMessage(),
            ]);

            return [];
        } catch (ConnectionException $ce) {
            Log::error('NewsAPI connection error', [
                'message' => $ce->getMessage(),
            ]);

            throw $ce;
        } catch (Exception $e) {
            Log::error('NewsAPI adapter error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }

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
}
