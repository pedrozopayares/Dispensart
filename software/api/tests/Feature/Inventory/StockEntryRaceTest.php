<?php

use App\Models\AuditEvent;
use App\Models\Lot;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Support\RaceRunner;

// inventory "Ingresos simultáneos del mismo lote nuevo" (S18 D3). Procesos reales con conexiones propias: las
// filas deben estar confirmadas, así que nada de RefreshDatabase. Barrera por defecto (LOCK TABLE kardex_movements
// IN SHARE MODE): el primero espera en el kardex con el lote insertado sin confirmar; el segundo espera en el
// índice único de lots. Ambos detenidos → se suelta. Filas nuevas por iteración. Los workers usan la fecha real.

uses(DatabaseMigrations::class);

test('Ingresos simultáneos del mismo lote nuevo: un solo lote, existencia de 5 y dos entradas, nunca 500, 10 de 10', function () {
    $iterations = 10;
    $regente = User::factory()->regente()->create();
    $expiresOn = now('America/Bogota')->addYear()->toDateString();
    $outcomes = [];
    for ($i = 1; $i <= $iterations; $i++) {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $body = fn (int $quantity) => [
            'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'lot_code' => "CARRERA-{$i}",
            'expires_on' => $expiresOn, 'quantity' => $quantity, 'reason' => "Carrera {$i}",
        ];
        $lastAuditId = (int) AuditEvent::max('id');

        $results = RaceRunner::post('/api/stock-entries', [
            ['user_id' => $regente->id, 'body' => $body(3)],
            ['user_id' => $regente->id, 'body' => $body(2)],
        ]);

        $lots = Lot::where('product_id', $product->id)->get();
        $stocks = Stock::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->get();
        $outcomes[$i] = [
            'statuses' => array_column($results, 'status'),
            'lots' => $lots->count(),
            'stocks' => $stocks->count(),
            'quantity' => $stocks->sum('quantity'),
            'entries' => $stocks->count() === 1 ? movementsOf($stocks->first())->where('type.value', 'entrada')->count() : 0,
            'audit' => AuditEvent::where('action', 'stock.received')->where('id', '>', $lastAuditId)->count(),
        ];
    }

    $expected = ['statuses' => [201, 201], 'lots' => 1, 'stocks' => 1, 'quantity' => 5, 'entries' => 2, 'audit' => 2];
    $failed = array_filter($outcomes, fn (array $outcome) => $outcome !== $expected);
    expect($failed)->toBe([], count($failed)."/{$iterations} iteraciones fuera de lo esperado: ".json_encode($failed));
});
