<?php

namespace App\Services\Inventory;

use App\Exceptions\InsufficientStock;
use App\Models\KardexMovement;
use App\Models\Lot;
use App\Models\Stock;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Única puerta de escritura de existencias (design D2). Garantiza, en una sola transacción:
 * bloqueo de filas en la clave global (lots.expires_on, lots.id, warehouse_id), saldo nunca negativo
 * (RN-03) y exactamente un movimiento de kardex por cambio con su saldo resultante (RN-06).
 * La regla de lote vencido no vive aquí: la aplica cada llamador.
 */
final class StockLedger
{
    /**
     * Si el llamador ya abrió una transacción, esta queda como punto de guardado dentro de la suya.
     *
     * @param  list<StockChange>  $changes
     * @return list<KardexMovement>
     *
     * @throws InsufficientStock antes de escribir nada, si algún saldo quedaría negativo o falta la existencia
     */
    public function apply(array $changes): array
    {
        if ($changes === []) {
            return [];
        }

        return DB::transaction(function () use ($changes): array {
            $lots = $this->loadLots($changes);
            $stockIds = $this->checkBalances($changes, $this->lockStocks($changes, $lots));

            $movements = [];
            foreach ($changes as $change) {
                $movements[] = $this->write($change, $stockIds[$change->stockKey()], $lots[$change->lotId]);
            }

            return $movements;
        });
    }

    /**
     * @param  list<StockChange>  $changes
     * @return array<int, Lot>
     */
    private function loadLots(array $changes): array
    {
        $ids = array_values(array_unique(array_map(fn (StockChange $c): int => $c->lotId, $changes)));
        $lots = Lot::query()->findMany($ids, ['id', 'product_id', 'expires_on'])->keyBy('id')->all();

        $missing = array_diff($ids, array_keys($lots));
        if ($missing !== []) {
            throw (new ModelNotFoundException)->setModel(Lot::class, array_values($missing));
        }

        return $lots;
    }

    /**
     * Bloquea cada existencia afectada, una por una, en el orden de la clave global. Una existencia que
     * recibe unidades y aún no existe se crea con cantidad 0 (ON CONFLICT DO NOTHING: dos creaciones
     * simultáneas no chocan) antes de bloquearla.
     *
     * @param  list<StockChange>  $changes
     * @param  array<int, Lot>  $lots
     * @return array<string, array{id: int, quantity: int}|null>
     */
    private function lockStocks(array $changes, array $lots): array
    {
        $keys = [];
        foreach ($changes as $change) {
            $key = $change->stockKey();
            $keys[$key] ??= ['warehouse_id' => $change->warehouseId, 'lot' => $lots[$change->lotId], 'receives' => false];
            $keys[$key]['receives'] = $keys[$key]['receives'] || $change->delta > 0;
        }

        uasort($keys, fn (array $a, array $b): int => [$a['lot']->expires_on->toDateString(), $a['lot']->id, $a['warehouse_id']]
            <=> [$b['lot']->expires_on->toDateString(), $b['lot']->id, $b['warehouse_id']]);

        $locked = [];
        foreach ($keys as $key => ['warehouse_id' => $warehouseId, 'lot' => $lot, 'receives' => $receives]) {
            if ($receives) {
                DB::table('stocks')->insertOrIgnore([
                    'warehouse_id' => $warehouseId,
                    'product_id' => $lot->product_id,
                    'lot_id' => $lot->id,
                    'quantity' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $row = Stock::query()
                ->where('warehouse_id', $warehouseId)
                ->where('lot_id', $lot->id)
                ->lockForUpdate()
                ->first(['id', 'quantity']);

            $locked[$key] = $row === null ? null : ['id' => $row->id, 'quantity' => $row->quantity];
        }

        return $locked;
    }

    /**
     * Con todas las filas bloqueadas, recorre los cambios en su orden: ningún saldo intermedio puede
     * quedar negativo. Se valida todo antes de la primera escritura.
     *
     * @param  list<StockChange>  $changes
     * @param  array<string, array{id: int, quantity: int}|null>  $locked
     * @return array<string, int> id de la existencia por clave bodega + lote
     */
    private function checkBalances(array $changes, array $locked): array
    {
        $balances = [];
        $stockIds = [];
        foreach ($changes as $change) {
            $key = $change->stockKey();
            $row = $locked[$key] ?? null;

            // Sin fila: nada que restar (una existencia que recibe unidades ya fue creada al bloquear).
            if ($row === null || ($balances[$key] ?? $row['quantity']) + $change->delta < 0) {
                throw new InsufficientStock;
            }

            $balances[$key] = ($balances[$key] ?? $row['quantity']) + $change->delta;
            $stockIds[$key] = $row['id'];
        }

        return $stockIds;
    }

    /**
     * Actualiza la existencia y escribe su movimiento con el saldo devuelto por la propia base.
     */
    private function write(StockChange $change, int $stockId, Lot $lot): KardexMovement
    {
        /** @var object{quantity: int} $updated */
        $updated = DB::selectOne(
            'UPDATE stocks SET quantity = quantity + ?, updated_at = now() WHERE id = ? RETURNING quantity',
            [$change->delta, $stockId],
            false,
        );

        $movement = (new KardexMovement)->forceFill([
            'warehouse_id' => $change->warehouseId,
            'product_id' => $lot->product_id,
            'lot_id' => $lot->id,
            'type' => $change->type,
            'quantity' => $change->delta,
            'balance_after' => $updated->quantity,
            'reason' => $change->reason,
            'user_id' => $change->userId,
        ]);
        $movement->save();

        // created_at viene del DEFAULT de la base.
        return $movement->refresh();
    }
}
