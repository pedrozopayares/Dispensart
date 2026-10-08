<?php

use App\Enums\Role;
use App\Enums\TransferStatus;
use App\Models\Transfer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// tarea 1.3: un traslado sembrable directamente en cada uno de los 7 estados, con actores coherentes y
// aceptado por todas las restricciones de la base.

it('siembra un traslado en cada estado con solicitante = creador y aprobador regente distinto', function (TransferStatus $status) {
    $transfer = Transfer::factory()->inStatus($status)->create()->refresh();

    expect($transfer->status)->toBe($status)
        ->and($transfer->creator->role)->toBe(Role::AuxiliarFarmacia);
    if ($transfer->requested_by !== null) {
        expect($transfer->requested_by)->toBe($transfer->created_by);
    }
    if ($transfer->approved_by !== null) {
        expect($transfer->approved_by)->not->toBe($transfer->created_by)
            ->and($transfer->approver?->role)->toBe(Role::RegenteFarmacia);
    }
})->with(fn () => array_combine(TransferStatus::values(), array_map(fn (TransferStatus $s) => [$s], TransferStatus::cases())));
