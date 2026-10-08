<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Bitácoras de solo inserción (audit-trail, RN-05, RN-10, design Data impact fila 7): acceso a pacientes y
| operaciones sensibles. Sin datos personales: solo ids; el detalle de una operación solo admite números.
| Un trigger de sentencia rechaza UPDATE, DELETE y TRUNCATE de cualquier rol (ENABLE ALWAYS, como el kardex).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_access_logs', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->foreignId('user_id')
                ->constrained('users', indexName: 'patient_access_logs_user_id_foreign')
                ->restrictOnDelete();
            $table->foreignId('patient_id')
                ->constrained('patients', indexName: 'patient_access_logs_patient_id_foreign')
                ->restrictOnDelete();
            $table->string('action', 16);
            $table->string('route', 255);
            $table->string('correlation_id', 128)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['patient_id', 'created_at'], 'patient_access_logs_patient_created_at_index');
            $table->index(['user_id', 'created_at'], 'patient_access_logs_user_created_at_index');
        });
        DB::statement("ALTER TABLE patient_access_logs ADD CONSTRAINT patient_access_logs_action_check CHECK (action IN ('view', 'search'))");

        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->foreignId('actor_id')
                ->constrained('users', indexName: 'audit_events_actor_id_foreign')
                ->restrictOnDelete();
            $table->string('action', 64);
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->jsonb('details')->default(DB::raw("'{}'::jsonb"));
            $table->string('correlation_id', 128)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id'], 'audit_events_subject_index');
            $table->index(['actor_id', 'created_at'], 'audit_events_actor_created_at_index');
        });
        DB::statement(
            'ALTER TABLE audit_events ADD CONSTRAINT audit_events_action_check CHECK (action IN '
            ."('prescription.created', 'dispensation.created', 'controlled_drug.authorized', 'controlled_drug.authorization_failed'))"
        );
        DB::statement(
            "ALTER TABLE audit_events ADD CONSTRAINT audit_events_subject_type_check CHECK (subject_type IN ('prescription', 'dispensation'))"
        );
        // Cada acción sobre su tipo de objeto: el fallo de autorización no tiene dispensación, cuelga de la prescripción.
        DB::statement(
            'ALTER TABLE audit_events ADD CONSTRAINT audit_events_action_subject_check CHECK ('
            ."(action IN ('prescription.created', 'controlled_drug.authorization_failed') AND subject_type = 'prescription')"
            ." OR (action IN ('dispensation.created', 'controlled_drug.authorized') AND subject_type = 'dispensation'))"
        );
        // Solo ids en el detalle: un correo, un nombre o una contraseña no caben (RN-10).
        DB::statement(
            'ALTER TABLE audit_events ADD CONSTRAINT audit_events_details_ids_only CHECK ('
            ."jsonb_typeof(details) = 'object' AND NOT jsonb_path_exists(details, 'strict \$.* ? (@.type() != \"number\")'))"
        );

        // OR REPLACE: migrate:fresh borra tablas (y sus triggers) pero no funciones.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION reject_append_only_mutation() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION '% is append-only: % rejected', TG_TABLE_NAME, TG_OP;
            END;
            $$;

            CREATE TRIGGER patient_access_logs_append_only
                BEFORE UPDATE OR DELETE OR TRUNCATE ON patient_access_logs
                FOR EACH STATEMENT EXECUTE FUNCTION reject_append_only_mutation();
            ALTER TABLE patient_access_logs ENABLE ALWAYS TRIGGER patient_access_logs_append_only;

            CREATE TRIGGER audit_events_append_only
                BEFORE UPDATE OR DELETE OR TRUNCATE ON audit_events
                FOR EACH STATEMENT EXECUTE FUNCTION reject_append_only_mutation();
            ALTER TABLE audit_events ENABLE ALWAYS TRIGGER audit_events_append_only;
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS audit_events_append_only ON audit_events');
        DB::statement('DROP TRIGGER IF EXISTS patient_access_logs_append_only ON patient_access_logs');
        DB::statement('DROP FUNCTION IF EXISTS reject_append_only_mutation()');
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('patient_access_logs');
    }
};
