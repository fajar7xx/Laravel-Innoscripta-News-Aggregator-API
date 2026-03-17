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
    /**
     * Create a new class instance.
     */
    public function __construct(private array $config = [])
    {
        $this->config = config('news.sources.guardian');
    }

    /**
     * Fetch raw articles from The Guardian /search endpoint.
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
        $endpoint = $this->config['endpoints']['search'];

        // $randSection = Category::query()->select('slug')->inRandomOrder()->value('slug');

        try {
            Log::info('The Guardian: Fetching articles', [
                'endpoint' => $endpoint,
                'fetched_at' => Carbon::now()->toIso8601String(),
            ]);

            $response = Http::baseUrl($baseUrl)
                ->withQueryParameters([
                    'api-key' => $apiKey,
                    'q' => 'AI',
                    'lang' => 'en',
                    'page' => 1,
                    'page-size' => 10,
                    'order-by' => 'newest',
                    'format' => 'json',
                    'show-fields' => 'all',
                    // 'section' => $randSection,
                ])->timeout($timeout)
                ->retry(3, 100)
                ->throw()
                ->get($endpoint);

            $data = $response->json();
            $articles = $data['response']['results'] ?? [];

            Log::info('The Guardian: fetched articles successfully', [
                'count' => count($articles),
                'total_results' => $data['response']['total'] ?? 0,
            ]);

            return $articles;
        } catch (RequestException $re) {
            Log::error('The Guardian HTTP error', [
                'status' => $re->response->status(),
                'message' => $re->getMessage(),
            ]);

            return [];
        } catch (ConnectionException $ce) {
            Log::error('The Guardian connection error', [
                'message' => $ce->getMessage(),
            ]);

            throw $ce;
        } catch (Exception $e) {
            Log::error('The Guardian adapter error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
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
}
