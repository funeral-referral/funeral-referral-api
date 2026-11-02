<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CampaignMessageController;
use App\Http\Controllers\ChangeEventController;
use App\Http\Controllers\CrawlRunController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FuneralHomeController;
use App\Http\Controllers\InboundEmailController;
use App\Http\Controllers\PipelineController;
use App\Http\Controllers\ReplyController;
use App\Http\Controllers\UnsubscribeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::match(['get', 'post'], '/unsubscribe/{campaign}', UnsubscribeController::class)
    ->middleware(['signed', 'throttle:60,1'])
    ->name('unsubscribe');

Route::post('/webhooks/inbound-email', InboundEmailController::class)->middleware('throttle:120,1');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard', DashboardController::class);
    Route::get('/homes', [FuneralHomeController::class, 'index']);
    Route::get('/homes/{home}', [FuneralHomeController::class, 'show']);
    Route::get('/changes', [ChangeEventController::class, 'index']);
    Route::get('/crawl-runs', [CrawlRunController::class, 'index']);

    Route::get('/users', [PipelineController::class, 'users']);
    Route::put('/homes/{home}/stage', [PipelineController::class, 'stage']);
    Route::put('/homes/{home}/assignee', [PipelineController::class, 'assign']);
    Route::post('/homes/{home}/notes', [PipelineController::class, 'storeNote']);
    Route::post('/homes/{home}/referrals', [PipelineController::class, 'storeReferral']);

    Route::post('/homes/{home}/campaigns', [CampaignController::class, 'store'])->middleware('throttle:20,1');
    Route::post('/homes/{home}/replies', [ReplyController::class, 'store']);
    Route::get('/campaigns', [CampaignController::class, 'index']);
    Route::get('/campaigns/{campaign}', [CampaignController::class, 'show']);
    Route::post('/campaigns/{campaign}/approve', [CampaignController::class, 'approve']);
    Route::post('/campaigns/{campaign}/stop', [CampaignController::class, 'stop']);
    Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy']);
    Route::patch('/campaign-messages/{message}', [CampaignMessageController::class, 'update']);
    Route::get('/replies', [ReplyController::class, 'index']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
