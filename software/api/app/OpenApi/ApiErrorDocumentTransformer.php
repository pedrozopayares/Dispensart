<?php

namespace App\OpenApi;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\Header;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\SecurityRequirement;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Dedoc\Scramble\Support\Generator\Server;
use Dedoc\Scramble\Support\Generator\Types\ArrayType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;

/**
 * Ajusta el OpenAPI inferido por Scramble al contrato real (design D5, D9; solo desarrollo):
 * - todo rechazo con la forma {code, message[, errors]} y su `code` estable;
 * - 419 en toda escritura (CSRF), 403/422/429 propios del login;
 * - seguridad por cookie de sesión + cabecera X-XSRF-TOKEN, sin bearer; login público.
 */
final class ApiErrorDocumentTransformer implements DocumentTransformer
{
    /** @var array<int, array{codes: list<string>, description: string}> */
    private const ERRORS = [
        401 => ['codes' => ['unauthenticated'], 'description' => 'Sin sesión.'],
        403 => ['codes' => ['forbidden'], 'description' => 'Sin permiso para el rol, o login desde un origen ajeno a la SPA.'],
        404 => ['codes' => ['not_found'], 'description' => 'Recurso inexistente.'],
        419 => ['codes' => ['csrf_token_mismatch'], 'description' => 'Falta X-XSRF-TOKEN o no corresponde a la sesión.'],
        429 => ['codes' => ['too_many_attempts'], 'description' => 'Demasiados intentos fallidos de login; ver Retry-After.'],
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

        $codes = [];
        $kept = [];
        foreach ($operation->responses ?? [] as $response) {
            $code = (int) ($response instanceof Reference ? $response->resolve()->code : $response->code);
            $codes[] = $code;
            if (! in_array($code, [401, 403, 404, 419, 422, 429], true)) {
                $kept[] = $response;
            }
        }

        $errors = array_values(array_intersect([401, 403, 404, 422], $codes));
        if ($isWrite) {
            $errors[] = 419;
        }
        if ($isLogin) {
            $errors = [403, 419, 422, 429];
        }

        foreach ($errors as $status) {
            $kept[] = $status === 422
                ? $this->validationResponse($validationError, $apiError, $isLogin)
                : $this->errorResponse($status, $apiError);
        }
        $operation->responses = $kept;

        $operation->security = $isLogin
            ? [new SecurityRequirement([])]
            : [new SecurityRequirement($isWrite ? ['sessionCookie' => [], 'xsrfToken' => []] : ['sessionCookie' => []])];
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
                'invalid_credentials', 'too_many_attempts', 'method_not_allowed', 'http_error', 'server_error',
            ]))
            ->addProperty('message', (new StringType)->setDescription('Mensaje en español para el usuario.'))
            ->addProperty('errors', $this->fieldErrorsType())
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
