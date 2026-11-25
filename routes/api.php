<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
| All routes here are prefixed with /api (bootstrap/app.php) and versioned under /v1.
*/
Route::prefix('v1')->name('v1.')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::apiResource('users', UserController::class);
        Route::apiResource('products', ProductController::class);
        Route::apiResource('orders', OrderController::class);
    });
});
