<?php

namespace App\Services\Idempotency;

use Illuminate\Http\Response;

/**
 * Respuesta exitosa de una operación idempotente: la original o su repetición (design D5). El cuerpo es el
 * texto guardado, idéntico byte a byte entre la primera respuesta y cada repetición.
 */
final readonly class StoredResponse
{
    public const REPLAYED_HEADER = 'Idempotent-Replayed';

    public function __construct(
        public int $status,
        public string $body,
        public bool $replayed,
    ) {}

    public function toResponse(): Response
    {
        $headers = ['Content-Type' => 'application/json'];
        if ($this->replayed) {
            $headers[self::REPLAYED_HEADER] = 'true';
        }

        return new Response($this->body, $this->status, $headers);
    }
}
