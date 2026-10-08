<?php

namespace App\Http\Resources;

use App\Models\Patient;
use App\Services\Patients\PatientMasker;
use App\Support\BusinessCalendar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Paciente para la API (RN-10). Datos en claro solo si la Policy lo permite (viewIdentifiable, lista de
 * permitidos); si no, el arreglo sale de PatientMasker y el modelo crudo nunca se serializa (design D7).
 * Las prescripciones (en la ficha) se ven completas para todo rol con lectura.
 *
 * @mixin Patient
 */
final class PatientResource extends JsonResource
{
    /**
     * @return array{id: int, document_type: string, document_number: string, full_name: string, birth_date: string|null, phone: string|null, masked: bool, prescriptions?: list<array{id: int, patient_id: int, status: string, valid_until: string, created_at: string, prescriber: array{id: int, name: string}, items: list<array{id: int, product: array{id: int, code: string, name: string, is_controlled: bool}, prescribed_quantity: int, dispensed_quantity: int, pending_quantity: int}>}>}
     */
    public function toArray(Request $request): array
    {
        /** @var Patient $patient */
        $patient = $this->resource;

        $fields = $request->user()?->can('viewIdentifiable', Patient::class)
            ? [
                'id' => $patient->id,
                'document_type' => $patient->document_type->value,
                'document_number' => $patient->document_number,
                'full_name' => $patient->full_name,
                'birth_date' => $patient->birth_date?->toDateString(),
                'phone' => $patient->phone,
                'masked' => false,
            ]
            : app(PatientMasker::class)->mask($patient);

        if ($patient->relationLoaded('prescriptions')) {
            $today = BusinessCalendar::today();
            $fields['prescriptions'] = $patient->prescriptions
                ->map(fn ($prescription) => (new PrescriptionResource($prescription))->onDate($today)->resolve($request))
                ->all();
        }

        return $fields;
    }
}
