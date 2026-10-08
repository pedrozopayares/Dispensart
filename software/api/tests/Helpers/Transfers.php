<?php

use App\Enums\TransferStatus;
use App\Models\AuditEvent;
use App\Models\KardexMovement;
use App\Models\Lot;
use App\Models\Stock;
use App\Models\Transfer;
use App\Models\TransferDiscrepancy;
use App\Models\TransferLine;
use App\Models\User;
use Illuminate\Testing\TestResponse;

// Ayudas de las pruebas de traslados (S4).

/**
 * Traslado de fábrica en el estado dado con una línea por [lote, cantidad], en ese orden de id.
 *
 * @param  list<array{0: Lot, 1: int}>  $lines
 * @param  array<string, mixed>  $attributes
 */
function transferWith(array $lines, TransferStatus $status = TransferStatus::Approved, array $attributes = []): Transfer
{
    $transfer = Transfer::factory()->inStatus($status)->create($attributes);
    foreach ($lines as [$lot, $quantity]) {
        TransferLine::factory()->create(['transfer_id' => $transfer->id, 'lot_id' => $lot->id, 'quantity' => $quantity]);
    }

    return $transfer->load('lines');
}

/**
 * Existencia del lote en la bodega origen (o destino) del traslado, nacida con su movimiento `entrada`.
 */
function originStock(Transfer $transfer, Lot $lot, int $quantity): Stock
{
    return stockOf($quantity, ['warehouse_id' => $transfer->origin_warehouse_id, 'lot_id' => $lot->id]);
}

/**
 * Existencia (o null) del lote en una bodega.
 */
function stockAt(int $warehouseId, Lot $lot): ?Stock
{
    return Stock::query()->where('warehouse_id', $warehouseId)->where('lot_id', $lot->id)->first();
}

/**
 * Cuerpo de recepción: por defecto, todo lo despachado; `$received` por posición de línea.
 *
 * @param  list<int>|null  $received
 * @return array{lines: list<array{line_id: int, received_quantity: int}>}
 */
function receiveBody(Transfer $transfer, ?array $received = null): array
{
    $lines = [];
    foreach ($transfer->lines()->orderBy('id')->get()->values() as $index => $line) {
        $lines[] = ['line_id' => $line->id, 'received_quantity' => $received[$index] ?? $line->quantity];
    }

    return ['lines' => $lines];
}

/**
 * POST a una acción de traslado como el usuario dado.
 *
 * @param  array<string, mixed>  $body
 */
function transferAction(User $user, Transfer $transfer, string $action, array $body = []): TestResponse
{
    return test()->actingAs($user)->postJson("/api/transfers/{$transfer->id}/{$action}", $body);
}

function movementsOfType(string $type): int
{
    return KardexMovement::query()->where('type', $type)->count();
}

/**
 * Foto de todo lo que una acción de traslado puede cambiar: "nada cambia" = misma foto antes y después.
 *
 * @return array<string, mixed>
 */
function transferState(): array
{
    return [
        'transfers' => Transfer::query()->orderBy('id')->get()
            ->mapWithKeys(fn (Transfer $t): array => [$t->id => [$t->status->value, $t->requested_by, $t->approved_by, $t->dispatched_by, $t->received_by, $t->voided_by]])
            ->all(),
        'lines' => TransferLine::query()->orderBy('id')->get(['id', 'received_quantity', 'dispatch_movement_id', 'receipt_movement_id'])->toArray(),
        'discrepancies' => TransferDiscrepancy::query()->orderBy('id')->get(['id', 'status'])->toArray(),
        'stocks' => Stock::query()->orderBy('id')->pluck('quantity', 'id')->all(),
        'kardex' => KardexMovement::count(),
        'audit' => AuditEvent::count(),
    ];
}
