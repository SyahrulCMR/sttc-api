<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Auth\SsoAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public auth routes
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::apiResource('users', UserController::class);
    });
});

Route::post('/sso/register-session', [SsoAuthController::class, 'registerSession'])->middleware('throttle:60,1');
Route::post('/sso/logout', [SsoAuthController::class, 'broadcastLogout'])->middleware('throttle:30,1');
// server-to-server, sebaiknya di-throttle dan tidak pakai session/csrf
Route::post('/sso/verify', [SsoAuthController::class, 'verifyToken'])
    ->middleware('throttle:30,1');
