<?php

use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'status' => 'OK',
    'service' => 'news-agregator-backend-api',
    'version' => '1.0.0',
]));
