<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Stock mínimo por bodega + producto (inventory-alerts, RN-11; design D1, Data impact fila 1). Sin fila = sin
| mínimo: el mínimo es un entero estrictamente positivo, sin valor por defecto. La base defiende, aunque la
| escritura no pase por la API: un mínimo por par, par existente y sin borrar bodega ni producto con mínimo.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_minimums', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->foreignId('warehouse_id')
                ->constrained('warehouses', indexName: 'stock_minimums_warehouse_id_foreign')
                ->restrictOnDelete();
            $table->foreignId('product_id')
                ->constrained('products', indexName: 'stock_minimums_product_id_foreign')
                ->restrictOnDelete();
            $table->integer('minimum_quantity');
            $table->timestampsTz();

            $table->unique(['warehouse_id', 'product_id'], 'stock_minimums_warehouse_product_unique');
            // Sirve la comprobación RESTRICT al borrar un producto (design D6).
            $table->index('product_id', 'stock_minimums_product_id_index');
        });

        DB::statement('ALTER TABLE stock_minimums ADD CONSTRAINT stock_minimums_minimum_quantity_positive CHECK (minimum_quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_minimums');
    }
};
