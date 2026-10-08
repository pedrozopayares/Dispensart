<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Registros de idempotencia (RN-09, design D5, Data impact fila 6). Alcance por usuario. Solo se guardan
| respuestas exitosas; el cuerpo es `text` (no jsonb, que reordena claves): la repetición es idéntica byte a
| byte. Sin caducidad (proposal § Assumptions 6).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->foreignId('user_id')
                ->constrained('users', indexName: 'idempotency_keys_user_id_foreign')
                ->restrictOnDelete();
            $table->string('key', 128);
            $table->string('request_hash', 64);
            $table->smallInteger('response_status');
            $table->text('response_body');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['user_id', 'key'], 'idempotency_keys_user_key_unique');
        });

        DB::statement("ALTER TABLE idempotency_keys ADD CONSTRAINT idempotency_keys_key_format CHECK (key ~ '^[A-Za-z0-9_-]{16,128}$')");
        DB::statement("ALTER TABLE idempotency_keys ADD CONSTRAINT idempotency_keys_request_hash_format CHECK (request_hash ~ '^[0-9a-f]{64}$')");
        DB::statement('ALTER TABLE idempotency_keys ADD CONSTRAINT idempotency_keys_response_status_2xx CHECK (response_status BETWEEN 200 AND 299)');
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
