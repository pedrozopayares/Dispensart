<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Bodegas: código y nombre únicos en la base (catalog "Integridad del catálogo en la base de datos").
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique('warehouses_code_unique');
            $table->string('name', 120)->unique('warehouses_name_unique');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
