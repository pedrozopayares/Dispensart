<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
| Fecha del movimiento = momento de escribirlo (deuda D-auv-2). CURRENT_TIMESTAMP es el inicio de la
| transacción: dos transacciones solapadas que bloquean la misma existencia en orden inverso a su inicio
| fechaban el segundo movimiento antes que el primero, y GET /api/kardex (fecha DESC, id DESC) listaba la
| historia fuera del orden de balance_after. clock_timestamp() se evalúa en el INSERT, ya con la fila bloqueada.
*/
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE kardex_movements ALTER COLUMN created_at SET DEFAULT clock_timestamp()');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE kardex_movements ALTER COLUMN created_at SET DEFAULT CURRENT_TIMESTAMP');
    }
};
