<?php

namespace App\Services\Patients;

use App\Models\Patient;

/**
 * Enmascarado del paciente para roles sin vista en claro, como el auditor (patients "Enmascarado para el
 * auditor", RN-10, design D7). Puro: mismas claves que la vista en claro y `masked` verdadero.
 */
final class PatientMasker
{
    /**
     * @return array{id: int, document_type: string, document_number: string, full_name: string, birth_date: null, phone: string|null, masked: true}
     */
    public function mask(Patient $patient): array
    {
        return [
            'id' => $patient->id,
            'document_type' => $patient->document_type->value,
            'document_number' => self::keepLast($patient->document_number, 3),
            'full_name' => self::initials($patient->full_name),
            'birth_date' => null,
            'phone' => $patient->phone === null ? null : self::keepLast($patient->phone, 2),
            'masked' => true,
        ];
    }

    /**
     * Todo salvo los últimos `$visible` caracteres cambiado por `*`.
     */
    private static function keepLast(string $value, int $visible): string
    {
        $length = mb_strlen($value);
        $hidden = max(0, $length - $visible);

        return str_repeat('*', $hidden).mb_substr($value, $hidden);
    }

    /**
     * Inicial de cada palabra seguida de `***`: "Ana Sintética Pérez" → "A*** S*** P***".
     */
    private static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_map(fn (string $word): string => mb_substr($word, 0, 1).'***', $words));
    }
}
