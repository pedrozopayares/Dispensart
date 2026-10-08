<?php

namespace App\Enums;

/**
 * Acciones que cambian el estado de un traslado (RN-07, design D2).
 */
enum TransferAction: string
{
    case Request = 'request';
    case Approve = 'approve';
    case Dispatch = 'dispatch';
    case Receive = 'receive';
    case Void = 'void';
}
