<?php

namespace App\OpenApi;

use App\Health\ReadinessResult;
use App\Http\Middleware\AssignCorrelationId;
use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Path;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\Server;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;

/**
 * Documenta GET /health y GET /ready (service-health «Contrato OpenAPI de salud y disponibilidad»; solo
 * desarrollo). Scramble solo infiere rutas bajo `api_path` = `api`, y estas viven en la raíz del origen: se agregan
 * aquí, con un servidor propio `/` por ruta para que no resuelvan bajo `/api`. Los valores salen del código
 * (ReadinessResult, AssignCorrelationId), no de texto copiado. Se registra después de ApiErrorDocumentTransformer:
 * estas operaciones no llevan sesión, CSRF ni rechazos 4xx.
 */
final class HealthDocumentTransformer implements DocumentTransformer
{
    private const TAG = 'Health';

    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        $root = [Server::make('/')->setDescription('Raíz del mismo origen, fuera de /api.')];

        $health = Operation::make('get')
            ->setOperationId('health')
            ->summary('Vivacidad: el proceso atiende peticiones. No consulta base, caché ni sesión')
            ->setTags([self::TAG])
            ->addResponse($this->response(200, 'El proceso está vivo.', (new ObjectType)
                ->addProperty('status', (new StringType)->enum(['ok']))
                ->setRequired(['status'])));
        $health->addSecurity(new SecurityRequirement([]));

        $ready = Operation::make('get')
            ->setOperationId('ready')
            ->summary('Disponibilidad: la base responde y no quedan migraciones pendientes')
            ->setTags([self::TAG])
            ->addResponse($this->response(200, 'Lista para recibir tráfico.', $this->readinessType(true)))
            ->addResponse($this->response(503, 'No lista: la base no responde o hay migraciones pendientes o sin verificar.', $this->readinessType(false)));
        $ready->addSecurity(new SecurityRequirement([]));

        $document->addPath(Path::make('health')->servers($root)->addOperation($health));
        $document->addPath(Path::make('ready')->servers($root)->addOperation($ready));
    }

    private function response(int $status, string $description, ObjectType $body): Response
    {
        return Response::make($status)
            ->setDescription($description)
            ->setContent('application/json', Schema::fromType($body))
            ->addHeader(AssignCorrelationId::HEADER, new Header(
                description: 'Identificador de correlación de la petición: el recibido si es válido, o uno nuevo.',
                required: true,
                schema: Schema::fromType(new StringType),
            ));
    }

    /**
     * Cuerpo de ReadinessResult::toArray(): solo estados, sin mensajes ni datos de conexión (RN-10).
     */
    private function readinessType(bool $ready): ObjectType
    {
        $checks = (new ObjectType)
            ->addProperty('database', (new StringType)->enum([ReadinessResult::OK, ReadinessResult::FAIL]))
            ->addProperty('migrations', (new StringType)->enum([
                ReadinessResult::OK, ReadinessResult::PENDING, ReadinessResult::SKIPPED, ReadinessResult::FAIL,
            ]))
            ->setRequired(['database', 'migrations']);

        return (new ObjectType)
            ->addProperty('status', (new StringType)->enum([$ready ? 'ready' : 'not_ready']))
            ->addProperty('checks', $checks)
            ->setRequired(['status', 'checks']);
    }
}
