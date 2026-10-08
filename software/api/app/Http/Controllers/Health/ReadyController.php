<?php

namespace App\Http\Controllers\Health;

use App\Health\ReadinessChecker;
use Illuminate\Http\JsonResponse;

/**
 * Disponibilidad: traduce el resultado del checker a 200 (lista) o 503 (no lista).
 */
final class ReadyController
{
    public function __invoke(ReadinessChecker $checker): JsonResponse
    {
        $result = $checker->check();

        return new JsonResponse($result->toArray(), $result->isReady() ? 200 : 503);
    }
}
