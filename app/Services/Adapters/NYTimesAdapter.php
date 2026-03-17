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
use Illuminate\Support\Str;

class NYTimesAdapter implements NewsSourceInterface
{
    /**
     * Create a new class instance.
     */
    public function __construct(private array $config = [])
    {
        $this->config = config('news.sources.nytimes');
    }

    /**
     * Fetch raw articles from the NYTimes Archive API for 2 months ago.
     *
     * A 2-month offset is used because recent months may not yet be available
     * in NYTimes' Google Cloud Storage backend.
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
        $endpoint = $this->config['endpoints']['archive'];

        // NYTimes Archive API has a publication delay.
        // Recent months may not be available yet in their Google Cloud Storage.
        // Use at least a 2-month offset to ensure data availability.
        $now = Carbon::now()->subMonths(2);
        $year = $now->year;
        $month = $now->month;
        $path = "{$endpoint}/{$year}/{$month}.json";

        try {
            Log::info('NYTimes: Fetching articles', [
                'endpoint' => $endpoint,
                'fetched_at' => Carbon::now()->toIso8601String(),
            ]);

            $response = Http::baseUrl($baseUrl)
                ->withQueryParameters([
                    'api-key' => $apiKey,
                ])->timeout($timeout)
                ->retry(3, 100)
                ->throw()
                ->get($path);

            $data = $response->json();
            $articles = $data['response']['docs'] ?? [];

            Log::info('NYTimes: fetched articles successfully', [
                'count' => count($articles),
                'total_results' => $data['response']['meta']['hits'] ?? 0,
            ]);

            return $articles;
        } catch (RequestException $re) {
            Log::error('NYTimes HTTP error', [
                'status' => $re->response->status(),
                'message' => $re->getMessage(),
            ]);

            return [];
        } catch (ConnectionException $ce) {
            Log::error('NYTimes connection error', [
                'message' => $ce->getMessage(),
            ]);

            throw $ce;
        } catch (Exception $e) {
            Log::error('NYTimes adapter error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Normalize a single raw NYTimes article into the common article schema.
     *
     * @param  array{
     *     _id?: string,
     *     web_url?: string,
     *     snippet?: string,
     *     headline?: array{main?: string},
     *     byline?: array{original?: string},
     *     pub_date?: string,
     *     section_name?: string,
     *     multimedia?: array{
     *         default?: array{url?: string, height?: int, width?: int},
     *         thumbnail?: array{url?: string, height?: int, width?: int},
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
        if (empty($data['_id']) || empty($data['web_url'])) {
            return [];
        }

        $author = $data['byline']['original'] ?? null;
        if ($author && str_starts_with($author, 'By ')) {
            $author = substr($author, 3);
        }

        $sectionName = $data['section_name'] ?? 'general';
        $slug = Str::slug($sectionName) ?: 'general';
        $category = Category::firstOrCreate(
            ['slug' => $slug],
            ['name' => $sectionName],
        );

        return [
            'external_id' => $data['_id'],
            'title' => $data['headline']['main'] ?? 'Untitled',
            'description' => $data['snippet'] ?? null,
            'content' => $data['snippet'] ?? null,
            'author' => $author ?? null,
            'url' => $data['web_url'] ?? '',
            'image_url' => $data['multimedia']['default']['url'] ?? null,
            'published_at' => Carbon::parse($data['pub_date'])->toDateTimeString(),
            'categories' => [$category->slug],
        ];
    }
}
