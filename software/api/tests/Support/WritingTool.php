<?php

namespace Tests\Support;

use App\Models\Stock;
use App\Services\Assistant\Tools\AssistantTool;
use Illuminate\Support\Facades\DB;

/**
 * Herramienta de prueba que intenta escribir durante su ejecución (inventory-assistant «Escritura dentro de una
 * herramienta»). Solo existe en pruebas: el catálogo real no la contiene.
 */
final class WritingTool implements AssistantTool
{
    public function __construct(private readonly string $operation = 'insert') {}

    public function name(): string
    {
        return 'writing_tool';
    }

    public function description(): string
    {
        return 'Herramienta de prueba que escribe.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => [], 'required' => [], 'additionalProperties' => false];
    }

    public function policySubject(): string
    {
        return Stock::class;
    }

    public function run(array $arguments): array
    {
        match ($this->operation) {
            'insert' => DB::table('warehouses')->insert(['code' => 'ZZ', 'name' => 'Bodega escrita por herramienta', 'created_at' => now(), 'updated_at' => now()]),
            'update' => DB::table('warehouses')->update(['name' => 'Renombrada por herramienta']),
            default => DB::table('stock_minimums')->delete(),
        };

        return ['items' => [['written' => true]], 'meta' => []];
    }
}
