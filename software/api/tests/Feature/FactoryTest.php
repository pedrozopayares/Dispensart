<?php

use App\Enums\Role;
use App\Models\Lot;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Cimiento (tarea 1.4): las fábricas producen filas válidas contra las restricciones reales.

it('Factory de usuario crea un usuario por cada rol', function (Role $role) {
    $user = User::factory()->withRole($role)->create();

    expect($user->fresh()?->role)->toBe($role);
})->with(Role::cases());

it('Factory de bodega, producto y lote crea filas relacionadas', function () {
    $warehouse = Warehouse::factory()->create();
    $lot = Lot::factory()->for(Product::factory()->controlled())->create();

    expect($warehouse->exists)->toBeTrue()
        ->and($lot->product->is_controlled)->toBeTrue()
        ->and($lot->fresh()?->expires_on->isFuture())->toBeTrue();
});
