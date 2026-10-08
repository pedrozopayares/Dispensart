<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Traslados entre bodegas (transfers, RN-07, RN-08; design D3, Data impact fila 1). Un actor y una fecha por
| transición. La base defiende, aunque la escritura no pase por la API: los 7 estados literales, origen ≠
| destino, solicitante = creador y aprobador distinto del creador y del solicitante. Sin CHECK estado ↔ actor
| (design D3). Literales escritos aquí, no desde el enum.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->foreignId('origin_warehouse_id')
                ->constrained('warehouses', indexName: 'transfers_origin_warehouse_id_foreign')
                ->restrictOnDelete();
            $table->foreignId('destination_warehouse_id')
                ->constrained('warehouses', indexName: 'transfers_destination_warehouse_id_foreign')
                ->restrictOnDelete();
            $table->string('status', 20);
            $table->string('notes', 1000)->nullable();
            $table->foreignId('created_by')
                ->constrained('users', indexName: 'transfers_created_by_foreign')
                ->restrictOnDelete();
            foreach (['requested', 'approved', 'dispatched', 'received', 'voided'] as $step) {
                $table->foreignId("{$step}_by")
                    ->nullable()
                    ->constrained('users', indexName: "transfers_{$step}_by_foreign")
                    ->restrictOnDelete();
                $table->timestampTz("{$step}_at")->nullable();
            }
            $table->string('void_reason', 500)->nullable();
            $table->timestampsTz();

            $table->index(['created_at', 'id'], 'transfers_created_at_id_index');
            $table->index('status', 'transfers_status_index');
            $table->index('origin_warehouse_id', 'transfers_origin_warehouse_id_index');
            $table->index('destination_warehouse_id', 'transfers_destination_warehouse_id_index');
        });

        DB::statement(
            'ALTER TABLE transfers ADD CONSTRAINT transfers_status_check CHECK (status IN '
            ."('BORRADOR', 'SOLICITADO', 'APROBADO', 'EN_TRANSITO', 'RECIBIDO', 'RECIBIDO_PARCIAL', 'ANULADO'))"
        );
        DB::statement(
            'ALTER TABLE transfers ADD CONSTRAINT transfers_distinct_warehouses '
            .'CHECK (origin_warehouse_id <> destination_warehouse_id)'
        );
        // Solo el creador solicita: comparar contra created_by equivale a comparar contra el solicitante (D3).
        DB::statement(
            'ALTER TABLE transfers ADD CONSTRAINT transfers_requester_is_creator '
            .'CHECK (requested_by IS NULL OR requested_by = created_by)'
        );
        // RN-08: quien solicita nunca aprueba.
        DB::statement(
            'ALTER TABLE transfers ADD CONSTRAINT transfers_approver_differs '
            .'CHECK (approved_by IS NULL OR (approved_by <> created_by AND approved_by IS DISTINCT FROM requested_by))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
