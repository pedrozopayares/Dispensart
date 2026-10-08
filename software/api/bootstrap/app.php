<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Exceptions\InsufficientStock;
use App\Exceptions\InvalidCredentials;
use App\Exceptions\LotExpired;
use App\Exceptions\TooManyLoginAttempts;
use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        then: function (): void {
            Route::group([], base_path('routes/health.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Primero de la pila global: todo lo demás (incluidos los errores) ya tiene correlation_id.
        $middleware->prepend(AssignCorrelationId::class);

        // Sesión Sanctum solo para peticiones desde los orígenes de la SPA (design D1).
        $middleware->statefulApi();

        // API sin pantallas de login: un invitado recibe 401 JSON, nunca una redirección (ni un 500 por
        // la ruta login inexistente cuando falta Accept).
        $middleware->redirectGuestsTo(fn (): ?string => null);

        // CSRF sin atajo de pruebas también en el grupo web (/sanctum/csrf-cookie) (design D2).
        $middleware->web(replace: [PreventRequestForgery::class => ValidateCsrfToken::class]);

        // Proxies de confianza: config/trustedproxy.php (rangos privados por defecto, design D3).
        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Rechazos de negocio esperados: no son fallos del sistema, no se reportan.
        $exceptions->dontReport([
            InvalidCredentials::class, TooManyLoginAttempts::class, InsufficientStock::class, LotExpired::class,
        ]);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Forma estable de error JSON {code, message[, errors]} para todo rechazo (design D5), aun con
        // APP_DEBUG: sin traza ni mensaje interno. Solo las respuestas explícitas conservan su forma.
        $exceptions->render(function (Throwable $e, Request $request): ?JsonResponse {
            if (! ($request->is('api/*') || $request->expectsJson()) || $e instanceof HttpResponseException) {
                return null;
            }

            return ApiExceptionRenderer::render($e);
        });

        // Respaldo: toda respuesta de error lleva X-Correlation-Id aunque no pase por el middleware.
        $exceptions->respond(function (Response $response): Response {
            $correlationId = Context::get(AssignCorrelationId::CONTEXT_KEY);

            if (is_string($correlationId) && ! $response->headers->has(AssignCorrelationId::HEADER)) {
                $response->headers->set(AssignCorrelationId::HEADER, $correlationId);
            }

            return $response;
        });
    })->create();
