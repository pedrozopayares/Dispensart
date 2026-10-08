<?php

use App\Models\Product;
use App\Models\Warehouse;
use App\Queries\AlertQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// inventory-alerts «Alerta de stock bajo mínimo» a nivel de consulta (AlertQuery::lowStock), reloj fijado en 2027.
// Los datos y la afirmación de cada escenario viven en tests/Helpers/Alerts.php; la ruta los repite.

beforeEach(function () {
    freezeAlertClock();
});

/**
 * Corre el escenario contra la consulta, sin filtro de bodega.
 */
function lowStockQueryScenario(string $title): void
{
    lowStockScenarios()[$title](fn (): array => lowStockFromQuery(app(AlertQuery::class)->lowStock(null)));
}

test('Producto bajo su mínimo', fn () => lowStockQueryScenario('Producto bajo su mínimo'));

test('Existencia igual al mínimo', fn () => lowStockQueryScenario('Existencia igual al mínimo'));

test('Suma de varios lotes cubre el mínimo', fn () => lowStockQueryScenario('Suma de varios lotes cubre el mínimo'));

test('Sin existencias con mínimo definido', fn () => lowStockQueryScenario('Sin existencias con mínimo definido'));

test('Existencia vencida no cuenta', fn () => lowStockQueryScenario('Existencia vencida no cuenta'));

test('Mínimo propio de cada bodega', fn () => lowStockQueryScenario('Mínimo propio de cada bodega'));

test('Producto sin mínimo', fn () => lowStockQueryScenario('Producto sin mínimo'));

test('Unidades en tránsito no cuentan hasta la recepción', fn () => lowStockQueryScenario('Unidades en tránsito no cuentan hasta la recepción'));

test('el filtro de bodega deja solo sus pares', function () {
    $mine = Warehouse::factory()->create();
    $other = Warehouse::factory()->create();
    $product = Product::factory()->create();
    minimumOf($mine, $product, 5);
    minimumOf($other, $product, 5);

    expect(lowStockFromQuery(app(AlertQuery::class)->lowStock($mine->id)))
        ->toBe([lowStockRow($mine, $product, 5, 0)]);
});
