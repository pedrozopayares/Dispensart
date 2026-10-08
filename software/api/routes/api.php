<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MeController;
use Illuminate\Support\Facades\Route;

/*
| API bajo /api (grupo api + statefulApi, design D1). Toda ruta exige sesión salvo el login.
| Precedencia: CSRF (419) → auth:sanctum (401) → enlace de modelo (404) → autorización (403) → reglas (422).
*/

Route::post('/auth/login', LoginController::class)->name('auth.login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', LogoutController::class)->name('auth.logout');
    Route::get('/auth/me', MeController::class)->name('auth.me');
});
