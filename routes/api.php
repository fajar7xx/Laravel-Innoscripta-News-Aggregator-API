<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'status' => 'OK',
    'service' => 'news-aggregator-backend-api',
    'version' => '1.0.0',
    'timestamp' => now()->toIso8601String(),
]));
