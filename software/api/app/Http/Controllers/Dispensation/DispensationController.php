<?php

namespace App\Http\Controllers\Dispensation;

use App\Actions\Dispensation\DispenseMedication;
use App\Http\Requests\Dispensation\StoreDispensationRequest;
use App\Models\User;
use Illuminate\Http\Response;

final class DispensationController
{
    /**
     * Dispensa por FEFO con Idempotency-Key: 201 con la dispensación, o la respuesta original con
     * Idempotent-Replayed: true al repetir clave y cuerpo.
     *
     * El cuerpo es el texto guardado por el almacén de idempotencia; su forma, la de DispensationResource.
     *
     * @response \App\Http\Resources\DispensationResource
     */
    public function __invoke(StoreDispensationRequest $request, DispenseMedication $dispense): Response
    {
        /** @var User $user */
        $user = $request->user();

        return $dispense->handle(
            $user,
            $request->dispensation(),
            $request->authorizerEmail(),
            $request->authorizerPassword(),
            $request->idempotencyKey(),
            $request->fingerprint(),
        )->toResponse();
    }
}
