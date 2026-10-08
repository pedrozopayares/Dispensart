<?php

use App\Enums\PrescriptionStatus;
use App\Enums\Role;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\User;
use App\Support\BusinessCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Fábricas de S3 (tarea 1.6): filas válidas para la base, en cada estado de prescripción.

it('Factory de paciente crea un paciente sintético del rango 99990', function () {
    $patient = Patient::factory()->create();

    expect($patient->document_number)->toStartWith('99990')
        ->and($patient->full_name)->toContain('Sintético');
});

it('Factory de prescripción crea los estados vigente, vencida y agotada con médico prescriptor', function () {
    $today = BusinessCalendar::today();

    $active = Prescription::factory()->withItem(10, 4)->create()->load('items', 'prescriber');
    $expired = Prescription::factory()->expired()->withItem(10, 4)->create()->load('items');
    $exhausted = Prescription::factory()->exhausted()->create()->load('items');

    expect($active->statusOn($today))->toBe(PrescriptionStatus::Active)
        ->and($active->prescriber->role)->toBe(Role::Medico)
        ->and($active->items->sole()->pendingQuantity())->toBe(6)
        ->and($expired->statusOn($today))->toBe(PrescriptionStatus::Expired)
        ->and($exhausted->statusOn($today))->toBe(PrescriptionStatus::Exhausted);
});

it('Factory de usuario y producto dan el segundo regente y el producto controlado', function () {
    $regentes = User::factory()->regente()->count(2)->create();

    expect($regentes->pluck('role')->unique()->all())->toBe([Role::RegenteFarmacia])
        ->and($regentes->pluck('id')->unique())->toHaveCount(2)
        ->and(Product::factory()->controlled()->create()->is_controlled)->toBeTrue();
});
