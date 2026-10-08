<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Pacientes sintéticos (patients "Identidad única del paciente", design Data impact fila 1). Tipo y número
| de documento únicos juntos; número y nombre nunca vacíos. Los literales de tipo viven aquí: una migración
| es una foto del esquema.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->string('document_type', 3);
            $table->string('document_number', 20);
            $table->string('full_name', 150);
            $table->date('birth_date')->nullable();
            $table->string('phone', 20)->nullable();
            $table->timestampsTz();

            $table->unique(['document_type', 'document_number'], 'patients_document_unique');
        });

        DB::statement(
            "ALTER TABLE patients ADD CONSTRAINT patients_document_type_check CHECK (document_type IN ('CC', 'TI', 'CE', 'PA', 'RC'))"
        );
        DB::statement("ALTER TABLE patients ADD CONSTRAINT patients_document_number_not_blank CHECK (btrim(document_number) <> '')");
        DB::statement("ALTER TABLE patients ADD CONSTRAINT patients_full_name_not_blank CHECK (btrim(full_name) <> '')");
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
