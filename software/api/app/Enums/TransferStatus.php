<?php

namespace App\Enums;

/**
 * Estados del traslado con los literales de RN-07. Las transiciones viven solo en TransferTransitions; la base
 * repite el conjunto en transfers_status_check.
 */
enum TransferStatus: string
{
    case Draft = 'BORRADOR';
    case Requested = 'SOLICITADO';
    case Approved = 'APROBADO';
    case InTransit = 'EN_TRANSITO';
    case Received = 'RECIBIDO';
    case PartiallyReceived = 'RECIBIDO_PARCIAL';
    case Voided = 'ANULADO';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }
}
