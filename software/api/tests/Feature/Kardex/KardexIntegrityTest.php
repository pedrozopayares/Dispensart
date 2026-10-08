<?php

use App\Models\KardexMovement;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// kardex "Kardex de solo inserción" e "Integridad del movimiento en la base de datos": sentencias directas
// con el mismo usuario de base que usa la aplicación.

/**
 * Movimiento válido sobre la existencia dada (la FK compuesta exige que exista), con cambios opcionales.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function kardexRow(Stock $stock, array $overrides = []): array
{
    return [
        'warehouse_id' => $stock->warehouse_id,
        'product_id' => $stock->product_id,
        'lot_id' => $stock->lot_id,
        'type' => 'ajuste',
        'quantity' => 1,
        'balance_after' => 1,
        'reason' => 'Conteo físico',
        'user_id' => User::factory()->regente()->create()->id,
        ...$overrides,
    ];
}

describe('solo inserción', function () {
    it('acepta la inserción directa de un movimiento válido con fecha puesta por la base', function () {
        $stock = stockOf(0);

        DB::table('kardex_movements')->insert(kardexRow($stock));

        $movement = movementsOf($stock)->sole();
        expect($movement->quantity)->toBe(1)
            ->and($movement->created_at)->not->toBeNull();
    });

    it('rechaza en la base editar un movimiento y la fila no cambia', function (array $change) {
        $movement = movementsOf(stockOf(10))->sole();
        $before = DB::table('kardex_movements')->where('id', $movement->id)->first();

        expectRejectedByDatabase(
            fn () => DB::table('kardex_movements')->where('id', $movement->id)->update($change),
            sqlState: 'P0001',
            constraint: 'kardex_movements is append-only: UPDATE rejected',
        );
        expect(DB::table('kardex_movements')->where('id', $movement->id)->first())->toEqual($before);
    })->with([
        'cantidad' => [['quantity' => 99]],
        'motivo' => [['reason' => 'Reescrito']],
    ]);

    it('rechaza en la base borrar un movimiento y la fila permanece', function () {
        $movement = movementsOf(stockOf(10))->sole();

        expectRejectedByDatabase(
            fn () => DB::table('kardex_movements')->where('id', $movement->id)->delete(),
            sqlState: 'P0001',
            constraint: 'kardex_movements is append-only: DELETE rejected',
        );
        expect(KardexMovement::whereKey($movement->id)->exists())->toBeTrue();
    });

    it('rechaza en la base vaciar la tabla y las filas permanecen', function () {
        stockOf(10);
        stockOf(4);

        expectRejectedByDatabase(
            fn () => DB::statement('TRUNCATE kardex_movements'),
            sqlState: 'P0001',
            constraint: 'kardex_movements is append-only: TRUNCATE rejected',
        );
        expect(KardexMovement::count())->toBe(2);
    });

    it('sigue rechazando la edición con session_replication_role = replica (ENABLE ALWAYS)', function () {
        $movement = movementsOf(stockOf(10))->sole();

        expectRejectedByDatabase(
            function () use ($movement) {
                DB::statement('SET LOCAL session_replication_role = replica');
                DB::table('kardex_movements')->where('id', $movement->id)->update(['quantity' => 99]);
            },
            sqlState: 'P0001',
            constraint: 'append-only',
        );
        expect($movement->fresh()?->quantity)->toBe(10);
    });
});

describe('integridad del movimiento', function () {
    it('admite ya los tipos de traslado con su signo', function () {
        $stock = stockOf(0);

        DB::table('kardex_movements')->insert(kardexRow($stock, ['type' => 'salida_traslado', 'quantity' => -2, 'balance_after' => 0]));
        DB::table('kardex_movements')->insert(kardexRow($stock, ['type' => 'entrada_traslado', 'quantity' => 2, 'balance_after' => 2]));

        expect(movementsOf($stock)->pluck('type')->map->value->all())->toBe(['salida_traslado', 'entrada_traslado']);
    });

    it('rechaza un tipo fuera del conjunto', function () {
        $stock = stockOf(0);

        expectRejectedByDatabase(
            fn () => DB::table('kardex_movements')->insert(kardexRow($stock, ['type' => 'devolucion'])),
            sqlState: '23514',
            constraint: 'kardex_movements_type_check',
        );
        expect(KardexMovement::count())->toBe(0);
    });

    it('rechaza un signo contrario al tipo o una cantidad 0', function (string $type, int $quantity) {
        $stock = stockOf(0);

        expectRejectedByDatabase(
            fn () => DB::table('kardex_movements')->insert(kardexRow($stock, ['type' => $type, 'quantity' => $quantity])),
            sqlState: '23514',
            constraint: 'kardex_movements_quantity_sign_check',
        );
        expect(KardexMovement::count())->toBe(0);
    })->with([
        'entrada negativa' => ['entrada', -1],
        'salida_dispensacion positiva' => ['salida_dispensacion', 1],
        'salida_traslado positiva' => ['salida_traslado', 1],
        'entrada_traslado negativa' => ['entrada_traslado', -1],
        'ajuste en 0' => ['ajuste', 0],
        'entrada en 0' => ['entrada', 0],
    ]);

    it('rechaza un saldo resultante negativo', function () {
        $stock = stockOf(0);

        expectRejectedByDatabase(
            fn () => DB::table('kardex_movements')->insert(kardexRow($stock, ['balance_after' => -1])),
            sqlState: '23514',
            constraint: 'kardex_movements_balance_non_negative',
        );
        expect(KardexMovement::count())->toBe(0);
    });

    it('rechaza un ajuste sin usuario o sin motivo', function (array $override) {
        $stock = stockOf(0);

        expectRejectedByDatabase(
            fn () => DB::table('kardex_movements')->insert(kardexRow($stock, $override)),
            sqlState: '23514',
            constraint: 'kardex_movements_adjustment_attribution_check',
        );
        expect(KardexMovement::count())->toBe(0);
    })->with([
        'usuario nulo' => [['user_id' => null]],
        'motivo nulo' => [['reason' => null]],
        'motivo de solo espacios' => [['reason' => '   ']],
    ]);

    it('rechaza un movimiento sin existencia que lo respalde', function () {
        $stock = stockOf(0);

        expectRejectedByDatabase(
            fn () => DB::table('kardex_movements')->insert(kardexRow($stock, ['warehouse_id' => $stock->warehouse_id + 1000])),
            sqlState: '23503',
            constraint: 'kardex_movements_stock_foreign',
        );
        expect(KardexMovement::count())->toBe(0);
    });
});
