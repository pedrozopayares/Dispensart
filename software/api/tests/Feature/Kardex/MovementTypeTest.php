<?php

use App\Enums\MovementType;
use App\Services\Inventory\StockChange;

// kardex "Tipos futuros ya admitidos" y "Signo contrario al tipo" a nivel de dominio.

it('declara exactamente los 5 tipos de RN-06', function () {
    expect(array_map(fn (MovementType $type) => $type->value, MovementType::cases()))
        ->toBe(['entrada', 'salida_dispensacion', 'salida_traslado', 'entrada_traslado', 'ajuste']);
});

it('admite o rechaza la cantidad según el signo del tipo', function (MovementType $type, int $quantity, bool $allowed) {
    expect($type->allowsQuantity($quantity))->toBe($allowed);
})->with([
    'entrada +1' => [MovementType::Inbound, 1, true],
    'entrada -1' => [MovementType::Inbound, -1, false],
    'entrada_traslado +2' => [MovementType::TransferInbound, 2, true],
    'entrada_traslado -2' => [MovementType::TransferInbound, -2, false],
    'salida_dispensacion -1' => [MovementType::DispensingOutbound, -1, true],
    'salida_dispensacion +1' => [MovementType::DispensingOutbound, 1, false],
    'salida_traslado -2' => [MovementType::TransferOutbound, -2, true],
    'salida_traslado +2' => [MovementType::TransferOutbound, 2, false],
    'ajuste +3' => [MovementType::Adjustment, 3, true],
    'ajuste -3' => [MovementType::Adjustment, -3, true],
    'ajuste 0' => [MovementType::Adjustment, 0, false],
    'entrada 0' => [MovementType::Inbound, 0, false],
]);

it('no construye un cambio de existencia con signo contrario al tipo', function () {
    expect(fn () => new StockChange(1, 1, -1, MovementType::Inbound))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new StockChange(1, 1, 0, MovementType::Adjustment))->toThrow(InvalidArgumentException::class);
});
