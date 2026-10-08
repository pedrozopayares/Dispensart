<?php

namespace App\Http\Requests\Patients;

use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ficha del paciente: la Policy corre aquí, antes de buscar el modelo (sin enlace implícito en la ruta), así
 * un rol sin acceso recibe 403 y no puede sondear qué ids existen (design D4).
 */
final class ShowPatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('view', Patient::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }
}
