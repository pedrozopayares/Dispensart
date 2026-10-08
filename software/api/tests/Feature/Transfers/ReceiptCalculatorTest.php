<?php

use App\Enums\TransferStatus;
use App\Models\Lot;
use App\Services\Transfers\ReceiptCalculator;
use App\Support\BusinessCalendar;
use Carbon\CarbonImmutable;

// transfers "Recepción completa", "Recepción parcial", "Nada recibido" a nivel de dominio (design D6) y regla de
// lote vencido con el reloj de negocio fijado («Lote vencido rechazado al crear», «Lote que vence mañana admitido»).

it('calcula RECIBIDO sin faltantes cuando llega todo', function () {
    $outcome = (new ReceiptCalculator)->compute([10 => 3, 11 => 2], [10 => 3, 11 => 2]);

    expect($outcome->status)->toBe(TransferStatus::Received)->and($outcome->shortages)->toBe([]);
});

it('calcula RECIBIDO_PARCIAL con faltante solo en la línea incompleta', function () {
    $outcome = (new ReceiptCalculator)->compute([10 => 3, 11 => 2], [10 => 2, 11 => 2]);

    expect($outcome->status)->toBe(TransferStatus::PartiallyReceived)->and($outcome->shortages)->toBe([10 => 1]);
});

it('calcula RECIBIDO_PARCIAL con todo faltante cuando no llega nada', function () {
    $outcome = (new ReceiptCalculator)->compute([10 => 3, 11 => 2], [10 => 0, 11 => 0]);

    expect($outcome->status)->toBe(TransferStatus::PartiallyReceived)->and($outcome->shortages)->toBe([10 => 3, 11 => 2]);
});

it('trata la sobre-recepción, lo negativo y las líneas ajenas como defecto, nunca como 422', function (array $received) {
    expect(fn () => (new ReceiptCalculator)->compute([10 => 3, 11 => 2], $received))->toThrow(LogicException::class);
})->with([
    'sobre-recepción' => [[10 => 4, 11 => 2]],
    'negativo' => [[10 => -1, 11 => 2]],
    'línea omitida' => [[10 => 3]],
    'línea ajena' => [[10 => 3, 11 => 2, 99 => 1]],
]);

it('da por vencido el lote que vence hoy en Bogotá y admite el que vence mañana', function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-14 23:30:00', 'America/Bogota'));
    $today = BusinessCalendar::today();

    expect((new Lot)->forceFill(['expires_on' => '2027-03-14'])->isExpiredOn($today))->toBeTrue()
        ->and((new Lot)->forceFill(['expires_on' => '2027-03-15'])->isExpiredOn($today))->toBeFalse();
});
