<?php

use App\Enums\Role;
use App\Models\Dispensation;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\User;

// Policies de S3 sobre el mapa de capacidades de S1, que no cambia (tarea 3.1).

it('aplica por rol las Policies de pacientes, prescripciones y dispensaciones', function (Role $role, array $expected) {
    $user = (new User)->forceFill(['role' => $role]);

    expect([
        'patients.view' => $user->can('viewAny', Patient::class) && $user->can('view', Patient::class),
        'patients.identifiable' => $user->can('viewIdentifiable', Patient::class),
        'prescriptions.create' => $user->can('create', Prescription::class),
        'dispensations.create' => $user->can('create', Dispensation::class),
    ])->toBe($expected);
})->with([
    'auxiliar_farmacia' => [Role::AuxiliarFarmacia, ['patients.view' => true, 'patients.identifiable' => true, 'prescriptions.create' => false, 'dispensations.create' => true]],
    'regente_farmacia' => [Role::RegenteFarmacia, ['patients.view' => true, 'patients.identifiable' => true, 'prescriptions.create' => false, 'dispensations.create' => true]],
    'medico' => [Role::Medico, ['patients.view' => true, 'patients.identifiable' => true, 'prescriptions.create' => true, 'dispensations.create' => false]],
    'auditor' => [Role::Auditor, ['patients.view' => true, 'patients.identifiable' => false, 'prescriptions.create' => false, 'dispensations.create' => false]],
    'admin' => [Role::Admin, ['patients.view' => false, 'patients.identifiable' => false, 'prescriptions.create' => false, 'dispensations.create' => false]],
]);
