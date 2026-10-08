<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Producto embebido en existencias y movimientos.
 *
 * @mixin Product
 */
final class ProductSummaryResource extends JsonResource
{
    /**
     * @return array{id: int, code: string, name: string, is_controlled: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'is_controlled' => $this->is_controlled,
        ];
    }
}
