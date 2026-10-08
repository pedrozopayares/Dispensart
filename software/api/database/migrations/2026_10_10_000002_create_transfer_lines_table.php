<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Líneas de traslado: lote + cantidad, producto coherente con el lote (FK compuesta, como S2) y enlace a sus
| movimientos de despacho y recepción (design D11, Data impact fila 2). Cada movimiento respalda a lo sumo una
| línea; kardex_movements no cambia.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_lines', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->foreignId('transfer_id')
                ->constrained('transfers', indexName: 'transfer_lines_transfer_id_foreign')
                ->restrictOnDelete();
            $table->unsignedBigInteger('lot_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity');
            $table->integer('received_quantity')->nullable();
            $table->foreignId('dispatch_movement_id')
                ->nullable()
                ->constrained('kardex_movements', indexName: 'transfer_lines_dispatch_movement_id_foreign')
                ->restrictOnDelete();
            $table->foreignId('receipt_movement_id')
                ->nullable()
                ->constrained('kardex_movements', indexName: 'transfer_lines_receipt_movement_id_foreign')
                ->restrictOnDelete();

            $table->foreign(['lot_id', 'product_id'], 'transfer_lines_lot_product_foreign')
                ->references(['id', 'product_id'])
                ->on('lots')
                ->restrictOnDelete();
            $table->unique(['transfer_id', 'lot_id'], 'transfer_lines_transfer_lot_unique');
            $table->unique(['id', 'transfer_id'], 'transfer_lines_id_transfer_unique');
            $table->unique('dispatch_movement_id', 'transfer_lines_dispatch_movement_unique');
            $table->unique('receipt_movement_id', 'transfer_lines_receipt_movement_unique');
            $table->index(['lot_id', 'product_id'], 'transfer_lines_lot_product_index');
        });

        DB::statement('ALTER TABLE transfer_lines ADD CONSTRAINT transfer_lines_quantity_positive CHECK (quantity > 0)');
        DB::statement(
            'ALTER TABLE transfer_lines ADD CONSTRAINT transfer_lines_received_range '
            .'CHECK (received_quantity IS NULL OR received_quantity BETWEEN 0 AND quantity)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_lines');
    }
};
