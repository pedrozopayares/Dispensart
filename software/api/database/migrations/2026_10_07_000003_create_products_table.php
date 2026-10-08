<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Productos: código único; is_controlled marca los de control especial (RN-05).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique('products_code_unique');
            $table->string('name', 150);
            $table->string('presentation', 150)->nullable();
            $table->boolean('is_controlled')->default(false);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
