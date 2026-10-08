<?php

namespace App\Services\Idempotency;

use App\Exceptions\IdempotencyKeyReused;
use App\Models\IdempotencyKey;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Almacén de idempotencia por usuario (RN-09, design D5). Solo guarda éxitos, en la transacción de la
 * operación: un rechazo o un fallo revierte todo y la clave no se consume. Dos reintentos simultáneos se
 * serializan con un candado de asesoramiento transaccional por (usuario, clave), el primero que toma la
 * transacción: el segundo, al entrar, relee el registro confirmado y repite.
 */
final class IdempotencyStore
{
    /**
     * Huella sha256 del JSON canónico (claves ordenadas recursivamente, listas en su orden) de método, patrón
     * de ruta y datos validados. Quien llama excluye lo que no debe contar (credenciales del autorizador).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fingerprint(string $method, string $route, array $payload): string
    {
        $canonical = self::canonical(['method' => strtoupper($method), 'route' => $route, 'payload' => $payload]);

        return hash('sha256', (string) json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Respuesta guardada para la clave del usuario, o null. Otra huella con la misma clave se rechaza.
     *
     * @throws IdempotencyKeyReused
     */
    public function find(User $user, string $key, string $hash): ?StoredResponse
    {
        $stored = IdempotencyKey::query()->where('user_id', $user->id)->where('key', $key)->first();

        if ($stored === null) {
            return null;
        }
        if (! hash_equals($stored->request_hash, $hash)) {
            throw new IdempotencyKeyReused;
        }

        return new StoredResponse($stored->response_status, $stored->response_body, replayed: true);
    }

    /**
     * Candado de la clave hasta el fin de la transacción en curso. Debe ser el primer candado tomado.
     */
    public function lock(User $user, string $key): void
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['idempotency:'.$user->id.':'.$key]);
    }

    public function remember(User $user, string $key, string $hash, StoredResponse $response): void
    {
        DB::table('idempotency_keys')->insert([
            'user_id' => $user->id,
            'key' => $key,
            'request_hash' => $hash,
            'response_status' => $response->status,
            'response_body' => $response->body,
        ]);
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::canonical(...), $value);
    }
}
