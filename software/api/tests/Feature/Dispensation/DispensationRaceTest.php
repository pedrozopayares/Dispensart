<?php

use App\Models\Dispensation;
use App\Models\KardexMovement;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Support\RaceRunner;

// dispensation "Dispensación atómica y segura ante concurrencia" e "Idempotencia" (carreras), RN-03, RN-04,
// RN-09; design D10. Procesos reales (RaceRunner) con conexiones propias y filas confirmadas: nada de
// RefreshDatabase; DatabaseMigrations migra al entrar y revierte al salir. Filas nuevas por iteración.

uses(DatabaseMigrations::class);

const DISPENSATION_RACE_ITERATIONS = 10;

beforeEach(function () {
    $this->auxiliar = User::factory()->auxiliar()->create();
    $this->regente = User::factory()->regente()->create();
});

/**
 * @param  array<string, mixed>  $body
 * @return array{user_id: int, body: array<string, mixed>, headers: array<string, string>}
 */
function raceRequest(User $user, array $body, ?string $key = null): array
{
    return ['user_id' => $user->id, 'body' => $body, 'headers' => ['Idempotency-Key' => $key ?? newIdempotencyKey()]];
}

/**
 * @param  list<array{status: int, code: string|null, body: array<string, mixed>|null}>  $results
 * @return list<int>
 */
function sortedStatuses(array $results): array
{
    $statuses = array_column($results, 'status');
    sort($statuses);

    return $statuses;
}

/**
 * @param  array<int, array<string, mixed>>  $outcomes
 */
function expectEveryIteration(array $outcomes, array $expected): void
{
    $failed = array_filter($outcomes, fn (array $outcome) => $outcome !== $expected);
    expect($failed)->toBe([], count($failed).'/'.DISPENSATION_RACE_ITERATIONS.' iteraciones fuera de lo esperado');
}

function dispensingMovements(Stock $stock): int
{
    return movementsOf($stock)->where('type.value', 'salida_dispensacion')->count();
}

it('serializa la carrera por la última unidad: un 201, un 409 y nunca negativo, 10 de 10', function () {
    $outcomes = [];
    for ($i = 1; $i <= DISPENSATION_RACE_ITERATIONS; $i++) {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $stock = lotStock($warehouse, $product, 30, 1);
        $a = prescriptionWith([[$product, 5]]);
        $b = prescriptionWith([[$product, 5]]);

        $results = RaceRunner::post('/api/dispensations', [
            raceRequest($this->auxiliar, dispensationBody($a, $warehouse, [[$product, 1]])),
            raceRequest($this->regente, dispensationBody($b, $warehouse, [[$product, 1]])),
        ]);

        $outcomes[$i] = [
            'statuses' => sortedStatuses($results),
            'codes' => array_values(array_filter(array_column($results, 'code'))),
            'quantity' => $stock->fresh()?->quantity,
            'movements' => dispensingMovements($stock),
        ];
    }

    expectEveryIteration($outcomes, ['statuses' => [201, 409], 'codes' => ['insufficient_stock'], 'quantity' => 0, 'movements' => 1]);
    expect(KardexMovement::where('balance_after', '<', 0)->count())->toBe(0);
});

it('lleva al perdedor al lote siguiente: dos 201, L1 y L2 en 0, 10 de 10', function () {
    $outcomes = [];
    for ($i = 1; $i <= DISPENSATION_RACE_ITERATIONS; $i++) {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $l1 = lotStock($warehouse, $product, 10, 1);
        $l2 = lotStock($warehouse, $product, 40, 1);
        $a = prescriptionWith([[$product, 5]]);
        $b = prescriptionWith([[$product, 5]]);

        $results = RaceRunner::post('/api/dispensations', [
            raceRequest($this->auxiliar, dispensationBody($a, $warehouse, [[$product, 1]])),
            raceRequest($this->regente, dispensationBody($b, $warehouse, [[$product, 1]])),
        ]);

        $lots = array_map(fn (array $r) => $r['body']['data']['lines'][0]['lot_id'] ?? null, $results);
        sort($lots);
        $outcomes[$i] = [
            'statuses' => sortedStatuses($results),
            'lots' => $lots === [$l1->lot_id, $l2->lot_id],
            'quantities' => [$l1->fresh()?->quantity, $l2->fresh()?->quantity],
            'movements' => dispensingMovements($l1) + dispensingMovements($l2),
        ];
    }

    expectEveryIteration($outcomes, ['statuses' => [201, 201], 'lots' => true, 'quantities' => [0, 0], 'movements' => 2]);
});

it('serializa la carrera por el pendiente de un ítem: un 201, un 422 exceeds_prescription, 10 de 10', function () {
    $outcomes = [];
    for ($i = 1; $i <= DISPENSATION_RACE_ITERATIONS; $i++) {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $other = Product::factory()->create();
        lotStock($warehouse, $product, 30, 100);
        // Segundo ítem con pendiente: el perdedor no ve la prescripción agotada (design D4).
        $prescription = prescriptionWith([[$product, 5], [$other, 3]]);
        $body = dispensationBody($prescription, $warehouse, [[$product, 5]]);

        $results = RaceRunner::post('/api/dispensations', [
            raceRequest($this->auxiliar, $body),
            raceRequest($this->auxiliar, $body),
        ]);

        $outcomes[$i] = [
            'statuses' => sortedStatuses($results),
            'codes' => array_values(array_filter(array_column($results, 'code'))),
            'dispensed' => itemOf($prescription, $product)->dispensed_quantity,
            'dispensations' => Dispensation::where('prescription_id', $prescription->id)->count(),
        ];
    }

    expectEveryIteration($outcomes, ['statuses' => [201, 422], 'codes' => ['exceeds_prescription'], 'dispensed' => 5, 'dispensations' => 1]);
});

it('no se bloquea con productos pedidos en orden cruzado: dos 201 y cada existencia baja la suma, 10 de 10', function () {
    $outcomes = [];
    for ($i = 1; $i <= DISPENSATION_RACE_ITERATIONS; $i++) {
        $warehouse = Warehouse::factory()->create();
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();
        $stockA = lotStock($warehouse, $productA, 30, 10);
        $stockB = lotStock($warehouse, $productB, 60, 10);
        $first = prescriptionWith([[$productA, 5], [$productB, 5]]);
        $second = prescriptionWith([[$productA, 5], [$productB, 5]]);

        // Barrera de filas: el padre bloquea ambas existencias; los dos workers esperan antes de tomar la primera.
        $results = RaceRunner::post('/api/dispensations', [
            raceRequest($this->auxiliar, dispensationBody($first, $warehouse, [[$productA, 2], [$productB, 3]])),
            raceRequest($this->regente, dispensationBody($second, $warehouse, [[$productB, 3], [$productA, 2]])),
        ], function (Connection $barrier) use ($stockA, $stockB): void {
            $barrier->select('SELECT id FROM stocks WHERE id IN (?, ?) ORDER BY id FOR UPDATE', [$stockA->id, $stockB->id]);
        });

        $outcomes[$i] = [
            'statuses' => sortedStatuses($results),
            'quantities' => [$stockA->fresh()?->quantity, $stockB->fresh()?->quantity],
        ];
    }

    expectEveryIteration($outcomes, ['statuses' => [201, 201], 'quantities' => [6, 4]]);
});

it('responde lo mismo a dos reintentos simultáneos con la misma clave y dispensa una vez, 10 de 10', function () {
    $outcomes = [];
    for ($i = 1; $i <= DISPENSATION_RACE_ITERATIONS; $i++) {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $stock = lotStock($warehouse, $product, 30, 10);
        $prescription = prescriptionWith([[$product, 10]]);
        $request = raceRequest($this->auxiliar, dispensationBody($prescription, $warehouse, [[$product, 2]]), newIdempotencyKey());

        $results = RaceRunner::post('/api/dispensations', [$request, $request]);

        $ids = array_map(fn (array $r) => $r['body']['data']['id'] ?? null, $results);
        $outcomes[$i] = [
            'statuses' => sortedStatuses($results),
            'same_id' => $ids[0] !== null && $ids[0] === $ids[1],
            'dispensations' => Dispensation::where('prescription_id', $prescription->id)->count(),
            'movements' => dispensingMovements($stock),
            'quantity' => $stock->fresh()?->quantity,
        ];
    }

    expectEveryIteration($outcomes, ['statuses' => [201, 201], 'same_id' => true, 'dispensations' => 1, 'movements' => 1, 'quantity' => 8]);
});
