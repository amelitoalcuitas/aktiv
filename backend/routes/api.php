<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourtController;
use App\Http\Controllers\Api\HubController;
use App\Http\Controllers\Api\OAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->name('api.auth.register');
    Route::post('/login', [AuthController::class, 'login'])->name('api.auth.login');
    Route::get('/google/redirect', [OAuthController::class, 'redirect'])->name('api.auth.google.redirect');
    Route::get('/google/callback', [OAuthController::class, 'callback'])->name('api.auth.google.callback');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('api.auth.me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
    });
});

// Public hub routes
Route::get('/hubs', [HubController::class, 'index'])->name('api.hubs.index');
Route::get('/hubs/{hub}', [HubController::class, 'show'])->name('api.hubs.show');
Route::get('/hubs/{hub}/courts', [CourtController::class, 'index'])->name('api.hubs.courts.index');

// Authenticated hub + court management
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/dashboard/hubs', [HubController::class, 'myHubs'])->name('api.dashboard.hubs');
    Route::post('/hubs', [HubController::class, 'store'])->name('api.hubs.store');
    Route::put('/hubs/{hub}', [HubController::class, 'update'])->name('api.hubs.update');
    Route::delete('/hubs/{hub}', [HubController::class, 'destroy'])->name('api.hubs.destroy');
    Route::post('/hubs/{hub}/courts', [CourtController::class, 'store'])->name('api.hubs.courts.store');
    Route::put('/hubs/{hub}/courts/{court}', [CourtController::class, 'update'])->name('api.hubs.courts.update');
    Route::delete('/hubs/{hub}/courts/{court}', [CourtController::class, 'destroy'])->name('api.hubs.courts.destroy');
});

Route::get('/status', static fn (): array => ['ok' => true])->name('api.status');
