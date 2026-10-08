<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Catalog\LotController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\WarehouseController;
use App\Http\Controllers\Dispensation\DispensationController;
use App\Http\Controllers\Dispensation\DispensationPreviewController;
use App\Http\Controllers\Inventory\KardexController;
use App\Http\Controllers\Inventory\StockAdjustmentController;
use App\Http\Controllers\Inventory\StockController;
use App\Http\Controllers\Patients\PatientController;
use App\Http\Controllers\Prescriptions\PrescriptionController;
use App\Http\Controllers\Users\UserController;
use App\Http\Middleware\RequireIdempotencyKey;
use App\Models\Dispensation;
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

    // Inventario (S2). El kardex no tiene ruta de edición ni borrado: es de solo inserción (RN-06).
    Route::get('/stock', StockController::class)->name('stock.index');
    Route::get('/kardex', KardexController::class)->name('kardex.index');
    Route::post('/stock-adjustments', StockAdjustmentController::class)->name('stock-adjustments.store');

    // Pacientes, prescripciones y dispensación (S3). La ficha no usa enlace implícito: la Policy corre antes
    // de buscar el paciente (403 antes que 404, design D4). Id de 1 a 18 dígitos: cabe en bigint, nunca un 500.
    Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
    Route::get('/patients/{patient}', [PatientController::class, 'show'])->where('patient', '[0-9]{1,18}')->name('patients.show');
    Route::post('/prescriptions', PrescriptionController::class)->name('prescriptions.store');
    Route::post('/dispensations/preview', DispensationPreviewController::class)
        ->can('create', Dispensation::class)
        ->name('dispensations.preview');
    // Precedencia (design D4): 419/401 → 403 → clave de idempotencia (422) → validación (422) → negocio.
    Route::post('/dispensations', DispensationController::class)
        ->can('create', Dispensation::class)
        ->middleware(RequireIdempotencyKey::class)
        ->name('dispensations.store');
});
