<?php

// API for the Owlvix mobile app (mobile/). Everything here lives under /api and answers in JSON.
// Each rate limit has its own name (the 3rd value), so they count separately: without it Laravel
// counts every limited request from one visitor together.

use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\MessageController;
use App\Http\Controllers\Api\Admin\ProjectController;
use App\Http\Controllers\Api\Admin\StatsController;
use App\Http\Controllers\Api\Admin\TestimonialController;
use App\Http\Controllers\Api\SiteController;
use Illuminate\Support\Facades\Route;

// Customer side: portfolio, reviews and the quote form
Route::get('site', [SiteController::class, 'show'])->middleware('throttle:60,1,api-site');
Route::post('contact', [SiteController::class, 'contact'])->middleware('throttle:5,1,api-contact');

// Mobile app builder: the Android build workflow on GitHub Actions (App\Http\Controllers\Api\AppBuildRunnerController)
Route::prefix('app-builds/{build}')->whereNumber('build')->middleware('throttle:300,1,app-runner')->controller(\App\Http\Controllers\Api\AppBuildRunnerController::class)->group(function () {
    Route::get('job', 'job');
    Route::get('asset', 'asset');
    Route::post('upload', 'upload');
    Route::post('finish', 'finish');
});

// Mobile app builder: one anonymous usage summary a day from each phone (app-shell/stats.js)
Route::post('app-pay/{id}', [\App\Http\Controllers\Api\AppPayController::class, 'start'])->where('id', '[a-z0-9]{16}')->middleware('throttle:10,1,app-pay');
Route::get('app-pay/{id}/{session}', [\App\Http\Controllers\Api\AppPayController::class, 'status'])->where(['id' => '[a-z0-9]{16}', 'session' => 'cs_[A-Za-z0-9_]{10,200}'])->middleware('throttle:30,1,app-pay-status');
Route::post('app-loyalty/{id}', [\App\Http\Controllers\Api\AppLoyaltyController::class, 'check'])->where('id', '[a-z0-9]{16}')->middleware('throttle:20,1,app-loyalty');
Route::post('app-stats/{id}', [\App\Http\Controllers\Api\AppStatsController::class, 'store'])->where('id', '[a-z0-9]{16}')->middleware('throttle:30,1,app-stats');

// Admin side
Route::prefix('admin')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1,api-login');

    Route::middleware(['admin.token', 'throttle:120,1,api-admin'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('push-token', [AuthController::class, 'pushToken']);

        Route::get('dashboard', [StatsController::class, 'dashboard']);
        Route::get('traffic', [StatsController::class, 'traffic']);

        Route::get('messages', [MessageController::class, 'index']);
        Route::get('messages/{message}', [MessageController::class, 'show']);
        Route::post('messages/{message}/reply', [MessageController::class, 'reply'])->middleware('throttle:10,1,api-reply');
        Route::patch('messages/{message}/read', [MessageController::class, 'toggleRead']);
        Route::delete('messages/{message}', [MessageController::class, 'destroy']);

        Route::get('projects', [ProjectController::class, 'index']);
        Route::post('projects', [ProjectController::class, 'store']);
        Route::post('projects/{project}', [ProjectController::class, 'update']); // POST: carries an image upload
        Route::delete('projects/{project}', [ProjectController::class, 'destroy']);
        Route::post('projects/{project}/move/{direction}', [ProjectController::class, 'move'])->whereIn('direction', ['up', 'down']);
        Route::post('projects/{project}/toggle', [ProjectController::class, 'toggle']);

        Route::get('reviews', [TestimonialController::class, 'index']);
        Route::post('reviews', [TestimonialController::class, 'store']);
        Route::put('reviews/settings', [TestimonialController::class, 'settings']);
        Route::put('reviews/{testimonial}', [TestimonialController::class, 'update']);
        Route::delete('reviews/{testimonial}', [TestimonialController::class, 'destroy']);
        Route::post('reviews/{testimonial}/move/{direction}', [TestimonialController::class, 'move'])->whereIn('direction', ['up', 'down']);
        Route::post('reviews/{testimonial}/toggle/{field}', [TestimonialController::class, 'toggle'])->whereIn('field', ['is_published', 'in_email']);
    });
});
