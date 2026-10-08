<?php

use App\Enums\MovementType;
use App\Exceptions\InsufficientStock;
use App\Models\KardexMovement;
use App\Models\Lot;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\StockChange;
use App\Services\Inventory\StockLedger;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// kardex "Un movimiento por cada cambio de existencia" e inventory "Ajuste nunca deja stock negativo" en el
// libro de stock (design D2), sin HTTP.

function adjustment(Stock $stock, int $delta, ?int $userId = null, string $reason = 'Conteo físico'): StockChange
{
    return new StockChange($stock->warehouse_id, $stock->lot_id, $delta, MovementType::Adjustment,
        $userId ?? User::factory()->regente()->create()->id, $reason);
}

it('escribe exactamente un movimiento por cambio con la variación y el saldo resultante', function () {
    $stock = stockOf(10);

    $movements = app(StockLedger::class)->apply([adjustment($stock, -3)]);

    expect($movements)->toHaveCount(1)
        ->and($movements[0]->quantity)->toBe(-3)
        ->and($movements[0]->balance_after)->toBe(7)
        ->and($movements[0]->type)->toBe(MovementType::Adjustment)
        ->and(movementsOf($stock))->toHaveCount(2)
        ->and($stock->fresh()?->quantity)->toBe(7);
});

it('encadena los saldos tras +4, -2 y -1 sobre una existencia de 6', function () {
    $stock = stockOf(6);
    $ledger = app(StockLedger::class);

    foreach ([4, -2, -1] as $delta) {
        $ledger->apply([adjustment($stock, $delta)]);
    }

    $movements = movementsOf($stock);
    expect($movements->pluck('balance_after')->all())->toBe([6, 10, 8, 7])
        ->and($stock->fresh()?->quantity)->toBe(7)
        ->and($movements->sum('quantity'))->toBe(7)
        ->and($movements->last()?->balance_after)->toBe(7);
});

it('encadena varios cambios de la misma existencia pedidos en una sola llamada', function () {
    $stock = stockOf(6);
    $userId = User::factory()->regente()->create()->id;

    $movements = app(StockLedger::class)->apply([
        adjustment($stock, 4, $userId), adjustment($stock, -2, $userId), adjustment($stock, -1, $userId),
    ]);

    expect(array_map(fn (KardexMovement $m) => $m->balance_after, $movements))->toBe([10, 8, 7])
        ->and($stock->fresh()?->quantity)->toBe(7);
});

it('deja la existencia exactamente en cero con saldo 0', function () {
    $stock = stockOf(5);

    [$movement] = app(StockLedger::class)->apply([adjustment($stock, -5)]);

    expect($movement->balance_after)->toBe(0)
        ->and($stock->fresh()?->quantity)->toBe(0);
});

it('rechaza un cambio mayor que la existencia sin escribir nada', function () {
    $stock = stockOf(5);

    expect(fn () => app(StockLedger::class)->apply([adjustment($stock, -6)]))->toThrow(InsufficientStock::class);

    expect($stock->fresh()?->quantity)->toBe(5)
        ->and(movementsOf($stock))->toHaveCount(1);
});

it('valida todos los saldos antes de la primera escritura', function () {
    $enough = stockOf(5);
    $short = stockOf(1);

    expect(fn () => app(StockLedger::class)->apply([adjustment($enough, -1), adjustment($short, -2)]))
        ->toThrow(InsufficientStock::class);

    expect($enough->fresh()?->quantity)->toBe(5)
        ->and(movementsOf($enough))->toHaveCount(1)
        ->and(KardexMovement::count())->toBe(2);
});

it('rechaza un cambio negativo sin existencia previa y no crea la existencia', function () {
    $warehouse = Warehouse::factory()->create();
    $lot = Lot::factory()->create();
    $userId = User::factory()->regente()->create()->id;

    expect(fn () => app(StockLedger::class)->apply([
        new StockChange($warehouse->id, $lot->id, -1, MovementType::Adjustment, $userId, 'Conteo físico'),
    ]))->toThrow(InsufficientStock::class);

    expect(Stock::count())->toBe(0)
        ->and(KardexMovement::count())->toBe(0);
});

it('crea la existencia que falta al recibir unidades, con su movimiento', function () {
    $warehouse = Warehouse::factory()->create();
    $lot = Lot::factory()->create();

    [$movement] = app(StockLedger::class)->apply([new StockChange($warehouse->id, $lot->id, 4, MovementType::Inbound)]);

    $stock = Stock::where('warehouse_id', $warehouse->id)->where('lot_id', $lot->id)->sole();
    expect($stock->quantity)->toBe(4)
        ->and($stock->product_id)->toBe($lot->product_id)
        ->and($movement->balance_after)->toBe(4)
        ->and($movement->user_id)->toBeNull();
});

it('revierte la existencia si falla la escritura del movimiento después de actualizarla', function () {
    $stock = stockOf(10);

    // Usuario inexistente: el UPDATE de la existencia ya corrió; el INSERT del movimiento viola la FK.
    expect(fn () => app(StockLedger::class)->apply([adjustment($stock, -3, userId: 999_999)]))
        ->toThrow(QueryException::class);

    expect($stock->fresh()?->quantity)->toBe(10)
        ->and(movementsOf($stock))->toHaveCount(1);
});

it('ordena las existencias por la clave global de bloqueo: vencimiento, lote y bodega', function () {
    [$w1, $w2] = Warehouse::factory()->count(2)->create();
    $later = Lot::factory()->expiringInDays(90)->create();
    $soonB = Lot::factory()->expiringInDays(10)->create();
    $soonA = Lot::factory()->expiringInDays(10)->create();
    $expected = [
        stockOf(1, ['warehouse_id' => $w2->id, 'lot_id' => $soonB->id]),
        stockOf(1, ['warehouse_id' => $w1->id, 'lot_id' => $soonB->id]),
        stockOf(1, ['warehouse_id' => $w1->id, 'lot_id' => $later->id]),
        stockOf(1, ['warehouse_id' => $w1->id, 'lot_id' => $soonA->id]),
    ];

    $ids = Stock::query()->inLockOrder()->pluck('stocks.id')->all();

    expect($ids)->toBe([$expected[1]->id, $expected[0]->id, $expected[3]->id, $expected[2]->id]);
});
