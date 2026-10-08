<?php

use App\Enums\PrescriptionStatus;
use App\Models\Product;
use App\Support\BusinessCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// prescriptions "Estado de la prescripción": calculado en cada lectura con la fecha de negocio de Bogotá.

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-14 12:00:00', 'America/Bogota'));
});

function statusOf(string $validUntil, int $prescribed, int $dispensed): PrescriptionStatus
{
    $prescription = prescriptionWith([[Product::factory()->create(), $prescribed, $dispensed]], ['valid_until' => $validUntil]);

    return $prescription->fresh('items')->statusOn(BusinessCalendar::today());
}

it('mantiene vigente una prescripción con pendiente que vence hoy', function () {
    expect(statusOf('2027-03-14', 10, 4))->toBe(PrescriptionStatus::Active);
});

it('devuelve vencida una prescripción con pendiente que venció ayer', function () {
    expect(statusOf('2027-03-13', 10, 4))->toBe(PrescriptionStatus::Expired);
});

it('devuelve agotada una prescripción con todo dispensado', function () {
    expect(statusOf('2027-04-14', 10, 10))->toBe(PrescriptionStatus::Exhausted);
});

it('prefiere agotada sobre vencida', function () {
    expect(statusOf('2027-03-13', 10, 10))->toBe(PrescriptionStatus::Exhausted);
});

it('es agotada solo si todos los ítems tienen pendiente 0', function () {
    $prescription = prescriptionWith([[Product::factory()->create(), 5, 5], [Product::factory()->create(), 3, 2]]);

    expect($prescription->statusOn(BusinessCalendar::today()))->toBe(PrescriptionStatus::Active);
});

it('pasa a vencida al día siguiente sin escritura alguna', function () {
    $prescription = prescriptionWith([[Product::factory()->create(), 10, 0]], ['valid_until' => '2027-03-14']);
    expect($prescription->statusOn(BusinessCalendar::today()))->toBe(PrescriptionStatus::Active);

    $this->travelTo(CarbonImmutable::parse('2027-03-15 00:00:01', 'America/Bogota'));

    expect($prescription->fresh('items')?->statusOn(BusinessCalendar::today()))->toBe(PrescriptionStatus::Expired);
});

it('sigue vigente a las 23:30 de Bogotá del día valid_until (04:30 UTC del día siguiente)', function () {
    $this->travelTo(CarbonImmutable::parse('2027-03-15 04:30:00', 'UTC'));

    expect(statusOf('2027-03-14', 10, 0))->toBe(PrescriptionStatus::Active);
});
