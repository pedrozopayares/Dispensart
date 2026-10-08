<?php

namespace App\Enums;

/**
 * Resolución de un faltante (design D10): devolución al origen con ajuste positivo, o pérdida sin movimiento.
 */
enum DiscrepancyResolution: string
{
    case ReturnedToOrigin = 'returned_to_origin';
    case WrittenOff = 'written_off';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $resolution): string => $resolution->value, self::cases());
    }
}
