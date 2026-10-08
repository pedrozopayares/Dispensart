<?php

use App\Models\Lot;
use App\Models\Stock;
use App\Services\Dispensation\FefoAllocator;
use Carbon\CarbonImmutable;

// dispensation "Asignación FEFO en la dispensación" a nivel unitario: FefoAllocator es puro (sin base).

const FEFO_TODAY = '2027-03-14';

/**
 * Existencia en memoria con su lote: [id de lote, vence (Y-m-d), cantidad].
 */
function memoryStock(int $lotId, string $expiresOn, int $quantity): Stock
{
    $lot = (new Lot)->forceFill(['id' => $lotId, 'lot_code' => 'L'.$lotId, 'expires_on' => $expiresOn, 'product_id' => 1]);

    return (new Stock)->forceFill(['id' => $lotId * 10, 'lot_id' => $lotId, 'product_id' => 1, 'quantity' => $quantity])
        ->setRelation('lot', $lot);
}

/**
 * @param  list<Stock>  $stocks
 * @return array{lines: list<array{int, int}>, available: int, shortage: int, expired: int}
 */
function allocate(array $stocks, int $requested): array
{
    $allocation = (new FefoAllocator)->allocate($stocks, $requested, CarbonImmutable::parse(FEFO_TODAY));

    return [
        'lines' => array_map(fn ($line) => [$line->lotId, $line->quantity], $allocation->lines),
        'available' => $allocation->available,
        'shortage' => $allocation->shortage,
        'expired' => $allocation->expiredExcluded,
    ];
}

it('consume en orden FEFO entre varios lotes aunque lleguen desordenados', function () {
    $result = allocate([
        memoryStock(3, '2027-06-12', 10), // L3, 90 días
        memoryStock(1, '2027-03-24', 3),  // L1, 10 días
        memoryStock(2, '2027-04-23', 10), // L2, 40 días
    ], 5);

    expect($result['lines'])->toBe([[1, 3], [2, 2]])
        ->and($result['shortage'])->toBe(0)
        ->and($result['available'])->toBe(23);
});

it('desempata por id de lote a igual vencimiento', function () {
    $result = allocate([memoryStock(8, '2027-05-01', 3), memoryStock(5, '2027-05-01', 3)], 4);

    expect($result['lines'])->toBe([[5, 3], [8, 1]]);
});

it('nunca selecciona un lote vencido y lo cuenta como excluido', function () {
    $result = allocate([memoryStock(1, '2027-03-13', 50), memoryStock(2, '2027-03-24', 2)], 2);

    expect($result['lines'])->toBe([[2, 2]])
        ->and($result['expired'])->toBe(50)
        ->and($result['available'])->toBe(2);
});

it('excluye el lote que vence hoy', function () {
    $result = allocate([memoryStock(1, FEFO_TODAY, 5)], 1);

    expect($result['lines'])->toBe([])
        ->and($result['shortage'])->toBe(1)
        ->and($result['expired'])->toBe(5);
});

it('informa el faltante sin error y omite existencias en 0', function () {
    $result = allocate([memoryStock(1, '2027-03-24', 3), memoryStock(2, '2027-04-23', 0), memoryStock(3, '2027-04-30', 10)], 20);

    expect($result['lines'])->toBe([[1, 3], [3, 10]])
        ->and($result['available'])->toBe(13)
        ->and($result['shortage'])->toBe(7);
});
