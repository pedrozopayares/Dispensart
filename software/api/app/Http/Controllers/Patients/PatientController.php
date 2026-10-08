<?php

namespace App\Http\Controllers\Patients;

use App\Actions\Patients\SearchPatients;
use App\Actions\Patients\ShowPatient;
use App\Http\Requests\Patients\SearchPatientsRequest;
use App\Http\Requests\Patients\ShowPatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\User;
use App\Support\RoutePattern;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PatientController
{
    /**
     * Busca pacientes por prefijo de documento o parte del nombre (hasta 20). Enmascarados para el auditor.
     */
    public function index(SearchPatientsRequest $request, SearchPatients $search): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return PatientResource::collection($search->handle($user, $request->term(), RoutePattern::of($request)));
    }

    /**
     * Ficha del paciente con sus prescripciones y saldos. Enmascarada para el auditor.
     */
    public function show(ShowPatientRequest $request, int $patient, ShowPatient $show): PatientResource
    {
        /** @var User $user */
        $user = $request->user();

        return new PatientResource($show->handle($user, $patient, RoutePattern::of($request)));
    }
}
