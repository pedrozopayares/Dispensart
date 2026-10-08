<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Dispensaciones (dispensation, design Data impact fila 4). Sin updated_at: una dispensación no cambia.
| El paciente debe ser el de la prescripción (FK compuesta) y el autorizador de control especial nunca es
| el dispensador (RN-05), aunque la escritura no pase por la API.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispensations', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->unsignedBigInteger('prescription_id');
            $table->unsignedBigInteger('patient_id');
            $table->foreignId('warehouse_id')
                ->constrained('warehouses', indexName: 'dispensations_warehouse_id_foreign')
                ->restrictOnDelete();
            $table->foreignId('dispensed_by')
                ->constrained('users', indexName: 'dispensations_dispensed_by_foreign')
                ->restrictOnDelete();
            $table->foreignId('authorized_by')
                ->nullable()
                ->constrained('users', indexName: 'dispensations_authorized_by_foreign')
                ->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign(['prescription_id', 'patient_id'], 'dispensations_prescription_patient_foreign')
                ->references(['id', 'patient_id'])
                ->on('prescriptions')
                ->restrictOnDelete();
            $table->unique(['id', 'prescription_id'], 'dispensations_id_prescription_unique');
            $table->index(['prescription_id', 'patient_id'], 'dispensations_prescription_patient_index');
            $table->index('patient_id', 'dispensations_patient_id_index');
            $table->index('warehouse_id', 'dispensations_warehouse_id_index');
            $table->index('dispensed_by', 'dispensations_dispensed_by_index');
            $table->index('authorized_by', 'dispensations_authorized_by_index');
        });

        DB::statement(
            'ALTER TABLE dispensations ADD CONSTRAINT dispensations_authorizer_differs '
            .'CHECK (authorized_by IS NULL OR authorized_by <> dispensed_by)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('dispensations');
    }
};
