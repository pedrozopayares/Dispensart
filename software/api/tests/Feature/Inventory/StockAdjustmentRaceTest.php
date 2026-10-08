<?php

use App\Models\KardexMovement;
use App\Models\Lot;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Support\RaceRunner;

// inventory "Carrera por la última unidad" y "Ajustes positivos simultáneos sobre existencia inexistente"
// (RN-03, design D7/D8). Procesos reales con conexiones propias: las filas deben estar confirmadas, así que
// nada de RefreshDatabase. DatabaseMigrations migra al entrar y hace rollback de todo al salir (ejerce cada
// down()); el trigger del kardex sigue activo todo el tiempo. Filas nuevas por iteración.

uses(DatabaseMigrations::class);

const RACE_ITERATIONS = 10;

beforeEach(function () {
    $this->regente = User::factory()->regente()->create();
});

it('serializa la carrera por la última unidad: un 201, un 409 y nunca negativo, 10 de 10', function () {
    $outcomes = [];
    for ($i = 1; $i <= RACE_ITERATIONS; $i++) {
        $stock = stockOf(1);
        $body = adjustmentBody($stock, -1, "Carrera {$i}");

        $results = RaceRunner::postAdjustments([
            ['user_id' => $this->regente->id, 'body' => $body],
            ['user_id' => $this->regente->id, 'body' => $body],
        ]);

        $statuses = array_column($results, 'status');
        sort($statuses);
        $outcomes[$i] = [
            'statuses' => $statuses,
            'codes' => array_values(array_filter(array_column($results, 'code'))),
            'quantity' => $stock->fresh()?->quantity,
            'movements' => movementsOf($stock)->count(),
        ];
    }

    $expected = ['statuses' => [201, 409], 'codes' => ['insufficient_stock'], 'quantity' => 0, 'movements' => 2];
    $failed = array_filter($outcomes, fn (array $outcome) => $outcome !== $expected);
    expect($failed)->toBe([], count($failed).'/'.RACE_ITERATIONS.' iteraciones fuera de lo esperado');
    expect(KardexMovement::where('balance_after', '<', 0)->count())->toBe(0);
});

it('acumula ajustes positivos simultáneos sobre una existencia inexistente en una sola fila, 10 de 10', function () {
    $outcomes = [];
    for ($i = 1; $i <= RACE_ITERATIONS; $i++) {
        $warehouse = Warehouse::factory()->create();
        $lot = Lot::factory()->create();
        $body = fn (int $quantity) => [
            'warehouse_id' => $warehouse->id, 'lot_id' => $lot->id, 'quantity' => $quantity, 'reason' => "Carrera {$i}",
        ];

        $results = RaceRunner::postAdjustments([
            ['user_id' => $this->regente->id, 'body' => $body(3)],
            ['user_id' => $this->regente->id, 'body' => $body(2)],
        ]);

        $stocks = Stock::where('warehouse_id', $warehouse->id)->where('lot_id', $lot->id)->get();
        $movements = $stocks->count() === 1 ? movementsOf($stocks->first()) : collect();
        $outcomes[$i] = [
            'statuses' => array_column($results, 'status'),
            'stocks' => $stocks->count(),
            'quantity' => $stocks->sum('quantity'),
            // Saldos en orden de inserción: cada uno parte del anterior real.
            'chain' => $movements->count() === 2
                && $movements[0]->balance_after === $movements[0]->quantity
                && $movements[1]->balance_after === $movements[0]->balance_after + $movements[1]->quantity,
        ];
    }

    $expected = ['statuses' => [201, 201], 'stocks' => 1, 'quantity' => 5, 'chain' => true];
    $failed = array_filter($outcomes, fn (array $outcome) => $outcome !== $expected);
    expect($failed)->toBe([], count($failed).'/'.RACE_ITERATIONS.' iteraciones fuera de lo esperado');
});
