<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Discrepancias de recepción (RN-07, design D10, Data impact fila 3): una por línea con faltante > 0, de una
| línea del mismo traslado (FK compuesta). Los datos de resolución existen si y solo si está resuelta; el
| motivo nunca en blanco. La devolución al origen enlaza su movimiento de ajuste.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_discrepancies', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->unsignedBigInteger('transfer_id');
            $table->unsignedBigInteger('transfer_line_id');
            $table->integer('shortage');
            $table->string('status', 16);
            $table->string('resolution', 32)->nullable();
            $table->string('resolution_reason', 500)->nullable();
            $table->foreignId('resolved_by')
                ->nullable()
                ->constrained('users', indexName: 'transfer_discrepancies_resolved_by_foreign')
                ->restrictOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->foreignId('adjustment_movement_id')
                ->nullable()
                ->constrained('kardex_movements', indexName: 'transfer_discrepancies_adjustment_movement_id_foreign')
                ->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['transfer_line_id', 'transfer_id'], 'transfer_discrepancies_line_transfer_foreign')
                ->references(['id', 'transfer_id'])
                ->on('transfer_lines')
                ->restrictOnDelete();
            $table->unique('transfer_line_id', 'transfer_discrepancies_line_unique');
            $table->unique('adjustment_movement_id', 'transfer_discrepancies_adjustment_movement_unique');
            $table->index(['transfer_id', 'status'], 'transfer_discrepancies_transfer_status_index');
        });

        DB::statement('ALTER TABLE transfer_discrepancies ADD CONSTRAINT transfer_discrepancies_shortage_positive CHECK (shortage > 0)');
        DB::statement(
            "ALTER TABLE transfer_discrepancies ADD CONSTRAINT transfer_discrepancies_status_check CHECK (status IN ('pending', 'resolved'))"
        );
        DB::statement(
            'ALTER TABLE transfer_discrepancies ADD CONSTRAINT transfer_discrepancies_resolution_check '
            ."CHECK (resolution IS NULL OR resolution IN ('returned_to_origin', 'written_off'))"
        );
        DB::statement(
            'ALTER TABLE transfer_discrepancies ADD CONSTRAINT transfer_discrepancies_resolution_coherent CHECK ('
            ."(status = 'pending' AND resolution IS NULL AND resolution_reason IS NULL AND resolved_by IS NULL AND resolved_at IS NULL)"
            ." OR (status = 'resolved' AND resolution IS NOT NULL AND resolution_reason IS NOT NULL AND resolved_by IS NOT NULL"
            ." AND resolved_at IS NOT NULL AND btrim(resolution_reason) <> ''))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_discrepancies');
    }
};
