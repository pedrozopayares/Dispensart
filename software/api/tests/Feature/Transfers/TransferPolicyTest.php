<?php

use App\Enums\Role;
use App\Models\Transfer;
use App\Models\User;

// tarea 4.2: Policy de traslados por rol sobre el mapa de S1, que no cambia (design D9). Sin base: usuarios y
// traslado en memoria. Segregación fuera de la Policy (design D8): el creador regente pasa `approve` aquí.

/**
 * @return array<string, bool>
 */
function transferAbilities(User $user, Transfer $transfer): array
{
    return [
        'viewAny' => $user->can('viewAny', Transfer::class),
        'view' => $user->can('view', $transfer),
        'create' => $user->can('create', Transfer::class),
        'request' => $user->can('request', $transfer),
        'approve' => $user->can('approve', $transfer),
        'dispatch' => $user->can('dispatch', $transfer),
        'receive' => $user->can('receive', $transfer),
        'void' => $user->can('void', $transfer),
        'resolveDiscrepancy' => $user->can('resolveDiscrepancy', $transfer),
    ];
}

function policyUser(Role $role, int $id): User
{
    return (new User)->forceFill(['id' => $id, 'role' => $role]);
}

it('aplica por rol la Policy de traslados sobre un traslado ajeno', function (Role $role, array $expected) {
    $transfer = (new Transfer)->forceFill(['created_by' => 999]);

    expect(transferAbilities(policyUser($role, 1), $transfer))->toBe($expected);
})->with([
    'auxiliar_farmacia' => [Role::AuxiliarFarmacia, ['viewAny' => true, 'view' => true, 'create' => true, 'request' => false, 'approve' => false, 'dispatch' => true, 'receive' => true, 'void' => false, 'resolveDiscrepancy' => false]],
    'regente_farmacia' => [Role::RegenteFarmacia, ['viewAny' => true, 'view' => true, 'create' => true, 'request' => false, 'approve' => true, 'dispatch' => true, 'receive' => true, 'void' => true, 'resolveDiscrepancy' => true]],
    'medico' => [Role::Medico, ['viewAny' => false, 'view' => false, 'create' => false, 'request' => false, 'approve' => false, 'dispatch' => false, 'receive' => false, 'void' => false, 'resolveDiscrepancy' => false]],
    'auditor' => [Role::Auditor, ['viewAny' => true, 'view' => true, 'create' => false, 'request' => false, 'approve' => false, 'dispatch' => false, 'receive' => false, 'void' => false, 'resolveDiscrepancy' => false]],
    'admin' => [Role::Admin, ['viewAny' => false, 'view' => false, 'create' => false, 'request' => false, 'approve' => false, 'dispatch' => false, 'receive' => false, 'void' => false, 'resolveDiscrepancy' => false]],
]);

it('deja solicitar y anular a su creador solo si tiene transfers.create', function (Role $role, bool $request, bool $void) {
    $transfer = (new Transfer)->forceFill(['created_by' => 1]);
    $abilities = transferAbilities(policyUser($role, 1), $transfer);

    expect([$abilities['request'], $abilities['void']])->toBe([$request, $void]);
})->with([
    'auxiliar_farmacia creador' => [Role::AuxiliarFarmacia, true, true],
    'regente_farmacia creador' => [Role::RegenteFarmacia, true, true],
    'auditor' => [Role::Auditor, false, false],
    'medico' => [Role::Medico, false, false],
    'admin' => [Role::Admin, false, false],
]);
