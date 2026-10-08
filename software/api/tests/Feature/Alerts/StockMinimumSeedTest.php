<?php

use App\Models\Stock;
use App\Models\StockMinimum;
use App\Queries\AlertQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// inventory-alerts «Mínimos semilla»: distribución de design D7 sobre las existencias semilla e idempotencia.

/**
 * Mínimos como "BODEGA/PRODUCTO" => mínimo, por clave natural.
 *
 * @return array<string, int>
 */
function seededMinimums(): array
{
    return StockMinimum::with(['warehouse', 'product'])->get()
        ->mapWithKeys(fn (StockMinimum $m): array => ["{$m->warehouse?->code}/{$m->product?->code}" => $m->minimum_quantity])
        ->sortKeys()
        ->all();
}

test('Siembra inicial de mínimos', function () {
    $this->seed();

    $alerts = app(AlertQuery::class)->lowStock(null)
        ->mapWithKeys(fn (StockMinimum $m): array => [
            "{$m->warehouse?->code}/{$m->product?->code}" => [$m->minimum_quantity, $m->getAttribute('available_quantity')],
        ])
        ->sortKeys()
        ->all();
    $stockedPairs = Stock::with(['warehouse', 'product'])->where('quantity', '>', 0)->get()
        ->map(fn (Stock $s): string => "{$s->warehouse?->code}/{$s->product?->code}")
        ->unique()->values()->all();

    // Alertados: FC/MED-006 (8 < 20), BH/MED-004 (60 no vencidas < 64), BH/MED-006 (sin existencias).
    expect($alerts)->toBe([
        'BH/MED-004' => [64, 60],
        'BH/MED-006' => [5, 0],
        'FC/MED-006' => [20, 8],
    ])
        // Con mínimo y sin alerta.
        ->and(seededMinimums())->toHaveKey('FC/MED-001', 50)
        ->and($alerts)->not->toHaveKey('FC/MED-001')
        // Con existencias y sin mínimo.
        ->and($stockedPairs)->toContain('FU/MED-001')
        ->and(seededMinimums())->not->toHaveKey('FU/MED-001');
});

test('Siembra repetida', function () {
    $this->seed();
    $first = seededMinimums();

    $this->seed();

    expect(seededMinimums())->toBe($first)
        ->and($first)->toBe(['BH/MED-004' => 64, 'BH/MED-006' => 5, 'FC/MED-001' => 50, 'FC/MED-006' => 20]);
});

test('Mínimo cambiado a mano sobrevive a la resiembra', function () {
    $this->seed();
    $minimum = StockMinimum::query()
        ->whereHas('warehouse', fn ($q) => $q->where('code', 'FC'))
        ->whereHas('product', fn ($q) => $q->where('code', 'MED-006'))
        ->sole();
    DB::table('stock_minimums')->where('id', $minimum->id)->update(['minimum_quantity' => 7]);

    $this->seed();

    expect($minimum->fresh()?->minimum_quantity)->toBe(7)
        ->and(StockMinimum::count())->toBe(4);
});
