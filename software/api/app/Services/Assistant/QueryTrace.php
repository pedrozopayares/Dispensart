<?php

namespace App\Services\Assistant;

/**
 * Traza de una pregunta mientras se responde: rondas y llamadas hechas. La lee el log también cuando el proveedor
 * falla a mitad del ciclo (503), sin depender de que el orquestador termine.
 */
final class QueryTrace
{
    public int $rounds = 0;

    /** @var list<ToolCallRecord> */
    public array $calls = [];
}
