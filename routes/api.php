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
    Route::post('/takeoff', [DroneApiController::class, 'takeoff']);
    Route::post('/land', [DroneApiController::class, 'land']);
    Route::post('/emergency', [DroneApiController::class, 'emergency']);
    Route::post('/sdk/reset', [DroneApiController::class, 'resetSdk']);
    Route::post('/rc', [DroneApiController::class, 'rc']);
    Route::post('/nudge/up', [DroneApiController::class, 'nudgeUp']);
    Route::post('/nudge/down', [DroneApiController::class, 'nudgeDown']);
    Route::post('/nudge/left', [DroneApiController::class, 'nudgeLeft']);
    Route::post('/nudge/right', [DroneApiController::class, 'nudgeRight']);
    Route::post('/nudge/forward', [DroneApiController::class, 'nudgeForward']);
    Route::post('/nudge/back', [DroneApiController::class, 'nudgeBack']);
    Route::post('/nudge/yaw-left', [DroneApiController::class, 'nudgeYawLeft']);
    Route::post('/nudge/yaw-right', [DroneApiController::class, 'nudgeYawRight']);
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
    Route::delete('/events/clear', [McpAiController::class, 'clearEvents']);
    Route::post('/summarize', [McpAiController::class, 'summarize']);
});
