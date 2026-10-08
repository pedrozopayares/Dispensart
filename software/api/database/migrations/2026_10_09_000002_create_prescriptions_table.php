<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Prescripciones (prescriptions, design Data impact fila 2). El estado no se guarda: se calcula en cada
| lectura. (id, patient_id) es clave candidata: destino de la FK compuesta de dispensaciones, que así exige
| que el paciente de la dispensación sea el de su prescripción.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->foreignId('patient_id')
                ->constrained('patients', indexName: 'prescriptions_patient_id_foreign')
                ->restrictOnDelete();
            $table->foreignId('prescriber_id')
                ->constrained('users', indexName: 'prescriptions_prescriber_id_foreign')
                ->restrictOnDelete();
            $table->date('valid_until');
            $table->timestampsTz();

            $table->unique(['id', 'patient_id'], 'prescriptions_id_patient_unique');
            $table->index(['patient_id', 'created_at'], 'prescriptions_patient_created_at_index');
            $table->index('prescriber_id', 'prescriptions_prescriber_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
