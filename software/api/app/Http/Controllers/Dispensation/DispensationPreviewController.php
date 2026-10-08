<?php

namespace App\Http\Controllers\Dispensation;

use App\Actions\Dispensation\PreviewDispensation;
use App\Http\Requests\Dispensation\PreviewDispensationRequest;
use App\Http\Resources\DispensationPreviewResource;

final class DispensationPreviewController
{
    /**
     * Lotes que FEFO tomaría hoy por ítem, sin bloquear ni escribir; el faltante es dato (fulfillable).
     */
    public function __invoke(PreviewDispensationRequest $request, PreviewDispensation $preview): DispensationPreviewResource
    {
        return new DispensationPreviewResource($preview->handle($request->dispensation()));
    }
}
