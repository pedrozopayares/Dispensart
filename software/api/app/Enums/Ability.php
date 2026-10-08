<?php

namespace App\Enums;

/**
 * Capacidades del sistema (identity-access "Mapa de capacidades por rol"). Cada caso es también un Gate.
 */
enum Ability: string
{
    case CatalogView = 'catalog.view';
    case CatalogManage = 'catalog.manage';
    case UsersManage = 'users.manage';
    case InventoryView = 'inventory.view';
    case InventoryAdjust = 'inventory.adjust';
    case DispensationsCreate = 'dispensations.create';
    case ControlledDrugsAuthorize = 'controlled_drugs.authorize';
    case TransfersView = 'transfers.view';
    case TransfersCreate = 'transfers.create';
    case TransfersReceive = 'transfers.receive';
    case TransfersApprove = 'transfers.approve';
    case PrescriptionsCreate = 'prescriptions.create';
    case PatientsView = 'patients.view';
}
