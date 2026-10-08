<?php

use App\Actions\Inventory\AdjustStock;
use App\Enums\MovementType;
use App\Exceptions\LotExpired;
use App\Models\Lot;
use App\Models\Stock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// inventory "Ajuste sobre lote vencido" e "Ajuste de inventario" a nivel de acción, con reloj fijado en
// Bogotá (2027-03-14 23:30: en UTC ya es el 15, el día de negocio sigue siendo el 14).

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-14 23:30:00', 'America/Bogota'));
    $this->regente = User::factory()->regente()->create();
});

function stockOnLotExpiring(string $expiresOn, int $quantity): Stock
{
    return stockOf($quantity, ['lot_id' => Lot::factory()->create(['expires_on' => $expiresOn])->id]);
}

it('rechaza un ingreso a un lote que vence hoy en Bogotá sin cambiar nada', function () {
    $stock = stockOnLotExpiring('2027-03-14', 5);

    expect(fn () => app(AdjustStock::class)->handle($this->regente, adjustmentBody($stock, 2)))
        ->toThrow(LotExpired::class);

    expect($stock->fresh()?->quantity)->toBe(5)
        ->and(movementsOf($stock))->toHaveCount(1);
});

it('permite dar de baja la existencia de un lote vencido', function () {
    $stock = stockOnLotExpiring('2027-03-13', 5);

    $movement = app(AdjustStock::class)->handle($this->regente, adjustmentBody($stock, -5, 'Baja por vencimiento'));

    expect($movement->balance_after)->toBe(0)
        ->and($stock->fresh()?->quantity)->toBe(0);
});

it('permite un ingreso a un lote que vence mañana', function () {
    $stock = stockOnLotExpiring('2027-03-15', 5);

    $movement = app(AdjustStock::class)->handle($this->regente, adjustmentBody($stock, 2));

    expect($movement->balance_after)->toBe(7);
});

it('fija tipo ajuste, el usuario autenticado, el motivo y el saldo calculado', function () {
    $stock = stockOnLotExpiring('2027-12-31', 5);

    $movement = app(AdjustStock::class)->handle($this->regente, adjustmentBody($stock, -1, 'Rotura'));

    expect($movement->type)->toBe(MovementType::Adjustment)
        ->and($movement->user_id)->toBe($this->regente->id)
        ->and($movement->reason)->toBe('Rotura')
        ->and($movement->balance_after)->toBe(4)
        ->and($movement->relationLoaded('user'))->toBeTrue();
});

it('aplica dos veces el mismo ajuste repetido: no es idempotente', function () {
    $stock = stockOnLotExpiring('2027-12-31', 5);
    $body = adjustmentBody($stock, -1);

    app(AdjustStock::class)->handle($this->regente, $body);
    app(AdjustStock::class)->handle($this->regente, $body);

    expect($stock->fresh()?->quantity)->toBe(3)
        ->and(movementsOf($stock)->where('type', MovementType::Adjustment))->toHaveCount(2);
});
