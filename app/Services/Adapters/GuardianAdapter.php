<?php

namespace App\Services\Adapters;

use App\Contracts\NewsSourceInterface;
use App\Models\Category;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GuardianAdapter implements NewsSourceInterface
{
    private const PAGE_SIZE = 50;

    private const MAX_PAGES = 20;

    /**
     * Create a new class instance.
     */
    public function __construct(private array $config = [])
    {
        $this->config = config('news.sources.guardian');
    }

    /**
     * Fetch all articles published today from The Guardian /search endpoint.
     *
     * Paginates through all available pages until every article for the day
     * is collected or the page limit is reached.
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
            $totalPages = $result['pages'];
            $page++;
        } while ($page <= $totalPages && $page <= self::MAX_PAGES);

        Log::info('Guardian: finished fetching all pages', [
            'total_fetched' => count($allArticles),
            'pages_requested' => $page - 1,
        ]);

        return $allArticles;
    }

    /**
     * Normalize a single raw Guardian article into the common article schema.
     *
     * @param  array{
     *     id?: string,
     *     webUrl?: string,
     *     apiUrl?: string,
     *     webTitle?: string,
     *     webPublicationDate?: string,
     *     sectionId?: string,
     *     sectionName?: string,
     *     fields?: array{
     *         trailText?: string,
     *         bodyText?: string,
     *         byline?: string,
     *         thumbnail?: string,
     *     },
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
        if (empty($data['id'])) {
            return [];
        }

        if (empty($data['apiUrl']) || empty($data['webUrl'])) {
            return [];
        }

        $category = Category::firstOrCreate(
            ['slug' => $data['sectionId']],
            ['name' => $data['sectionName']],
        );

        $fields = $data['fields'] ?? [];

        return [
            'external_id' => $data['id'],
            'title' => $data['webTitle'] ?? 'Untitled',
            'description' => $fields['trailText'] ?? null,
            'content' => $fields['bodyText'] ?? null,
            'author' => $fields['byline'] ?? null,
            'url' => $data['webUrl'] ?? '',
            'image_url' => $fields['thumbnail'] ?? null,
            'published_at' => Carbon::parse($data['webPublicationDate'])->toDateTimeString(),
            'categories' => [$category->slug],
        ];
    }

    /**
     * Fetch a single page of results from The Guardian API.
     *
     * Returns null on HTTP errors, throws on connection errors.
     *
     * @return array{articles: array<int, mixed>, pages: int}|null
     *
     * @throws ConnectionException
     * @throws Exception
     */
    private function fetchPage(int $page, string $date): ?array
    {
        $baseUrl = $this->config['base_url'];
        $apiKey = $this->config['api_key'];
        $timeout = $this->config['timeout'];
        $endpoint = $this->config['endpoints']['search'];

        try {
            Log::info('The Guardian: fetching page', [
                'page' => $page,
                'date' => $date,
            ]);

            $response = Http::baseUrl($baseUrl)
                ->withQueryParameters([
                    'api-key' => $apiKey,
                    'from-date' => $date,
                    'to-date' => $date,
                    'lang' => 'en',
                    'page' => $page,
                    'page-size' => self::PAGE_SIZE,
                    'order-by' => 'newest',
                    'format' => 'json',
                    'show-fields' => 'all',
                ])->timeout($timeout)
                ->retry(3, 100)
                ->throw()
                ->get($endpoint);

            $data = $response->json();
            $responseBody = $data['response'] ?? [];

            return [
                'articles' => $responseBody['results'] ?? [],
                'pages' => $responseBody['pages'] ?? 1,
            ];
        } catch (RequestException $re) {
            Log::error('The Guardian HTTP error', [
                'page' => $page,
                'status' => $re->response->status(),
                'message' => $re->getMessage(),
            ]);

            return null;
        } catch (ConnectionException $ce) {
            Log::error('The Guardian connection error', ['message' => $ce->getMessage()]);

            throw $ce;
        } catch (Exception $e) {
            Log::error('The Guardian adapter error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
