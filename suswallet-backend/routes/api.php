<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Middleware\EnsureActiveStudent;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public Authentication Routes (No Registration — Accounts are Pre-Seeded)
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:10,1');

    // Protected Student Routes
    Route::middleware(['auth:sanctum', EnsureActiveStudent::class])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::put('/auth/password', [AuthController::class, 'changePassword']);

        Route::get('/profile', [ProfileController::class, 'show']);
        Route::match(['patch', 'post'], '/profile', [ProfileController::class, 'update']);

        Route::get('/wallets', [WalletController::class, 'index']);

        Route::get('/transactions', [TransactionController::class, 'index']);
        Route::post('/transactions/deposit', [TransactionController::class, 'deposit']);
        Route::post('/transactions/allocate', [TransactionController::class, 'allocate']);
        Route::post('/transactions/tap', [TransactionController::class, 'simulateCardTap']);
        Route::post('/transactions/refund-last-tap', [TransactionController::class, 'refundLastTap']);
    });
});
