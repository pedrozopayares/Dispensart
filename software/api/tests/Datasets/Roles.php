<?php

use App\Enums\Role;

// Datasets de roles para las pruebas de autorización por endpoint.

dataset('all_roles', fn () => array_combine(Role::values(), array_map(fn (Role $role) => [$role], Role::cases())));

dataset('non_admin_roles', fn () => [
    'auxiliar_farmacia' => [Role::AuxiliarFarmacia],
    'regente_farmacia' => [Role::RegenteFarmacia],
    'medico' => [Role::Medico],
    'auditor' => [Role::Auditor],
]);
