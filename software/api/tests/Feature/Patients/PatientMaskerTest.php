<?php

use App\Enums\DocumentType;
use App\Models\Patient;
use App\Services\Patients\PatientMasker;

// patients "Auditor ve la ficha enmascarada" a nivel unitario: PatientMasker es puro.

it('enmascara documento, nombre, teléfono y fecha de nacimiento con las mismas claves', function () {
    $patient = (new Patient)->forceFill([
        'id' => 4242, 'document_type' => DocumentType::CedulaCiudadania, 'document_number' => '9999010001',
        'full_name' => 'Ana Sintética Pérez', 'birth_date' => '1985-03-12', 'phone' => '3000000012',
    ]);

    expect((new PatientMasker)->mask($patient))->toBe([
        'id' => 4242,
        'document_type' => 'CC',
        'document_number' => '*******001',
        'full_name' => 'A*** S*** P***',
        'birth_date' => null,
        'phone' => '********12',
        'masked' => true,
    ]);
});

it('enmascara iniciales multibyte y deja nulo un teléfono ausente', function () {
    $patient = (new Patient)->forceFill([
        'id' => 1, 'document_type' => DocumentType::TarjetaIdentidad, 'document_number' => '99',
        'full_name' => '  Ñoño   Éter ', 'birth_date' => null, 'phone' => null,
    ]);

    $masked = (new PatientMasker)->mask($patient);

    expect($masked['full_name'])->toBe('Ñ*** É***')
        ->and($masked['document_number'])->toBe('99')
        ->and($masked['phone'])->toBeNull();
});
