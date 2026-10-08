<?php

namespace App\Http\Requests\Patients;

use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Búsqueda de pacientes: patients.view (Policy antes de validar) y `q` de 3 a 50 caracteres. Los mensajes
 * nunca repiten el valor enviado (RN-10).
 */
final class SearchPatientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('viewAny', Patient::class);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:3', 'max:50'],
        ];
    }

    public function term(): string
    {
        return (string) $this->validated('q');
    }
}
