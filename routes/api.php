<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\UserRoleController;
use App\Http\Controllers\Api\V1\UserTwoFactorController;
use App\Http\Controllers\Auth\SsoBackChannelController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public auth routes
    //
    // Self-service register DINONAKTIFKAN sejak Sprint 1 (Epic 1 IAM/SSO): pembuatan akun
    // terpusat lewat IAM (provisioning), bukan pendaftaran mandiri.
    // Controller & AuthService::register sengaja dipertahankan. Lihat epics/sprint-1-plan.md.
    // Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);

    // Authenticated routes (Sanctum PAT lama)
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::apiResource('users', UserController::class);
    });

    // Manajemen role & 2FA oleh Super Admin (token OAuth, active_role=super-admin).
    Route::middleware(['auth:api', 'role:super-admin'])->group(function () {
        Route::get('roles', [RoleController::class, 'index']);
        Route::get('users/{user}/roles', [UserRoleController::class, 'index']);
        Route::post('users/{user}/roles', [UserRoleController::class, 'store']);
        Route::delete('users/{user}/roles/{role}', [UserRoleController::class, 'destroy']);
        Route::post('users/{user}/two-factor/reset', [UserTwoFactorController::class, 'reset']);
    });
});

// Back-channel SSO (server-to-server): pendaftaran sesi + single-logout.
// Alur opaque lama (`/sso/verify`) dihapus di Sprint 2 (task 2b-1) — verifikasi token
// kini lokal via JWKS di resource server.
Route::post('/sso/register-session', [SsoBackChannelController::class, 'registerSession'])->middleware('throttle:60,1');
Route::post('/sso/logout', [SsoBackChannelController::class, 'broadcastLogout'])->middleware('throttle:30,1');
