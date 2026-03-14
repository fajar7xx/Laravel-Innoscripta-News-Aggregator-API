<?php

test('returns a successful health check response', function () {
    $this->getJson('/api/health')
        ->assertSuccessful()
        ->assertJson([
            'status' => 'OK',
            'service' => 'news-aggregator-backend-api',
            'version' => '1.0.0',
            'timestamp' => now()->toIso8601String(),
        ]);
});
