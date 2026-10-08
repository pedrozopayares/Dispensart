<?php

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// patients "Identidad única del paciente": sentencias directas, sin pasar por la API.

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function patientRow(array $overrides = []): array
{
    return ['document_type' => 'CC', 'document_number' => '9999010001', 'full_name' => 'Ana Sintética Pérez', ...$overrides];
}

it('rechaza en la base un documento duplicado del mismo tipo y no deja fila', function () {
    DB::table('patients')->insert(patientRow());

    expectRejectedByDatabase(
        fn () => DB::table('patients')->insert(patientRow(['full_name' => 'Otra Sintética'])),
        '23505',
        'patients_document_unique',
    );
    expect(Patient::count())->toBe(1);
});

it('acepta el mismo número con otro tipo de documento', function () {
    DB::table('patients')->insert(patientRow());
    DB::table('patients')->insert(patientRow(['document_type' => 'TI']));

    expect(Patient::where('document_number', '9999010001')->pluck('document_type')->map->value->sort()->values()->all())
        ->toBe(['CC', 'TI']);
});

it('rechaza en la base un paciente sin nombre, sin tipo, sin número o con nombre en blanco', function (array $row, string $sqlState) {
    expectRejectedByDatabase(fn () => DB::table('patients')->insert($row), $sqlState);

    expect(Patient::count())->toBe(0);
})->with([
    'sin full_name' => [array_diff_key(patientRow(), ['full_name' => true]), '23502'],
    'sin document_type' => [array_diff_key(patientRow(), ['document_type' => true]), '23502'],
    'sin document_number' => [array_diff_key(patientRow(), ['document_number' => true]), '23502'],
    'nombre de solo espacios' => [patientRow(['full_name' => '   ']), '23514'],
    'tipo fuera del conjunto' => [patientRow(['document_type' => 'XX']), '23514'],
]);
