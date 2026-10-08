<?php

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// dispensation "Fallo a mitad de la transacción" (RN-03): la segunda línea de una dispensación de dos lotes
// falla por un trigger temporal creado dentro de la transacción de la prueba (sin costura en producción, D10).

it('revierte todo y responde 500 si falla la escritura de la segunda línea', function () {
    $auxiliar = User::factory()->auxiliar()->create();
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create();
    lotStock($warehouse, $product, 10, 3);
    $second = lotStock($warehouse, $product, 40, 10);
    $prescription = prescriptionWith([[$product, 10]]);
    DB::unprepared(sprintf(<<<'SQL'
        CREATE FUNCTION test_fail_second_line() RETURNS trigger LANGUAGE plpgsql AS $$
        BEGIN
            IF NEW.lot_id = %d THEN RAISE EXCEPTION 'fallo forzado de la segunda línea'; END IF;
            RETURN NEW;
        END; $$;
        CREATE TRIGGER test_fail_second_line BEFORE INSERT ON dispensation_lines
            FOR EACH ROW EXECUTE FUNCTION test_fail_second_line();
        SQL, $second->lot_id));
    $before = dispensationState();

    $response = dispense($auxiliar, dispensationBody($prescription, $warehouse, [[$product, 5]]));

    $response->assertStatus(500)->assertExactJson(['code' => 'server_error', 'message' => __('errors.server_error')]);
    expect(dispensationState())->toBe($before)
        ->and($before['dispensations'])->toBe(0)
        ->and($before['idempotency_keys'])->toBe(0);

    // Control positivo: sin el trigger, la misma petición dispensa las dos líneas.
    DB::unprepared('DROP TRIGGER test_fail_second_line ON dispensation_lines');
    dispense($auxiliar, dispensationBody($prescription, $warehouse, [[$product, 5]]))->assertCreated()->assertJsonCount(2, 'data.lines');
});
