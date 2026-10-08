<?php

use App\Enums\Role;

// identity-access "Mapa de capacidades por rol": listas exactas de § 3 y denegación por defecto.

$auxiliar = [
    'catalog.view', 'inventory.view', 'dispensations.create', 'transfers.view',
    'transfers.create', 'transfers.receive', 'patients.view',
];

it('resuelve exactamente las capacidades de cada rol', function (Role $role, array $expected) {
    $abilities = $role->abilityValues();

    expect($abilities)->toEqualCanonicalizing($expected)
        ->and($abilities)->toHaveCount(count($expected));
    foreach ($expected as $ability) {
        expect($role->allows($ability))->toBeTrue();
    }
})->with([
    'auxiliar_farmacia' => [Role::AuxiliarFarmacia, $auxiliar],
    'regente_farmacia' => [Role::RegenteFarmacia, [
        ...$auxiliar, 'transfers.approve', 'controlled_drugs.authorize', 'inventory.adjust',
    ]],
    'medico' => [Role::Medico, ['catalog.view', 'prescriptions.create', 'patients.view']],
    'auditor' => [Role::Auditor, ['catalog.view', 'inventory.view', 'transfers.view', 'patients.view']],
    'admin' => [Role::Admin, ['catalog.view', 'catalog.manage', 'users.manage']],
]);

it('niega al auditor toda capacidad de escritura', function () {
    $writes = [
        'catalog.manage', 'users.manage', 'inventory.adjust', 'dispensations.create',
        'controlled_drugs.authorize', 'transfers.create', 'transfers.receive', 'transfers.approve',
        'prescriptions.create',
    ];

    foreach ($writes as $ability) {
        expect(Role::Auditor->allows($ability))->toBeFalse();
    }
});

it('niega al admin datos clínicos e inventario', function () {
    foreach (['patients.view', 'inventory.view', 'prescriptions.create', 'dispensations.create', 'controlled_drugs.authorize'] as $ability) {
        expect(Role::Admin->allows($ability))->toBeFalse();
    }
});

it('niega una capacidad desconocida a los cinco roles', function (Role $role) {
    expect($role->allows('reports.export'))->toBeFalse()
        ->and($role->allows(''))->toBeFalse();
})->with(Role::cases());
