<?php

namespace App\Providers;

use App\OpenApi\ApiErrorDocumentTransformer;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\ServiceProvider;

/**
 * OpenAPI con Scramble (design D9). Scramble es dependencia solo de desarrollo: en la imagen de producción
 * no existe y este proveedor no hace nada.
 */
final class OpenApiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (class_exists(Scramble::class)) {
            // Sin interfaz /docs/api: el contrato se entrega como archivo (software/api/openapi.json).
            Scramble::ignoreDefaultRoutes();
        }
    }

    public function boot(): void
    {
        if (! class_exists(Scramble::class)) {
            return;
        }

        Scramble::configure()->withDocumentTransformers(ApiErrorDocumentTransformer::class);
    }
}
