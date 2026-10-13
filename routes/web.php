<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\McpController;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/events', [EventController::class, 'index'])->name('events.index');
Route::patch('/events/{event}/review', [EventController::class, 'updateReview'])->name('events.review.update');
Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
Route::get('/mcp', [McpController::class, 'index'])->name('mcp.index');
