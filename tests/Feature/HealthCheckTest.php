<?php

it('returns a successful health check response', function () {
    $this->getJson('/api/health')
        ->assertSuccessful()
        ->assertJson([
            'status' => 'OK',
            'service' => 'news-agregator-backend-api',
            'version' => '1.0.0',
        ]);
});
