<?php

namespace App\Services\Assistant\Tools;

use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Assistant\Text;
use Illuminate\Database\Eloquent\Model;

/**
 * Resuelve el texto de producto o bodega que eligió el modelo a un id del catálogo (design D6). Compara sin tildes
 * ni mayúsculas, en tres pasos, cada uno exigiendo un único candidato: igualdad, nombre que contiene el texto, y
 * todas las palabras del texto presentes en el nombre. Ambiguo o sin coincidencia → null (resultado vacío), nunca
 * "el primero".
 */
final class CatalogResolver
{
    /**
     * Filtros `product` y `warehouse` presentes en los argumentos, resueltos a ids. Null si alguno presente no
     * resuelve: la herramienta responde vacío sin consultar existencias.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{warehouse_id?: int, product_id?: int}|null
     */
    public function filters(array $arguments): ?array
    {
        $filters = [];
        if (is_string($arguments['warehouse'] ?? null)) {
            $id = $this->warehouseId($arguments['warehouse']);
            if ($id === null) {
                return null;
            }
            $filters['warehouse_id'] = $id;
        }
        if (is_string($arguments['product'] ?? null)) {
            $id = $this->productId($arguments['product']);
            if ($id === null) {
                return null;
            }
            $filters['product_id'] = $id;
        }

        return $filters;
    }

    public function warehouseId(string $text): ?int
    {
        return $this->resolve($text, Warehouse::query()->get(['id', 'name'])->all());
    }

    public function productId(string $text): ?int
    {
        return $this->resolve($text, Product::query()->get(['id', 'name'])->all());
    }

    /**
     * @param  list<Model>  $candidates  con `id` y `name`
     */
    private function resolve(string $text, array $candidates): ?int
    {
        $term = Text::normalize($text);
        $termWords = Text::words($text);
        if ($term === '') {
            return null;
        }

        $names = [];
        foreach ($candidates as $candidate) {
            $names[(int) $candidate->getAttribute('id')] = (string) $candidate->getAttribute('name');
        }

        $steps = [
            fn (string $name): bool => Text::normalize($name) === $term,
            fn (string $name): bool => str_contains(Text::normalize($name), $term),
            fn (string $name): bool => $termWords !== [] && array_diff($termWords, Text::words($name)) === [],
        ];

        foreach ($steps as $matches) {
            $found = array_keys(array_filter($names, $matches));
            if (count($found) === 1) {
                return $found[0];
            }
            if (count($found) > 1) {
                return null;
            }
        }

        return null;
    }
}
