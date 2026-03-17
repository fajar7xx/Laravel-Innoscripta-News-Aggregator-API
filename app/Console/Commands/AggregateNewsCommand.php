<?php

namespace App\Console\Commands;

use App\Jobs\FetchArticlesJob;
use App\Models\Source;
use Illuminate\Console\Command;

class AggregateNewsCommand extends Command
{
    protected $signature = 'news:aggregate';

    protected $description = 'Dispatch fetch jobs for all active news sources';

    public function handle(): int
    {
        $sources = Source::query()->where('is_active', true)->get();

        if ($sources->isEmpty()) {
            $this->warn('No active sources found.');

            return self::SUCCESS;
        }

        foreach ($sources as $source) {
            FetchArticlesJob::dispatch($source->slug);
            $this->info("Dispatched job for: {$source->name}");
        }

        $this->info("Total {$sources->count()} job(s) dispatched to queue.");

        return self::SUCCESS;
    }
}
