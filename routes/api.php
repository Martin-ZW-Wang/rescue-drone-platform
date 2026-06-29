<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DroneApiController;
use App\Http\Controllers\Api\EventIngestController;
use App\Http\Controllers\Api\EventQueryController;
use App\Http\Controllers\Api\McpAiController;

Route::prefix('drone')->group(function () {
    Route::get('/status', [DroneApiController::class, 'status']);
    Route::post('/start', [DroneApiController::class, 'start']);
    Route::post('/stop', [DroneApiController::class, 'stop']);
    Route::post('/pose/start', [DroneApiController::class, 'startPose']);
    Route::post('/pose/stop', [DroneApiController::class, 'stopPose']);
    Route::get('/debug/seg', [DroneApiController::class, 'debugSeg']);
});

Route::post('/events/ingest', [EventIngestController::class, 'store']);
Route::get('/events/recent', [EventQueryController::class, 'recent']);
Route::get('/events/{event}', [EventQueryController::class, 'show']);

Route::prefix('mcp')->group(function () {
    Route::get('/status', [McpAiController::class, 'status']);
    Route::get('/events/recent', [McpAiController::class, 'recentEvents']);
    Route::post('/summarize', [McpAiController::class, 'summarize']);
});
