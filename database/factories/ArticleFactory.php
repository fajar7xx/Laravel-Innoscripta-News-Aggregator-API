<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\Category;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_id' => Source::inRandomOrder()->value('id') ?? Source::factory(),
            'external_id' => fake()->unique()->uuid(),
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'content' => fake()->paragraphs(3, true),
            'author' => fake()->name(),
            'url' => fake()->unique()->url(),
            'image_url' => fake()->imageUrl(),
            'published_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'fetched_at' => now(),
        ];
    }

    public function withCategories(int $count = 2): static
    {
        return $this->afterCreating(function (Article $article) use ($count) {
            $categoryIds = Category::inRandomOrder()->limit($count)->pluck('id');
            $article->categories()->sync($categoryIds);
        });
    }
}
