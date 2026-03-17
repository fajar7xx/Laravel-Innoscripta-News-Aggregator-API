<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sources = collect(config('news.sources'))
            ->map(fn ($config, $slug) => [
                'name' => $config['name'],
                'slug' => $slug,
                'is_active' => $config['enabled'] ?? true,
                'last_fetched_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ])->values()
            ->all();

        DB::table('sources')->insert($sources);

    }
}
