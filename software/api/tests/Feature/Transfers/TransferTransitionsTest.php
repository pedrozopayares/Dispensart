<?php

use App\Enums\TransferAction;
use App\Enums\TransferStatus;
use App\Exceptions\InvalidTransferTransition;
use App\Services\Transfers\TransferTransitions;

// transfers "Tabla de transiciones del traslado" a nivel de dominio (design D2): las 35 combinaciones
// estado × acción escritas aquí a mano, nunca derivadas de la tabla que se prueba. 7 permitidas, 28 prohibidas.

const TRANSITION_MATRIX = [
    // estado          request        approve       dispatch        receive               void
    'BORRADOR' => ['SOLICITADO', null, null, null, 'ANULADO'],
    'SOLICITADO' => [null, 'APROBADO', null, null, 'ANULADO'],
    'APROBADO' => [null, null, 'EN_TRANSITO', null, 'ANULADO'],
    'EN_TRANSITO' => [null, null, null, 'RECIBIDO|RECIBIDO_PARCIAL', null],
    'RECIBIDO' => [null, null, null, null, null],
    'RECIBIDO_PARCIAL' => [null, null, null, null, null],
    'ANULADO' => [null, null, null, null, null],
];

const TRANSITION_ACTIONS = ['request', 'approve', 'dispatch', 'receive', 'void'];

dataset('transition_matrix', function () {
    $rows = [];
    foreach (TRANSITION_MATRIX as $status => $targets) {
        foreach (TRANSITION_ACTIONS as $index => $action) {
            $rows["{$action} desde {$status}"] = [TransferStatus::from($status), TransferAction::from($action), $targets[$index]];
        }
    }

    return $rows;
});

it('cubre exactamente 35 combinaciones, 7 permitidas', function () {
    $allowed = 0;
    foreach (TRANSITION_MATRIX as $targets) {
        $allowed += count(array_filter($targets));
    }

    expect(count(TRANSITION_MATRIX) * count(TRANSITION_ACTIONS))->toBe(35)
        ->and($allowed)->toBe(7)
        ->and(array_keys(TRANSITION_MATRIX))->toBe(TransferStatus::values())
        ->and(TRANSITION_ACTIONS)->toBe(array_map(fn (TransferAction $a) => $a->value, TransferAction::cases()));
});

it('permite solo las transiciones de RN-07 y rechaza el resto', function (TransferStatus $from, TransferAction $action, ?string $targets) {
    expect(TransferTransitions::allows($action, $from))->toBe($targets !== null);

    if ($targets === null) {
        expect(fn () => TransferTransitions::assertAllowed($action, $from))->toThrow(InvalidTransferTransition::class);

        return;
    }

    $choices = explode('|', $targets);
    if (count($choices) === 1) {
        expect(TransferTransitions::target($action, $from)->value)->toBe($choices[0]);
    }
    foreach ($choices as $choice) {
        expect(TransferTransitions::target($action, $from, TransferStatus::from($choice))->value)->toBe($choice);
    }
})->with('transition_matrix');

it('trata un destino ajeno a la tabla como defecto, no como flujo', function () {
    expect(fn () => TransferTransitions::target(TransferAction::Receive, TransferStatus::InTransit, TransferStatus::Voided))
        ->toThrow(LogicException::class);
});
