<?php

use App\Enums\MovementType;
use App\Models\KardexMovement;
use App\Models\Lot;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\BusinessCalendar;
use Database\Seeders\LotSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\WarehouseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// inventory "Existencias semilla": distribución exigida, una entrada por existencia, idempotencia por
// bodega + lote.

it('siembra existencias en las 3 bodegas con lote vencido, controlado vigente y 2 lotes vigentes en una bodega', function () {
    $this->seed();
    $today = BusinessCalendar::today();
    $stocks = Stock::with(['lot', 'product'])->where('quantity', '>', 0)->get();

    expect($stocks->pluck('warehouse_id')->unique()->sort()->values()->all())
        ->toBe(Warehouse::orderBy('id')->pluck('id')->all())
        ->and($stocks->filter(fn (Stock $s) => $s->lot?->isExpiredOn($today)))->not->toBeEmpty()
        ->and($stocks->filter(fn (Stock $s) => $s->product?->is_controlled && ! $s->lot?->isExpiredOn($today)))->not->toBeEmpty();

    $twoLiveLots = $stocks->reject(fn (Stock $s) => $s->lot?->isExpiredOn($today))
        ->groupBy(fn (Stock $s) => $s->warehouse_id.':'.$s->product_id)
        ->filter(fn ($group) => $group->count() >= 2);
    expect($twoLiveLots)->not->toBeEmpty();
});

it('escribe un único movimiento entrada por existencia sembrada, de igual cantidad y sin usuario', function () {
    $this->seed();

    $stocks = Stock::all();
    expect($stocks)->not->toBeEmpty()
        ->and(KardexMovement::count())->toBe($stocks->count());
    foreach ($stocks as $stock) {
        $movement = movementsOf($stock)->sole();
        expect($movement->type)->toBe(MovementType::Inbound)
            ->and($movement->quantity)->toBe($stock->quantity)
            ->and($movement->balance_after)->toBe($stock->quantity)
            ->and($movement->user_id)->toBeNull();
    }
});

it('repite la siembra sin crear existencias ni movimientos ni cambiar cantidades', function () {
    $this->seed();
    $quantities = Stock::orderBy('id')->pluck('quantity', 'id')->all();
    $movements = KardexMovement::count();

    $this->seed();

    expect(Stock::orderBy('id')->pluck('quantity', 'id')->all())->toBe($quantities)
        ->and(KardexMovement::count())->toBe($movements);
});

it('conserva un ajuste del regente tras resembrar, sin segunda entrada', function () {
    $this->seed();
    $stock = Stock::query()->whereHas('lot', fn ($q) => $q->where('lot_code', 'L-ACE-2403'))->firstOrFail();
    $regente = User::where('email', 'regente@dispensart.test')->firstOrFail();

    $this->actingAs($regente)->postJson('/api/stock-adjustments', adjustmentBody($stock, -2))->assertCreated();
    $this->seed();

    expect($stock->fresh()?->quantity)->toBe($stock->quantity - 2)
        ->and(movementsOf($stock)->where('type', MovementType::Inbound))->toHaveCount(1);
});

it('no toma una existencia ya creada por un ajuste antes de la siembra', function () {
    $this->seed([WarehouseSeeder::class, ProductSeeder::class, LotSeeder::class, UserSeeder::class]);
    $warehouse = Warehouse::where('code', 'FC')->firstOrFail();
    $lot = Lot::where('lot_code', 'L-ACE-2402')->firstOrFail();
    $regente = User::where('email', 'regente@dispensart.test')->firstOrFail();
    $this->actingAs($regente)->postJson('/api/stock-adjustments', [
        'warehouse_id' => $warehouse->id, 'lot_id' => $lot->id, 'quantity' => 4, 'reason' => 'Ingreso previo',
    ])->assertCreated();

    $this->seed();

    $stock = Stock::where('warehouse_id', $warehouse->id)->where('lot_id', $lot->id)->sole();
    expect($stock->quantity)->toBe(4)
        ->and(movementsOf($stock)->where('type', MovementType::Inbound))->toHaveCount(0)
        ->and(movementsOf($stock))->toHaveCount(1);
});
