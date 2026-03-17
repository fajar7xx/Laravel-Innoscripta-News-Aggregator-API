<?php

namespace App\Jobs;

use App\Contracts\NewsSourceInterface;
use App\Models\Source;
use App\Services\Adapters\GuardianAdapter;
use App\Services\Adapters\NewsApiAdapter;
use App\Services\Adapters\NYTimesAdapter;
use App\Services\NewsAggregationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class FetchArticlesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [30, 120, 600];

    public int $timeout = 120;

    public function __construct(public readonly string $sourceSlug) {}

    public function handle(NewsAggregationService $service): void
    {
        $source = Source::query()
            ->where('slug', $this->sourceSlug)
            ->where('is_active', true)
            ->firstOrFail();

        $adapter = $this->resolveAdapter($this->sourceSlug);
        $rawArticles = $adapter->fetch();

        $saved = 0;

        foreach ($rawArticles as $rawArticle) {
            try {
                $normalized = $adapter->normalize($rawArticle);
                $service->saveArticle($source, $normalized);
                $saved++;
            } catch (Throwable $e) {
                Log::warning('Failed to save article', [
                    'source' => $this->sourceSlug,
                    'url' => $rawArticle['url'] ?? $rawArticle['webUrl'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $source->update(['last_fetched_at' => now()]);

        Log::info('FetchArticlesJob completed', [
            'source' => $this->sourceSlug,
            'fetched' => count($rawArticles),
            'saved' => $saved,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('FetchArticlesJob exhausted all retries', [
            'source' => $this->sourceSlug,
            'error' => $exception->getMessage(),
        ]);
    }

    private function resolveAdapter(string $slug): NewsSourceInterface
    {
        return match ($slug) {
            'newsapi' => app(NewsApiAdapter::class),
            'guardian' => app(GuardianAdapter::class),
            'nytimes' => app(NYTimesAdapter::class),
            default => throw new InvalidArgumentException("No adapter registered for source: {$slug}"),
        };
    }
}
