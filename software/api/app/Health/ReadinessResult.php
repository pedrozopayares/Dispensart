<?php

namespace App\Health;

/**
 * Resultado de la comprobación de disponibilidad. Sin mensajes ni datos de conexión: solo estados.
 */
final readonly class ReadinessResult
{
    public const OK = 'ok';

    public const FAIL = 'fail';

    public const PENDING = 'pending';

    public const SKIPPED = 'skipped';

    public function __construct(
        public string $database,
        public string $migrations,
    ) {}

    public function isReady(): bool
    {
        return $this->database === self::OK && $this->migrations === self::OK;
    }

    /**
     * @return array{status: string, checks: array{database: string, migrations: string}}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->isReady() ? 'ready' : 'not_ready',
            'checks' => [
                'database' => $this->database,
                'migrations' => $this->migrations,
            ],
        ];
    }
}
