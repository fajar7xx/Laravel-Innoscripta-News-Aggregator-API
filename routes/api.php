<?php

use App\Http\Controllers\API\V1\ArticleController;
use App\Http\Controllers\API\V1\CategoryController;
use App\Http\Controllers\API\V1\SourceController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'status' => 'OK',
    'service' => 'news-aggregator-backend-api',
    'version' => '1.0.0',
    'timestamp' => now()->toIso8601String(),
]));

Route::prefix('/v1')->group(function () {
    Route::controller(ArticleController::class)->group(function () {
        Route::get('/articles', 'index');
        Route::get('/articles/{article}', 'show');
    });

    Route::controller(CategoryController::class)->group(function () {
        Route::get('/categories', 'index');
        Route::get('/categories/{category}', 'show');
    });

    Route::controller(SourceController::class)->group(function () {
        Route::get('/sources', 'index');
        Route::get('/sources/{source}', 'show');
    });
});
