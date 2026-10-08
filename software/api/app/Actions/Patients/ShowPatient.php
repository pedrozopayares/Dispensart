<?php

namespace App\Actions\Patients;

use App\Enums\PatientAccessAction;
use App\Models\Patient;
use App\Models\User;
use App\Services\Audit\PatientAccessRecorder;

/**
 * Ficha del paciente con sus prescripciones, de la más reciente a la más antigua, con ítems y saldos
 * (patients "Ficha del paciente con prescripciones"). La Policy ya corrió en el FormRequest: un rol sin acceso
 * nunca llega aquí, así que un id inexistente no es un oráculo (design D4). Registra el acceso antes de
 * serializar (design D8).
 */
final class ShowPatient
{
    public function __construct(private readonly PatientAccessRecorder $recorder) {}

    public function handle(User $user, int $patientId, string $routePattern): Patient
    {
        $patient = Patient::query()
            ->with(['prescriptions' => fn ($query) => $query
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->with(['prescriber:id,name', 'items.product'])])
            ->findOrFail($patientId);

        $this->recorder->record($user, [$patient->id], PatientAccessAction::View, $routePattern);

        return $patient;
    }
}
