<?php

namespace App\Enums;

/**
 * Tipos de movimiento del kardex (RN-06). Los literales son los de la regla; S3 y S4 escriben los suyos.
 */
enum MovementType: string
{
    case Inbound = 'entrada';
    case DispensingOutbound = 'salida_dispensacion';
    case TransferOutbound = 'salida_traslado';
    case TransferInbound = 'entrada_traslado';
    case Adjustment = 'ajuste';

    /**
     * Signo permitido por tipo: entradas suman, salidas restan, el ajuste va en ambos sentidos; nunca 0.
     * La base lo repite en kardex_movements_quantity_sign_check.
     */
    public function allowsQuantity(int $quantity): bool
    {
        if ($quantity === 0) {
            return false;
        }

        return match ($this) {
            self::Inbound, self::TransferInbound => $quantity > 0,
            self::DispensingOutbound, self::TransferOutbound => $quantity < 0,
            self::Adjustment => true,
        };
    }
}
