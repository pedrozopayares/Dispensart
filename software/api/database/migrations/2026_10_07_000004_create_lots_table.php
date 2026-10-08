<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Lotes: pertenecen a un producto existente, siempre con vencimiento (RN-01). El único
| (product_id, lot_code) cubre también el índice de la FK. Borrar un producto con lotes se rechaza.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products', indexName: 'lots_product_id_foreign')
                ->restrictOnDelete();
            $table->string('lot_code', 50);
            $table->date('expires_on');
            $table->timestampsTz();

            $table->unique(['product_id', 'lot_code'], 'lots_product_id_lot_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};
