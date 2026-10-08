<?php

namespace App\Services\Assistant\Tools;

/**
 * Herramienta de solo lectura del catálogo cerrado (design D3, D6). Solo ReadOnlyToolRunner la ejecuta: después
 * de autorizar con la Policy de `policySubject()` y dentro de una transacción de solo lectura.
 */
interface AssistantTool
{
    public function name(): string;

    public function description(): string;

    /**
     * Único esquema de argumentos: lo recibe el modelo y lo aplica ToolArgumentValidator.
     *
     * @return array<string, mixed>
     */
    public function parameters(): array;

    /**
     * Clase cuya Policy `viewAny` autoriza la herramienta (la misma de la ruta REST equivalente).
     *
     * @return class-string
     */
    public function policySubject(): string;

    /**
     * Resultado materializado en arreglos PHP con lista blanca de campos. `items` vacío = sin resultados; `meta`
     * lleva los parámetros efectivos (p. ej. el plazo usado).
     *
     * @param  array<string, mixed>  $arguments  ya validados contra parameters()
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function run(array $arguments): array;
}
