<?php

namespace App\Enums;

/**
 * Estado de una discrepancia de recepción: nace pendiente y el regente la resuelve una sola vez.
 */
enum DiscrepancyStatus: string
{
    case Pending = 'pending';
    case Resolved = 'resolved';
}
