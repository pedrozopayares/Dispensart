<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
| Clave candidata (id, product_id) en lotes: destino de la FK compuesta de existencias, que así exige
| que el producto de la existencia sea el de su lote (design D3). Migración propia de S2: no edita la de S1.
*/
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE lots ADD CONSTRAINT lots_id_product_id_unique UNIQUE (id, product_id)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE lots DROP CONSTRAINT IF EXISTS lots_id_product_id_unique');
    }
};
