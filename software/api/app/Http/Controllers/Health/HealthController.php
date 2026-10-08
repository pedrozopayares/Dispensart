<?php

namespace App\Http\Controllers\Health;

use Illuminate\Http\JsonResponse;

/**
 * Vivacidad: responde mientras el proceso atiende peticiones. No toca base, caché ni sesión.
 */
final class HealthController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
