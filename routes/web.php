<?php

use App\Http\Controllers\Admin\PasswordResetRequestController;
use App\Http\Controllers\Auth\OAuthLoginController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RoleContextController;
use App\Http\Controllers\Auth\SsoAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// --- SSO opaque lama (koeksistensi, dihapus setelah Passport stabil) ---
Route::get('/sso/login', [SsoAuthController::class, 'showLogin'])->name('sso.login');
Route::post('/sso/login', [SsoAuthController::class, 'login'])->name('sso.login.submit');

// --- Login untuk alur OAuth authorize (Passport) ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [OAuthLoginController::class, 'show'])->name('login');
    Route::post('/login', [OAuthLoginController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
});

// --- Konteks role (multi-role picker + perpindahan tanpa login ulang) ---
Route::middleware('auth')->group(function () {
    Route::get('/select-role', [RoleContextController::class, 'select'])->name('role.select');
    Route::post('/select-role', [RoleContextController::class, 'store']);
    Route::get('/switch-role', [RoleContextController::class, 'switch'])->name('role.switch');
});

// --- Client uji OAuth (hanya non-production) ---
if (! app()->environment('production')) {
    Route::get('/dev/oauth/callback', function (Request $request) {
        abort_unless($request->filled('code'), 400, 'Parameter code tidak ada.');

        return response()->json([
            'code' => $request->query('code'),
            'state' => $request->query('state'),
        ]);
    })->name('dev.oauth.callback');
}

// --- Admin (proses pengajuan reset password) ---
Route::middleware(['auth', 'role:super-admin,admin-baak'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/password-requests', [PasswordResetRequestController::class, 'index'])->name('password-requests.index');
    Route::post('/password-requests/{resetRequest}/approve', [PasswordResetRequestController::class, 'approve'])->name('password-requests.approve');
    Route::post('/password-requests/{resetRequest}/reject', [PasswordResetRequestController::class, 'reject'])->name('password-requests.reject');
});
