<?php

namespace App\Actions\Patients;

use App\Enums\PatientAccessAction;
use App\Models\Patient;
use App\Models\User;
use App\Services\Audit\PatientAccessRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Búsqueda de pacientes (patients "Búsqueda de pacientes"): quien ve datos en claro busca por prefijo de
 * documento o fragmento de nombre sin distinguir mayúsculas (comodines escapados); quien los ve enmascarados
 * (auditor) solo por documento completo exacto, así la búsqueda no sirve de oráculo para reconstruir datos que
 * el enmascarado oculta (RN-10). Por nombre e id, hasta 20. Registra el acceso a cada paciente devuelto antes de
 * serializar (design D8). El término nunca se registra.
 */
final class SearchPatients
{
    public const LIMIT = 20;

    public function __construct(private readonly PatientAccessRecorder $recorder) {}

    /**
     * @return Collection<int, Patient>
     */
    public function handle(User $user, string $term, string $routePattern): Collection
    {
        $patients = Patient::query()
            ->where(fn ($query) => $user->can('viewIdentifiable', Patient::class)
                ? $this->partialMatch($query, $term)
                : $query->where('document_number', $term))
            ->orderBy('full_name')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get();

        $this->recorder->record($user, $patients->modelKeys(), PatientAccessAction::Search, $routePattern);

        return $patients;
    }

    /**
     * Prefijo de documento o fragmento de nombre, con comodines escapados: solo para quien ve datos en claro.
     *
     * @param  Builder<Patient>  $query
     * @return Builder<Patient>
     */
    private function partialMatch(Builder $query, string $term): Builder
    {
        $escaped = addcslashes($term, '\\%_');

        return $query
            ->whereRaw("document_number LIKE ? || '%'", [$escaped])
            ->orWhereRaw("full_name ILIKE '%' || ? || '%'", [$escaped]);
    }
}
