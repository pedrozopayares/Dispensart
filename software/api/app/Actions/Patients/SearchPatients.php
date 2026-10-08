<?php

namespace App\Actions\Patients;

use App\Enums\PatientAccessAction;
use App\Models\Patient;
use App\Models\User;
use App\Services\Audit\PatientAccessRecorder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Búsqueda de pacientes (patients "Búsqueda de pacientes"): documento que empieza por el término o nombre que
 * lo contiene sin distinguir mayúsculas, comodines escapados, por nombre e id, hasta 20. Registra el acceso a
 * cada paciente devuelto antes de serializar (design D8). El término nunca se registra.
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
        $escaped = addcslashes($term, '\\%_');

        $patients = Patient::query()
            ->where(fn ($query) => $query
                ->whereRaw("document_number LIKE ? || '%'", [$escaped])
                ->orWhereRaw("full_name ILIKE '%' || ? || '%'", [$escaped]))
            ->orderBy('full_name')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get();

        $this->recorder->record($user, $patients->modelKeys(), PatientAccessAction::Search, $routePattern);

        return $patients;
    }
}
