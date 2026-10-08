<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Kardex de solo inserción (RN-06, design D4/D5, Data impact fila 3). Cada movimiento pertenece a una
| existencia (FK compuesta) y guarda su saldo resultante. Los literales de tipo se escriben aquí y no desde
| App\Enums\MovementType: una migración es una foto del esquema. La fecha la fija la base.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kardex_movements', function (Blueprint $table): void {
            $table->id()->generatedAs();
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('lot_id');
            $table->string('type', 32);
            $table->integer('quantity');
            $table->integer('balance_after');
            $table->string('reason', 500)->nullable();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users', indexName: 'kardex_movements_user_id_foreign')
                ->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->foreign(['warehouse_id', 'product_id', 'lot_id'], 'kardex_movements_stock_foreign')
                ->references(['warehouse_id', 'product_id', 'lot_id'])
                ->on('stocks')
                ->restrictOnDelete();
            $table->index(['warehouse_id', 'product_id', 'lot_id', 'created_at'], 'kardex_movements_stock_created_at_index');
            $table->index('product_id', 'kardex_movements_product_id_index');
            $table->index('lot_id', 'kardex_movements_lot_id_index');
            $table->index(['created_at', 'id'], 'kardex_movements_created_at_id_index');
            $table->index('user_id', 'kardex_movements_user_id_index');
        });

        DB::statement(
            'ALTER TABLE kardex_movements ADD CONSTRAINT kardex_movements_type_check CHECK (type IN '
            ."('entrada', 'salida_dispensacion', 'salida_traslado', 'entrada_traslado', 'ajuste'))"
        );
        // Entradas suman, salidas restan, el ajuste puede ir en ambos sentidos; ninguno vale 0.
        DB::statement(
            'ALTER TABLE kardex_movements ADD CONSTRAINT kardex_movements_quantity_sign_check CHECK ('
            .'quantity <> 0'
            ." AND (type NOT IN ('entrada', 'entrada_traslado') OR quantity > 0)"
            ." AND (type NOT IN ('salida_dispensacion', 'salida_traslado') OR quantity < 0))"
        );
        DB::statement(
            'ALTER TABLE kardex_movements ADD CONSTRAINT kardex_movements_balance_non_negative CHECK (balance_after >= 0)'
        );
        DB::statement(
            'ALTER TABLE kardex_movements ADD CONSTRAINT kardex_movements_adjustment_attribution_check CHECK ('
            ."type <> 'ajuste' OR (user_id IS NOT NULL AND btrim(coalesce(reason, '')) <> ''))"
        );

        // Inmutabilidad (design D5): un trigger de sentencia rechaza UPDATE, DELETE y TRUNCATE de cualquier
        // rol, también del dueño de la tabla. ENABLE ALWAYS: dispara aun con session_replication_role = replica.
        // OR REPLACE: migrate:fresh borra tablas (y con ellas el trigger) pero no funciones.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION kardex_movements_reject_mutation() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'kardex_movements is append-only: % rejected', TG_OP;
            END;
            $$;

            CREATE TRIGGER kardex_movements_append_only
                BEFORE UPDATE OR DELETE OR TRUNCATE ON kardex_movements
                FOR EACH STATEMENT EXECUTE FUNCTION kardex_movements_reject_mutation();

            ALTER TABLE kardex_movements ENABLE ALWAYS TRIGGER kardex_movements_append_only;
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS kardex_movements_append_only ON kardex_movements');
        DB::statement('DROP FUNCTION IF EXISTS kardex_movements_reject_mutation()');
        Schema::dropIfExists('kardex_movements');
    }
};
