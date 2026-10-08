<?php

namespace App\OpenApi;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Server;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;

/**
 * Ajusta el OpenAPI inferido por Scramble al contrato real (design D5, D9; solo desarrollo):
 * - todo rechazo con la forma {code, message[, errors]} y su `code` estable;
 * - 419 en toda escritura (CSRF), 403/422/429 propios del login;
 * - rechazos de dominio de inventario (S2), de pacientes, prescripciones y dispensación (S3), con la cabecera
 *   Idempotency-Key de la dispensación y la cabecera Idempotent-Replayed de su repetición, y de traslados (S4);
 * - 429 del limitador y 503 del proveedor del asistente (S7);
 * - seguridad por cookie de sesión + cabecera X-XSRF-TOKEN, sin bearer; login público.
 */
final class ApiErrorDocumentTransformer implements DocumentTransformer
{
    /** @var array<int, array{codes: list<string>, description: string}> */
    private const ERRORS = [
        401 => ['codes' => ['unauthenticated'], 'description' => 'Sin sesión.'],
        403 => ['codes' => ['forbidden'], 'description' => 'Sin permiso para el rol, o login desde un origen ajeno a la SPA.'],
        404 => ['codes' => ['not_found'], 'description' => 'Recurso inexistente.'],
        409 => ['codes' => ['insufficient_stock', 'invalid_transfer_transition', 'discrepancy_already_resolved'], 'description' => 'La operación dejaría la existencia negativa o la existencia no existe (en la dispensación, con `shortages` por ítem); la acción no está permitida desde el estado del traslado; o la discrepancia ya fue resuelta.'],
        419 => ['codes' => ['csrf_token_mismatch'], 'description' => 'Falta X-XSRF-TOKEN o no corresponde a la sesión.'],
        429 => ['codes' => ['too_many_attempts'], 'description' => 'Demasiados intentos fallidos (login o autorizador de control especial); ver Retry-After.'],
        503 => ['codes' => ['assistant_unavailable'], 'description' => 'El proveedor del asistente no respondió, respondió con error, superó el plazo o no está configurado.'],
    ];

    /**
     * Rechazos de dominio que Scramble no infiere (excepciones propias, design D10 de S2), por operación.
     *
     * @var array<string, list<int>>
     */
    private const DOMAIN_ERRORS = [
        'post stock-adjustments' => [409, 422],
        'get patients/{patient}' => [404],
        'post dispensations/preview' => [422],
        'post dispensations' => [409, 422, 429],
        'post transfers' => [422],
        'get transfers/{transfer}' => [403, 404],
        'post transfers/{transfer}/request' => [403, 404, 409],
        'post transfers/{transfer}/approve' => [403, 404, 409],
        'post transfers/{transfer}/dispatch' => [403, 404, 409, 422],
        'post transfers/{transfer}/receive' => [403, 404, 409],
        'post transfers/{transfer}/void' => [403, 404, 409],
        'post transfers/{transfer}/discrepancies/{discrepancy}/resolve' => [403, 404, 409, 422],
        'post assistant/ask' => [429, 503],
    ];

    /**
     * Operaciones cuyo 422 incluye, además de validation_failed, un rechazo de dominio con la forma ApiError.
     *
     * @var array<string, string>
     */
    private const DOMAIN_422 = [
        'post stock-adjustments' => 'Datos inválidos (code: validation_failed, con errors) o ingreso a un lote vencido (code: lot_expired).',
        'post dispensations/preview' => 'Datos inválidos (code: validation_failed, con errors), o prescripción no dispensable '
            .'(code: prescription_expired, prescription_exhausted, exceeds_prescription).',
        'post dispensations' => 'En este orden: clave de idempotencia ausente o mal formada (invalid_idempotency_key); datos '
            .'inválidos (validation_failed, con errors); misma clave con otro cuerpo (idempotency_key_reused); '
            .'coautorización de control especial (authorization_required, authorizer_must_differ, invalid_authorizer); '
            .'prescripción (prescription_exhausted, prescription_expired, exceeds_prescription).',
        'post transfers' => 'Datos inválidos (code: validation_failed, con errors) o lote vencido en alguna línea (code: lot_expired).',
        'post transfers/{transfer}/dispatch' => 'Lote vencido en alguna línea, evaluado antes que las existencias (code: lot_expired).',
        'post transfers/{transfer}/discrepancies/{discrepancy}/resolve' => 'Datos inválidos (code: validation_failed, con errors) o '
            .'devolución al origen sobre un lote vencido (code: lot_expired).',
    ];

    /**
     * Operaciones cuyo 403 incluye, además de forbidden, un rechazo de dominio.
     *
     * @var array<string, string>
     */
    private const DOMAIN_403 = [
        'post transfers/{transfer}/approve' => 'Sin permiso para el rol (code: forbidden), o quien creó y solicitó el traslado '
            .'intenta aprobarlo (code: segregation_of_duties, RN-08).',
    ];

    /**
     * Operaciones cuyo 429 no es el de intentos fallidos sino el de un limitador de ruta.
     *
     * @var array<string, string>
     */
    private const DOMAIN_429 = [
        'post assistant/ask' => 'Más de 20 preguntas por minuto del mismo usuario (code: too_many_requests); ver Retry-After.',
    ];

    private const IDEMPOTENT_OPERATION = 'post dispensations';

    /**
     * Operaciones sin cuerpo ni query que validar: Scramble les infiere un 422 que nunca responden.
     *
     * @var list<string>
     */
    private const WITHOUT_VALIDATION = [
        'get patients/{patient}', 'get transfers/{transfer}', 'post transfers/{transfer}/request',
        'post transfers/{transfer}/approve', 'post transfers/{transfer}/dispatch',
    ];

    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        // Mismo origen que la SPA: la API vive bajo /api detrás del Nginx de web.
        $document->servers = [Server::make('/api')->setDescription('Mismo origen que la SPA.')];
        $document->info = LicensedInfoObject::from($document->info);

        $apiError = $document->components->addSchema('ApiError', Schema::fromType($this->apiErrorType()));
        $validationError = $document->components->addSchema('ValidationError', Schema::fromType($this->validationErrorType()));

        $session = SecurityScheme::apiKey('cookie', (string) config('session.cookie'))
            ->as('sessionCookie')
            ->setDescription('Cookie de sesión HttpOnly emitida por POST /auth/login.');
        $xsrf = SecurityScheme::apiKey('header', 'X-XSRF-TOKEN')
            ->as('xsrfToken')
            ->setDescription('Valor de la cookie XSRF-TOKEN (GET /sanctum/csrf-cookie). Obligatoria en escrituras.');
        $document->components->addSecurityScheme('sessionCookie', $session);
        $document->components->addSecurityScheme('xsrfToken', $xsrf);

        foreach ($document->paths as $path) {
            foreach ($path->operations as $operation) {
                $this->normalize($operation, $path->path, $apiError, $validationError);
            }
        }

        // Las respuestas de error de Scramble quedaron reemplazadas en línea.
        $document->components->responses = [];
    }

    private function normalize(Operation $operation, string $path, Reference $apiError, Reference $validationError): void
    {
        $isLogin = $path === 'auth/login';
        $isWrite = $operation->method !== 'get';
        $key = $operation->method.' '.$path;

        $codes = [];
        $kept = [];
        foreach ($operation->responses ?? [] as $response) {
            $code = (int) ($response instanceof Reference ? $response->resolve()->code : $response->code);
            $codes[] = $code;
            if (! in_array($code, [401, 403, 404, 409, 419, 422, 429], true)) {
                $kept[] = $response;
            }
        }

        $errors = array_values(array_intersect([401, 403, 404, 422], $codes));
        if (in_array($key, self::WITHOUT_VALIDATION, true)) {
            $errors = array_values(array_diff($errors, [422]));
        }
        $errors = array_values(array_unique([...$errors, ...(self::DOMAIN_ERRORS[$key] ?? [])]));
        sort($errors);
        if ($isWrite) {
            $errors[] = 419;
        }
        if ($isLogin) {
            $errors = [403, 419, 422, 429];
        }

        foreach ($errors as $status) {
            $kept[] = match (true) {
                $status === 422 && isset(self::DOMAIN_422[$key]) => Response::make(422)
                    ->setDescription(self::DOMAIN_422[$key])
                    ->setContent('application/json', $apiError),
                $status === 403 && isset(self::DOMAIN_403[$key]) => Response::make(403)
                    ->setDescription(self::DOMAIN_403[$key])
                    ->setContent('application/json', $apiError),
                $status === 429 && isset(self::DOMAIN_429[$key]) => $this->errorResponse(429, $apiError)
                    ->setDescription(self::DOMAIN_429[$key]),
                $status === 422 => $this->validationResponse($validationError, $apiError, $isLogin),
                default => $this->errorResponse($status, $apiError),
            };
        }
        $operation->responses = $kept;

        if ($key === self::IDEMPOTENT_OPERATION) {
            $this->documentIdempotency($operation);
        }

        $operation->security = $isLogin
            ? [new SecurityRequirement([])]
            : [new SecurityRequirement($isWrite ? ['sessionCookie' => [], 'xsrfToken' => []] : ['sessionCookie' => []])];
    }

    /**
     * Cabecera Idempotency-Key obligatoria y cabecera Idempotent-Replayed en la repetición (RN-09).
     */
    private function documentIdempotency(Operation $operation): void
    {
        $operation->addParameters([
            Parameter::make('Idempotency-Key', 'header')
                ->required(true)
                ->setSchema(Schema::fromType((new StringType)->pattern('^[A-Za-z0-9_-]{16,128}$')))
                ->description('Clave por usuario: repetir clave y cuerpo devuelve la respuesta original sin efectos nuevos.'),
        ]);

        foreach ($operation->responses ?? [] as $response) {
            // El controlador devuelve el texto guardado; Scramble lo documenta como 200 de DispensationResource.
            if ($response instanceof Response && in_array((int) $response->code, [200, 201], true)) {
                $response->code = 201;
                $response->setDescription('Dispensación creada, o su repetición idéntica.');
                $response->addHeader('Idempotent-Replayed', new Header(
                    description: 'Presente con valor true cuando la respuesta es la repetición de la original.',
                    schema: Schema::fromType((new BooleanType)),
                ));
            }
        }
    }

    private function errorResponse(int $status, Reference $apiError): Response
    {
        $response = Response::make($status)
            ->setDescription(self::ERRORS[$status]['description'].' code: '.implode(', ', self::ERRORS[$status]['codes']))
            ->setContent('application/json', $apiError);

        if ($status === 429) {
            $response->addHeader('Retry-After', new Header(
                description: 'Segundos hasta poder reintentar.',
                required: true,
                schema: Schema::fromType(new IntegerType),
            ));
        }

        return $response;
    }

    private function validationResponse(Reference $validationError, Reference $apiError, bool $isLogin): Response
    {
        $response = Response::make(422)
            ->setDescription($isLogin
                ? 'Datos inválidos (code: validation_failed, con errors) o credenciales inválidas (code: invalid_credentials).'
                : 'Datos inválidos. code: validation_failed, con errors por campo.');

        return $response->setContent('application/json', $isLogin ? $apiError : $validationError);
    }

    private function apiErrorType(): ObjectType
    {
        return (new ObjectType)
            ->addProperty('code', (new StringType)->setDescription('Código estable del rechazo (contrato con la SPA).')->enum([
                'unauthenticated', 'forbidden', 'not_found', 'csrf_token_mismatch', 'validation_failed',
                'invalid_credentials', 'too_many_attempts', 'insufficient_stock', 'lot_expired', 'method_not_allowed',
                'http_error', 'server_error', 'prescription_expired', 'prescription_exhausted', 'exceeds_prescription',
                'authorization_required', 'authorizer_must_differ', 'invalid_authorizer', 'invalid_idempotency_key',
                'idempotency_key_reused', 'invalid_transfer_transition', 'segregation_of_duties',
                'discrepancy_already_resolved', 'too_many_requests', 'assistant_unavailable',
            ]))
            ->addProperty('message', (new StringType)->setDescription('Mensaje en español para el usuario.'))
            ->addProperty('errors', $this->fieldErrorsType())
            ->addProperty('shortages', (new ArrayType)
                ->setDescription('Solo en insufficient_stock de la dispensación: ítems que no alcanzan.')
                ->setItems((new ObjectType)
                    ->addProperty('prescription_item_id', new IntegerType)
                    ->addProperty('product_id', new IntegerType)
                    ->addProperty('requested', new IntegerType)
                    ->addProperty('available', new IntegerType)
                    ->setRequired(['prescription_item_id', 'product_id', 'requested', 'available'])))
            ->setRequired(['code', 'message']);
    }

    private function validationErrorType(): ObjectType
    {
        return (new ObjectType)
            ->addProperty('code', (new StringType)->const('validation_failed'))
            ->addProperty('message', (new StringType)->setDescription('Mensaje en español para el usuario.'))
            ->addProperty('errors', $this->fieldErrorsType())
            ->setRequired(['code', 'message', 'errors']);
    }

    private function fieldErrorsType(): ObjectType
    {
        return (new ObjectType)
            ->setDescription('Mensajes en español por campo inválido.')
            ->additionalProperties((new ArrayType)->setItems(new StringType));
    }
}
