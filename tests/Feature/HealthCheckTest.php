<?php

test('returns a successful health check response', function () {
    $this->getJson('/api/health')
        ->assertOk()
        ->assertJson([
            'status' => 'OK',
            'service' => 'news-aggregator-backend-api',
            'version' => '1.0.0',
        ])->assertJsonStructure(['status', 'service', 'version', 'timestamp']);
});
