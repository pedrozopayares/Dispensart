<?php

namespace App\Http\Controllers\Prescriptions;

use App\Actions\Prescriptions\CreatePrescription;
use App\Http\Requests\Prescriptions\StorePrescriptionRequest;
use App\Http\Resources\PrescriptionResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final class PrescriptionController
{
    /**
     * Crea una prescripción a nombre del médico autenticado (solo prescriptions.create).
     */
    public function __invoke(StorePrescriptionRequest $request, CreatePrescription $create): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return (new PrescriptionResource($create->handle($user, $request->prescription())))
            ->response()
            ->setStatusCode(201);
    }
}
