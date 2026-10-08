<?php

use App\Http\Middleware\AssignCorrelationId;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        then: function (): void {
            Route::group([], base_path('routes/health.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Primero de la pila global: todo lo demás (incluidos los errores) ya tiene correlation_id.
        $middleware->prepend(AssignCorrelationId::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Forma estable de error JSON {code, message}: sin traza ni mensaje interno, aun con APP_DEBUG.
        // Validación, autenticación y respuestas explícitas conservan el render de Laravel.
        $exceptions->render(function (Throwable $e, Request $request): ?JsonResponse {
            if (! ($request->is('api/*') || $request->expectsJson())
                || $e instanceof ValidationException
                || $e instanceof AuthenticationException
                || $e instanceof HttpResponseException) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $code = match (true) {
                $status === 404 => 'not_found',
                $status === 405 => 'method_not_allowed',
                $status >= 500 => 'server_error',
                default => 'http_error',
            };
            $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

            return new JsonResponse(['code' => $code, 'message' => __('errors.'.$code)], $status, $headers);
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
