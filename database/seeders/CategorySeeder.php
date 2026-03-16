<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Technology', 'slug' => 'technology'],
            ['name' => 'Business', 'slug' => 'business'],
            ['name' => 'Politics', 'slug' => 'politics'],
            ['name' => 'Sports', 'slug' => 'sports'],
            ['name' => 'Entertainment', 'slug' => 'entertainment'],
            ['name' => 'Science', 'slug' => 'science'],
            ['name' => 'Health', 'slug' => 'health'],
            ['name' => 'World', 'slug' => 'world'],
        ];

        $categories = array_map(fn ($category) => [
            ...$category,
            'created_at' => now(),
            'updated_at' => now(),
        ], $categories);

        DB::table('categories')->insert($categories);
    }
}
