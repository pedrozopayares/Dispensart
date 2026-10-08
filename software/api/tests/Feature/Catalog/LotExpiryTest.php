<?php

use App\Models\Lot;
use App\Support\BusinessCalendar;
use Carbon\CarbonImmutable;

// catalog "Estado de vencimiento del lote": vencido si expires_on <= hoy en America/Bogota (design D6).

function lotExpiringOn(string $date): Lot
{
    return new Lot(['expires_on' => $date]);
}

it('calcula hoy en America/Bogota aunque app.timezone sea UTC', function () {
    // 23:30 en Bogotá del 14 de marzo = 04:30 UTC del 15 de marzo.
    $this->travelTo(CarbonImmutable::parse('2027-03-15 04:30:00', 'UTC'));

    expect(config('app.timezone'))->toBe('UTC')
        ->and(BusinessCalendar::today()->toDateString())->toBe('2027-03-14');
});

it('marca vencido un lote que venció ayer', function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-14 12:00:00', 'America/Bogota'));

    expect(lotExpiringOn('2027-03-13')->isExpiredOn(BusinessCalendar::today()))->toBeTrue();
});

it('marca vencido un lote que vence hoy', function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-14 12:00:00', 'America/Bogota'));

    expect(lotExpiringOn('2027-03-14')->isExpiredOn(BusinessCalendar::today()))->toBeTrue();
});

it('no marca vencido un lote que vence mañana', function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-14 12:00:00', 'America/Bogota'));

    expect(lotExpiringOn('2027-03-15')->isExpiredOn(BusinessCalendar::today()))->toBeFalse();
});

it('en la frontera de 23:30 Bogotá usa el día de Bogotá, no el de UTC', function () {
    // En UTC ya es 15 de marzo; en Bogotá sigue siendo 14: el lote del 15 aún no vence.
    $this->travelTo(CarbonImmutable::parse('2027-03-15 04:30:00', 'UTC'));

    expect(lotExpiringOn('2027-03-15')->isExpiredOn(BusinessCalendar::today()))->toBeFalse()
        ->and(lotExpiringOn('2027-03-14')->isExpiredOn(BusinessCalendar::today()))->toBeTrue();
});

it('vence al cambiar de día sin escritura alguna', function () {
    $lot = lotExpiringOn('2027-03-15');
    $this->travelTo(CarbonImmutable::parse('2027-03-14 23:59:00', 'America/Bogota'));
    expect($lot->isExpiredOn(BusinessCalendar::today()))->toBeFalse();

    $this->travelTo(CarbonImmutable::parse('2027-03-15 00:00:01', 'America/Bogota'));
    expect($lot->isExpiredOn(BusinessCalendar::today()))->toBeTrue();
});
