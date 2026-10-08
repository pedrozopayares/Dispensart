<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Catalog\LotController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\WarehouseController;
use App\Http\Controllers\Users\UserController;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Route;

/*
| API bajo /api (grupo api + statefulApi, design D1). Toda ruta exige sesión salvo el login.
| Precedencia: CSRF (419) → auth:sanctum (401) → enlace de modelo (404) → autorización (403) → reglas (422).
| Escrituras: autorización en el FormRequest (Policy). Listados: middleware `can` (Policy).
*/

Route::post('/auth/login', LoginController::class)->name('auth.login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', LogoutController::class)->name('auth.logout');
    Route::get('/auth/me', MeController::class)->name('auth.me');

    Route::get('/users', [UserController::class, 'index'])->can('viewAny', User::class)->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');

    Route::get('/warehouses', [WarehouseController::class, 'index'])->can('viewAny', Warehouse::class)->name('warehouses.index');
    Route::post('/warehouses', [WarehouseController::class, 'store'])->name('warehouses.store');
    Route::patch('/warehouses/{warehouse}', [WarehouseController::class, 'update'])->whereNumber('warehouse')->name('warehouses.update');

    Route::get('/products', [ProductController::class, 'index'])->can('viewAny', Product::class)->name('products.index');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::patch('/products/{product}', [ProductController::class, 'update'])->whereNumber('product')->name('products.update');

    Route::get('/lots', LotController::class)->name('lots.index');
});
