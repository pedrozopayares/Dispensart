<?php

namespace App\Services\Transfers;

use App\Enums\TransferAction;
use App\Enums\TransferStatus;
use App\Exceptions\InvalidTransferTransition;
use LogicException;

/**
 * Tabla de transiciones del traslado (RN-07, design D2): fuente única para las acciones y para la prueba de
 * las 35 combinaciones estado × acción. Lo que no está en la tabla está prohibido.
 */
final class TransferTransitions
{
    /**
     * acción → estado de origen → estados de destino posibles.
     *
     * @var array<string, array<string, list<string>>>
     */
    private const ALLOWED = [
        'request' => ['BORRADOR' => ['SOLICITADO']],
        'approve' => ['SOLICITADO' => ['APROBADO']],
        'dispatch' => ['APROBADO' => ['EN_TRANSITO']],
        'receive' => ['EN_TRANSITO' => ['RECIBIDO', 'RECIBIDO_PARCIAL']],
        'void' => ['BORRADOR' => ['ANULADO'], 'SOLICITADO' => ['ANULADO'], 'APROBADO' => ['ANULADO']],
    ];

    public static function allows(TransferAction $action, TransferStatus $from): bool
    {
        return isset(self::ALLOWED[$action->value][$from->value]);
    }

    /**
     * @throws InvalidTransferTransition
     */
    public static function assertAllowed(TransferAction $action, TransferStatus $from): void
    {
        if (! self::allows($action, $from)) {
            throw new InvalidTransferTransition;
        }
    }

    /**
     * Estado destino. Con más de un destino posible (recibir), el llamador indica cuál eligió y debe ser uno de
     * la tabla; elegir uno ajeno es un defecto, no un flujo.
     *
     * @throws InvalidTransferTransition
     */
    public static function target(TransferAction $action, TransferStatus $from, ?TransferStatus $chosen = null): TransferStatus
    {
        self::assertAllowed($action, $from);
        $targets = self::ALLOWED[$action->value][$from->value];

        if ($chosen === null && count($targets) === 1) {
            return TransferStatus::from($targets[0]);
        }
        if ($chosen !== null && in_array($chosen->value, $targets, true)) {
            return $chosen;
        }

        throw new LogicException("Destino no permitido para {$action->value} desde {$from->value}.");
    }
}
