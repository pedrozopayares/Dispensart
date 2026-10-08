<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Ítems de prescripción con su saldo dispensado (prescriptions "Saldo acumulado por ítem", RN-04, design
| Data impact fila 3). La base impide dispensar más de lo prescrito aunque faltara el bloqueo de la acción.
| (id, prescription_id, product_id) es clave candidata: destino de la FK compuesta de las líneas.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescription_items', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->foreignId('prescription_id')
                ->constrained('prescriptions', indexName: 'prescription_items_prescription_id_foreign')
                ->restrictOnDelete();
            $table->foreignId('product_id')
                ->constrained('products', indexName: 'prescription_items_product_id_foreign')
                ->restrictOnDelete();
            $table->integer('prescribed_quantity');
            $table->integer('dispensed_quantity')->default(0);
            $table->timestampsTz();

            $table->unique(['prescription_id', 'product_id'], 'prescription_items_prescription_product_unique');
            $table->unique(['id', 'prescription_id', 'product_id'], 'prescription_items_id_prescription_product_unique');
            $table->index('product_id', 'prescription_items_product_id_index');
        });

        DB::statement(
            'ALTER TABLE prescription_items ADD CONSTRAINT prescription_items_prescribed_positive CHECK (prescribed_quantity >= 1)'
        );
        DB::statement(
            'ALTER TABLE prescription_items ADD CONSTRAINT prescription_items_dispensed_range '
            .'CHECK (dispensed_quantity BETWEEN 0 AND prescribed_quantity)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
    }
};
