<?php

use App\Enums\Role;
use App\Models\KardexMovement;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// inventory "Roles sin lectura de inventario", "Otro rol intenta ajustar"; kardex "Roles sin lectura de
// inventario", a nivel de Policy: delegan en inventory.view / inventory.adjust del mapa de S1.

it('autoriza lectura y ajuste de inventario solo a los roles del mapa', function (Role $role, bool $view, bool $adjust) {
    $user = User::factory()->withRole($role)->create();

    expect($user->can('viewAny', Stock::class))->toBe($view)
        ->and($user->can('viewAny', KardexMovement::class))->toBe($view)
        ->and($user->can('adjust', Stock::class))->toBe($adjust);
})->with([
    'auxiliar_farmacia' => [Role::AuxiliarFarmacia, true, false],
    'regente_farmacia' => [Role::RegenteFarmacia, true, true],
    'medico' => [Role::Medico, false, false],
    'auditor' => [Role::Auditor, true, false],
    'admin' => [Role::Admin, false, false],
]);
