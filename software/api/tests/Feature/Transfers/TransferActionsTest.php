<?php

use App\Actions\Transfers\ApproveTransfer;
use App\Actions\Transfers\CreateTransfer;
use App\Actions\Transfers\DispatchTransfer;
use App\Actions\Transfers\ReceiveTransfer;
use App\Actions\Transfers\RequestTransfer;
use App\Actions\Transfers\ResolveDiscrepancy;
use App\Actions\Transfers\VoidTransfer;
use App\Enums\AuditAction;
use App\Enums\DiscrepancyResolution;
use App\Enums\DiscrepancyStatus;
use App\Enums\TransferStatus;
use App\Exceptions\InsufficientStock;
use App\Exceptions\InvalidTransferTransition;
use App\Exceptions\LotExpired;
use App\Exceptions\SegregationOfDutiesViolation;
use App\Models\AuditEvent;
use App\Models\KardexMovement;
use App\Models\Lot;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Tareas 3.1–3.5 a nivel de servicio: cada acción llamada directamente, sin HTTP.

beforeEach(function () {
    $this->auxiliar = User::factory()->auxiliar()->create();
    $this->regente = User::factory()->regente()->create();
});

it('crea un BORRADOR con el producto del lote y el creador dado, sin tocar existencias', function () {
    [$origin, $destination] = Warehouse::factory()->count(2)->create();
    $lot = Lot::factory()->create();
    $stock = stockOf(5, ['warehouse_id' => $origin->id, 'lot_id' => $lot->id]);

    $transfer = app(CreateTransfer::class)->handle($this->auxiliar, [
        'origin_warehouse_id' => $origin->id, 'destination_warehouse_id' => $destination->id,
        'notes' => null, 'lines' => [['lot_id' => $lot->id, 'quantity' => 50]],
    ]);

    expect($transfer->status)->toBe(TransferStatus::Draft)
        ->and($transfer->created_by)->toBe($this->auxiliar->id)
        ->and($transfer->lines->sole()->product_id)->toBe($lot->product_id)
        ->and($stock->fresh()?->quantity)->toBe(5)
        ->and(KardexMovement::count())->toBe(1);
});

it('rechaza crear con un lote vencido sin dejar traslado', function () {
    [$origin, $destination] = Warehouse::factory()->count(2)->create();
    $lot = Lot::factory()->expiringInDays(0)->create();

    expect(fn () => app(CreateTransfer::class)->handle($this->auxiliar, [
        'origin_warehouse_id' => $origin->id, 'destination_warehouse_id' => $destination->id,
        'notes' => null, 'lines' => [['lot_id' => $lot->id, 'quantity' => 1]],
    ]))->toThrow(LotExpired::class);
    expect(transferState()['transfers'])->toBe([]);
});

it('solicita, aprueba con fila de bitácora y anula con fila de bitácora, sin stock', function () {
    $transfer = transferWith([[Lot::factory()->create(), 3]], TransferStatus::Draft, ['created_by' => $this->auxiliar->id]);

    app(RequestTransfer::class)->handle($this->auxiliar, $transfer);
    $approved = app(ApproveTransfer::class)->handle($this->regente, $transfer);
    $voided = app(VoidTransfer::class)->handle($this->regente, $transfer, 'Ya no se necesita');

    expect($approved->approved_by)->toBe($this->regente->id)
        ->and($voided->status)->toBe(TransferStatus::Voided)
        ->and(AuditEvent::query()->orderBy('id')->pluck('action')->all())->toBe([AuditAction::TransferApproved, AuditAction::TransferVoided])
        ->and(KardexMovement::count())->toBe(0);
});

it('rechaza que el creador apruebe antes de mirar el estado, sin fila de bitácora', function (TransferStatus $status) {
    $transfer = transferWith([[Lot::factory()->create(), 3]], $status, ['created_by' => $this->regente->id]);

    expect(fn () => app(ApproveTransfer::class)->handle($this->regente, $transfer))->toThrow(SegregationOfDutiesViolation::class);
    expect(AuditEvent::count())->toBe(0);
})->with(['borrador' => [TransferStatus::Draft], 'solicitado' => [TransferStatus::Requested]]);

it('despacha todo o nada y enlaza cada línea a su salida_traslado', function () {
    $a = Lot::factory()->create();
    $b = Lot::factory()->create();
    $transfer = transferWith([[$a, 3], [$b, 5]]);
    $stockA = originStock($transfer, $a, 10);
    originStock($transfer, $b, 4);

    expect(fn () => app(DispatchTransfer::class)->handle($this->auxiliar, $transfer))->toThrow(InsufficientStock::class);
    expect($stockA->fresh()?->quantity)->toBe(10)->and($transfer->fresh()?->status)->toBe(TransferStatus::Approved);

    $b2 = Lot::factory()->create();
    $ok = transferWith([[$a, 3], [$b2, 2]]);
    originStock($ok, $a, 10);
    originStock($ok, $b2, 2);
    $dispatched = app(DispatchTransfer::class)->handle($this->auxiliar, $ok);

    expect($dispatched->status)->toBe(TransferStatus::InTransit);
    foreach ($dispatched->lines as $line) {
        $movement = KardexMovement::findOrFail($line->dispatch_movement_id);
        expect([$movement->type->value, $movement->lot_id, $movement->quantity, $movement->reason])
            ->toBe(['salida_traslado', $line->lot_id, -$line->quantity, "Traslado #{$ok->id}"]);
    }
});

it('recibe en destino y deja una discrepancia pendiente por línea con faltante', function () {
    $a = Lot::factory()->create();
    $b = Lot::factory()->create();
    $transfer = transferWith([[$a, 3], [$b, 2]], TransferStatus::InTransit);
    [$lineA, $lineB] = $transfer->lines->all();

    $received = app(ReceiveTransfer::class)->handle($this->auxiliar, $transfer, [$lineA->id => 2, $lineB->id => 2]);

    expect($received->status)->toBe(TransferStatus::PartiallyReceived)
        ->and($received->discrepancies->map(fn ($d) => [$d->transfer_line_id, $d->shortage, $d->status])->all())
        ->toBe([[$lineA->id, 1, DiscrepancyStatus::Pending]])
        ->and(stockAt($transfer->destination_warehouse_id, $a)?->quantity)->toBe(2);
    expect(fn () => app(ReceiveTransfer::class)->handle($this->auxiliar, $transfer, [$lineA->id => 3, $lineB->id => 2]))
        ->toThrow(InvalidTransferTransition::class);
});

it('resuelve una discrepancia con ajuste en origen o como pérdida sin movimiento', function () {
    $lot = Lot::factory()->create();
    $transfer = transferWith([[$lot, 3], [Lot::factory()->create(), 2]], TransferStatus::InTransit);
    $received = app(ReceiveTransfer::class)->handle($this->auxiliar, $transfer, array_fill_keys($transfer->lines->pluck('id')->all(), 0));
    [$first, $second] = $received->discrepancies->all();

    $returned = app(ResolveDiscrepancy::class)->handle($this->regente, $transfer, $first, ['resolution' => DiscrepancyResolution::ReturnedToOrigin, 'reason' => 'Unidad no cargada']);
    $kardex = KardexMovement::count();
    $lost = app(ResolveDiscrepancy::class)->handle($this->regente, $transfer, $second, ['resolution' => DiscrepancyResolution::WrittenOff, 'reason' => 'Rotura']);

    expect(stockAt($transfer->origin_warehouse_id, $lot)?->quantity)->toBe(3)
        ->and(KardexMovement::findOrFail($returned->adjustment_movement_id)->reason)->toBe("Traslado #{$transfer->id}, discrepancia #{$first->id}: Unidad no cargada")
        ->and($lost->adjustment_movement_id)->toBeNull()
        ->and(KardexMovement::count())->toBe($kardex)
        ->and($transfer->fresh()?->status)->toBe(TransferStatus::PartiallyReceived);
});
