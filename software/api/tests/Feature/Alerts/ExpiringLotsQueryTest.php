<?php

use App\Models\Warehouse;
use App\Queries\AlertQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// inventory-alerts «Alerta de vencimiento» a nivel de consulta (AlertQuery::expiringLots), reloj fijado en 2027.
// Los datos y la afirmación de cada escenario viven en tests/Helpers/Alerts.php; la ruta los repite.

beforeEach(function () {
    freezeAlertClock();
});

/**
 * Corre el escenario contra la consulta, sin filtro de bodega.
 */
function expiringQueryScenario(string $title): void
{
    expiringScenarios()[$title](fn (): array => expiringFromQuery(app(AlertQuery::class)->expiringLots(null)));
}

test('Lote que vence en 90 días incluido', fn () => expiringQueryScenario('Lote que vence en 90 días incluido'));

test('Lote que vence en 91 días excluido', fn () => expiringQueryScenario('Lote que vence en 91 días excluido'));

test('Lote ya vencido con existencia', fn () => expiringQueryScenario('Lote ya vencido con existencia'));

test('Lote próximo a vencer sin existencia', fn () => expiringQueryScenario('Lote próximo a vencer sin existencia'));

test('Mismo lote en dos bodegas', fn () => expiringQueryScenario('Mismo lote en dos bodegas'));

test('Orden por vencimiento', fn () => expiringQueryScenario('Orden por vencimiento'));

test('Frontera del día en hora de Bogotá', fn () => expiringQueryScenario('Frontera del día en hora de Bogotá'));

// Control positivo de M1 y M9: el mutante solo mueve la frontera, el día 89 sigue dentro.
test('control 89 días', function () {
    $warehouse = Warehouse::factory()->create();
    $lot = lotExpiringIn(89);
    stockAtWarehouse($warehouse, $lot, 3);

    expect(rowsOfLot(expiringFromQuery(app(AlertQuery::class)->expiringLots(null)), $lot))
        ->toBe([expiringRow($warehouse, $lot, 3, 89, false)]);
});

test('el filtro de bodega deja solo sus existencias', function () {
    $mine = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();
    $lot = lotExpiringIn(30);
    stockAtWarehouse($mine, $lot, 2);
    stockAtWarehouse($other, $lot, 5);

    expect(expiringFromQuery(app(AlertQuery::class)->expiringLots($mine->id)))
        ->toBe([expiringRow($mine, $lot, 2, 30, false)]);
});
