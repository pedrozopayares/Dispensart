<?php

use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

// Deuda D-auv-2: dos transacciones solapadas que escriben la misma existencia en orden inverso a su inicio.
// Conexiones reales distintas con filas confirmadas: nada de RefreshDatabase (DatabaseMigrations migra al
// entrar y revierte al salir, como las pruebas de carrera).

uses(DatabaseMigrations::class);

function kardexConnection(string $name): Connection
{
    config(['database.connections.'.$name => config('database.connections.'.config('database.default'))]);

    /** @var Connection */
    return DB::connection($name);
}

/**
 * Resta 1 a la existencia con su movimiento `ajuste`, como el libro: fila bloqueada, saldo devuelto por la base.
 */
function withdrawOne(Connection $connection, int $stockId, int $userId): void
{
    $stock = $connection->selectOne('SELECT * FROM stocks WHERE id = ? FOR UPDATE', [$stockId]);
    $balance = $connection->selectOne('UPDATE stocks SET quantity = quantity - 1 WHERE id = ? RETURNING quantity', [$stockId])->quantity;
    $connection->table('kardex_movements')->insert([
        'warehouse_id' => $stock->warehouse_id, 'product_id' => $stock->product_id, 'lot_id' => $stock->lot_id,
        'type' => 'ajuste', 'quantity' => -1, 'balance_after' => $balance, 'reason' => 'Conteo físico', 'user_id' => $userId,
    ]);
}

it('lista el kardex de una existencia en el orden real de sus saldos aunque las transacciones se solapen', function () {
    $stock = stockOf(10);
    $regente = User::factory()->regente()->create();
    $first = kardexConnection('kardex_first');
    $second = kardexConnection('kardex_second');

    // La primera transacción empieza antes, pero escribe después de que la segunda confirma. created_at es
    // timestamptz(0) (segundos, redondeado): el inicio de la primera y las escrituras se separan más de 1 s,
    // o el desempate por id ocultaría el defecto.
    $first->beginTransaction();
    $first->select('SELECT 1');
    usleep(1_100_000);
    $second->beginTransaction();
    withdrawOne($second, $stock->id, $regente->id);
    $second->commit();
    withdrawOne($first, $stock->id, $regente->id);
    $first->commit();
    DB::purge('kardex_first');
    DB::purge('kardex_second');

    $balances = array_column(
        $this->actingAs($regente)->getJson('/api/kardex?lot_id='.$stock->lot_id)->assertOk()->json('data'),
        'balance_after',
    );

    // Del más reciente al más antiguo: 8 (escrito al final), 9, 10 (entrada).
    expect($balances)->toBe([8, 9, 10]);
});
