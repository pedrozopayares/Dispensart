<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Líneas de dispensación: una por lote consumido, enlazada a su movimiento del kardex (design D2, Data
| impact fila 5). Las FKs compuestas hacen coherentes línea ↔ dispensación ↔ prescripción ↔ ítem ↔
| producto ↔ lote; cada movimiento respalda a lo sumo una línea. kardex_movements no cambia (S2 intacto).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispensation_lines', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->unsignedBigInteger('dispensation_id');
            $table->unsignedBigInteger('prescription_id');
            $table->unsignedBigInteger('prescription_item_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('lot_id');
            $table->integer('quantity');
            $table->foreignId('kardex_movement_id')
                ->constrained('kardex_movements', indexName: 'dispensation_lines_kardex_movement_id_foreign')
                ->restrictOnDelete();

            $table->foreign(['dispensation_id', 'prescription_id'], 'dispensation_lines_dispensation_prescription_foreign')
                ->references(['id', 'prescription_id'])
                ->on('dispensations')
                ->restrictOnDelete();
            $table->foreign(['prescription_item_id', 'prescription_id', 'product_id'], 'dispensation_lines_item_foreign')
                ->references(['id', 'prescription_id', 'product_id'])
                ->on('prescription_items')
                ->restrictOnDelete();
            $table->foreign(['lot_id', 'product_id'], 'dispensation_lines_lot_product_foreign')
                ->references(['id', 'product_id'])
                ->on('lots')
                ->restrictOnDelete();
            $table->unique('kardex_movement_id', 'dispensation_lines_kardex_movement_unique');
            $table->unique(['dispensation_id', 'lot_id'], 'dispensation_lines_dispensation_lot_unique');
            $table->index(['dispensation_id', 'prescription_id'], 'dispensation_lines_dispensation_prescription_index');
            $table->index('prescription_item_id', 'dispensation_lines_prescription_item_id_index');
            $table->index('lot_id', 'dispensation_lines_lot_id_index');
        });

        DB::statement('ALTER TABLE dispensation_lines ADD CONSTRAINT dispensation_lines_quantity_positive CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('dispensation_lines');
    }
};
