<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Rol único por usuario (identity-access "Un rol por usuario"). Sin valor por defecto: un default
| concedería capacidades en silencio. Los literales se escriben aquí y no desde App\Enums\Role:
| una migración es una foto del esquema (design Data impact).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 32);
        });

        DB::statement(
            'ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN '
            ."('auxiliar_farmacia', 'regente_farmacia', 'medico', 'auditor', 'admin'))"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('role');
        });
    }
};
