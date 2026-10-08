<?php

use App\Enums\MovementType;
use App\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Cimiento (tarea 1.4): la fábrica de existencias respeta las restricciones reales y la cadena de saldos.

it('Factory de existencia crea una fila coherente con su lote y su entrada', function () {
    $stock = stockOf(7);

    $movement = movementsOf($stock)->sole();
    expect($stock->product_id)->toBe($stock->lot?->product_id)
        ->and($movement->type)->toBe(MovementType::Inbound)
        ->and($movement->quantity)->toBe(7)
        ->and($movement->balance_after)->toBe(7)
        ->and($movement->user_id)->toBeNull();
});

it('Factory de existencia en cero no escribe movimiento', function () {
    $stock = Stock::factory()->create(['quantity' => 0]);

    expect(movementsOf($stock))->toHaveCount(0);
});
