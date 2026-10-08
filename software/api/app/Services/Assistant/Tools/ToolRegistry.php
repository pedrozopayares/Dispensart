<?php

namespace App\Services\Assistant\Tools;

use App\Services\Assistant\Llm\ToolDefinition;

/**
 * Catálogo cerrado de las 4 herramientas (design D3): lista fija en el constructor, sin descubrimiento por
 * contenedor ni etiquetas. Un nombre fuera del catálogo no resuelve a nada.
 */
final class ToolRegistry
{
    /** @var array<string, AssistantTool> */
    private array $tools = [];

    public function __construct(
        FindExpiringLotsTool $expiringLots,
        GetStockTool $stock,
        GetLowStockAlertsTool $lowStock,
        GetTransferStatusTool $transferStatus,
    ) {
        foreach ([$expiringLots, $stock, $lowStock, $transferStatus] as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
    }

    /**
     * @return list<ToolDefinition>
     */
    public function definitions(): array
    {
        return array_values(array_map(
            fn (AssistantTool $tool): ToolDefinition => new ToolDefinition($tool->name(), $tool->description(), $tool->parameters()),
            $this->tools,
        ));
    }

    public function find(string $name): ?AssistantTool
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->tools);
    }
}
