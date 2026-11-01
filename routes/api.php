<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ChangeEventController;
use App\Http\Controllers\CrawlRunController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FuneralHomeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard', DashboardController::class);
    Route::get('/homes', [FuneralHomeController::class, 'index']);
    Route::get('/homes/{home}', [FuneralHomeController::class, 'show']);
    Route::get('/changes', [ChangeEventController::class, 'index']);
    Route::get('/crawl-runs', [CrawlRunController::class, 'index']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
