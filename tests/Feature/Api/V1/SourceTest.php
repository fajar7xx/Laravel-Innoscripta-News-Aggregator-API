<?php

use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

test('returns all sources', function () {
    Source::factory()->count(3)->create();

    $this->getJson('/api/v1/sources')
        ->assertSuccessful()
        ->assertJsonStructure([
            'data' => [['id', 'name', 'slug']],
        ])
        ->assertJsonCount(3, 'data');
});

test('returns an empty list when no sources exist', function () {
    $this->getJson('/api/v1/sources')
        ->assertSuccessful()
        ->assertJson(['data' => []]);
});

test('returns a single source', function () {
    $source = Source::factory()->create();

    $this->getJson("/api/v1/sources/{$source->id}")
        ->assertSuccessful()
        ->assertJsonFragment(['id' => $source->id, 'name' => $source->name]);
});

test('returns 404 for a non-existent source', function () {
    $this->getJson('/api/v1/sources/999')->assertNotFound();
});
