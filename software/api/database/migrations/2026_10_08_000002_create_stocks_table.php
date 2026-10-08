<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Existencias por bodega + producto + lote (RN-01), defendidas en la base (design Data impact fila 2):
| única por la terna, cantidad entera nunca negativa (RN-03), producto coherente con el lote por FK
| compuesta, y sin borrar bodega ni lote que tengan existencias.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocks', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->foreignId('warehouse_id')
                ->constrained('warehouses', indexName: 'stocks_warehouse_id_foreign')
                ->restrictOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('lot_id');
            $table->integer('quantity')->default(0);
            $table->timestampsTz();

            $table->unique(['warehouse_id', 'product_id', 'lot_id'], 'stocks_warehouse_product_lot_unique');
            $table->foreign(['lot_id', 'product_id'], 'stocks_lot_product_foreign')
                ->references(['id', 'product_id'])
                ->on('lots')
                ->restrictOnDelete();
            $table->index(['lot_id', 'product_id'], 'stocks_lot_id_product_id_index');
            $table->index('product_id', 'stocks_product_id_index');
        });

        DB::statement('ALTER TABLE stocks ADD CONSTRAINT stocks_quantity_non_negative CHECK (quantity >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
