<?php

use App\Http\Controllers\Auth\SsoAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/sso/login', [SsoAuthController::class, 'showLogin'])->name('sso.login');
Route::post('/sso/login', [SsoAuthController::class, 'login'])->name('sso.login.submit');


