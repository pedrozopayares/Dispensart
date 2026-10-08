<?php

use App\Models\KardexMovement;
use App\Models\Stock;
use Illuminate\Database\Eloquent\Collection;

// Ayudas de las pruebas de inventario y kardex (S2). Pest carga tests/Helpers automáticamente.

/**
 * Existencia con la cantidad dada; si es > 0 nace con su movimiento `entrada` (StockFactory).
 *
 * @param  array<string, mixed>  $attributes
 */
function stockOf(int $quantity, array $attributes = []): Stock
{
    return Stock::factory()->create(['quantity' => $quantity, ...$attributes]);
}

/**
 * Movimientos de una existencia en orden de inserción.
 *
 * @return Collection<int, KardexMovement>
 */
function movementsOf(Stock $stock): Collection
{
    return KardexMovement::query()
        ->where('warehouse_id', $stock->warehouse_id)
        ->where('lot_id', $stock->lot_id)
        ->orderBy('id')
        ->get();
}

/**
 * Cuerpo válido de POST /api/stock-adjustments sobre la bodega + lote de la existencia.
 *
 * @return array{warehouse_id: int, lot_id: int, quantity: int, reason: string}
 */
function adjustmentBody(Stock $stock, int $quantity, string $reason = 'Conteo físico'): array
{
    return ['warehouse_id' => $stock->warehouse_id, 'lot_id' => $stock->lot_id, 'quantity' => $quantity, 'reason' => $reason];
}
