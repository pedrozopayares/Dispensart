<?php

namespace Database\Seeders;

use App\Enums\DocumentType;
use App\Models\Patient;
use Illuminate\Database\Seeder;

/**
 * Pacientes 100 % sintéticos (patients "Pacientes semilla sintéticos", RN-10). Documentos del rango ficticio
 * 99990… (reservado por este proyecto; no corresponde a personas reales) y nombres con la marca "Sintético/a".
 * Idempotente por tipo + número de documento: si existe, no se toca.
 */
class PatientSeeder extends Seeder
{
    /**
     * @var list<array{document_type: DocumentType, document_number: string, full_name: string, birth_date: string, phone: string}>
     */
    public const PATIENTS = [
        ['document_type' => DocumentType::CedulaCiudadania, 'document_number' => '9999010001', 'full_name' => 'Ana Sintética Pérez', 'birth_date' => '1985-03-12', 'phone' => '3000000012'],
        ['document_type' => DocumentType::CedulaCiudadania, 'document_number' => '9999010002', 'full_name' => 'Carlos Sintético Gómez', 'birth_date' => '1972-11-02', 'phone' => '3000000034'],
        ['document_type' => DocumentType::TarjetaIdentidad, 'document_number' => '9999010003', 'full_name' => 'Lucía Sintética Ramírez', 'birth_date' => '2012-07-21', 'phone' => '3000000056'],
    ];

    public function run(): void
    {
        foreach (self::PATIENTS as $patient) {
            Patient::query()->firstOrCreate(
                ['document_type' => $patient['document_type'], 'document_number' => $patient['document_number']],
                ['full_name' => $patient['full_name'], 'birth_date' => $patient['birth_date'], 'phone' => $patient['phone']],
            );
        }
    }
}
