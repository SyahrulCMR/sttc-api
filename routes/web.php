<?php

use App\Http\Controllers\Admin\PasswordResetRequestController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\SsoAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/sso/login', [SsoAuthController::class, 'showLogin'])->name('sso.login');
Route::post('/sso/login', [SsoAuthController::class, 'login'])->name('sso.login.submit');
// Public (Pengguna Mengajukan)
Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
});

// Private Admin (Admin Memproses Pengajuan)
Route::middleware(['auth', 'role:super-admin,admin-baak'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/password-requests', [PasswordResetRequestController::class, 'index'])->name('password-requests.index');
    Route::post('/password-requests/{resetRequest}/approve', [PasswordResetRequestController::class, 'approve'])->name('password-requests.approve');
    Route::post('/password-requests/{resetRequest}/reject', [PasswordResetRequestController::class, 'reject'])->name('password-requests.reject');
});
