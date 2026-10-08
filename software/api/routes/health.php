<?php

use App\Http\Controllers\Health\HealthController;
use App\Http\Controllers\Health\ReadyController;
use Illuminate\Support\Facades\Route;

// Sin grupo de middleware: ni sesión ni cookies (design D6). Reenviadas por el Nginx de web.
Route::get('/health', HealthController::class)->name('health');
Route::get('/ready', ReadyController::class)->name('ready');
