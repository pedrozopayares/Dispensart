<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Patrón de la ruta resuelta de una petición (`/api/patients/{patient}`), nunca la ruta literal con
 * identificadores (patients "Rutas de pacientes registradas por patrón", RN-10, design D9). Sin ruta resuelta,
 * o con la ruta de respaldo: `unmatched`.
 */
final class RoutePattern
{
    public const UNMATCHED = 'unmatched';

    public static function of(Request $request): string
    {
        $route = $request->route();

        if (! $route instanceof \Illuminate\Routing\Route || $route->isFallback) {
            return self::UNMATCHED;
        }

        return '/'.ltrim($route->uri(), '/');
    }
}
