<?php

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

test('returns all categories', function () {
    Category::factory()->count(3)->create();

    $this->getJson('/api/v1/categories')
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [['id', 'name', 'slug']],
        ])
        ->assertJsonCount(3, 'data');
});

test('returns an empty list when no categories exist', function () {
    $this->getJson('/api/v1/categories')
        ->assertSuccessful()
        ->assertJson(['data' => []]);
});

test('returns a single category', function () {
    $category = Category::factory()->create();

    $this->getJson("/api/v1/categories/{$category->id}")
        ->assertSuccessful()
        ->assertJsonFragment(['id' => $category->id, 'name' => $category->name]);
});

test('returns 404 for a non-existent category', function () {
    $this->getJson('/api/v1/categories/999')->assertNotFound();
});
