<?php

namespace App\Http\Middleware;

use Closure;
use App\Support\RoutePattern;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Identificador de correlación por petición y línea de log de cierre (design D6).
 *
 * Respeta un X-Correlation-Id válido, genera un UUID si falta o es inválido, lo publica en Context
 * (de ahí lo toman todas las líneas de log) y lo devuelve en la respuesta.
 */
final class AssignCorrelationId
{
    public const HEADER = 'X-Correlation-Id';

    public const CONTEXT_KEY = 'correlation_id';

    private const VALID_PATTERN = '/\A[A-Za-z0-9._-]{1,128}\z/';

    private const STARTED_AT_ATTRIBUTE = 'correlation_started_at';

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::STARTED_AT_ATTRIBUTE, hrtime(true));

        $correlationId = $this->resolve($request->headers->get(self::HEADER));
        Context::add(self::CONTEXT_KEY, $correlationId);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set(self::HEADER, $correlationId);

        return $response;
    }

    /**
     * Línea de cierre: solo método, patrón de la ruta resuelta (`/api/patients/{patient}`, nunca la ruta literal
     * con identificadores; `unmatched` sin ruta), estado y duración. Nunca cuerpo, query string, cookies ni
     * cabeceras (RN-10, design D9 de S3). Una ruta sin parámetros (`/ready`) se registra igual que antes.
     */
    public function terminate(Request $request, Response $response): void
    {
        $startedAt = $request->attributes->get(self::STARTED_AT_ATTRIBUTE);
        $durationMs = is_int($startedAt) ? round((hrtime(true) - $startedAt) / 1_000_000, 2) : null;

        Log::info('request.completed', [
            'method' => $request->getMethod(),
            'path' => RoutePattern::of($request),
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
        ]);

        // La petición terminó: el identificador no debe heredarse en un proceso de larga vida.
        Context::forget(self::CONTEXT_KEY);
    }

    private function resolve(?string $received): string
    {
        if ($received !== null && preg_match(self::VALID_PATTERN, $received) === 1) {
            return $received;
        }

        return (string) Str::uuid();
    }
}
