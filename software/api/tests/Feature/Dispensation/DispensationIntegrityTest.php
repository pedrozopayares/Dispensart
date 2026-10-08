<?php

use App\Models\Dispensation;
use App\Models\Patient;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// Defensas de base de dispensaciones, líneas e idempotencia (design Data impact 4–6, tarea 1.3): sentencias
// directas que ninguna ruta produce, porque solo la acción escribe estas tablas.

beforeEach(function () {
    $this->product = Product::factory()->create();
    $this->prescription = prescriptionWith([[$this->product, 10]]);
    $this->warehouse = Warehouse::factory()->create();
    $this->dispenser = User::factory()->regente()->create();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function dispensationRow(array $overrides = []): array
{
    $test = test();

    return [
        'prescription_id' => $test->prescription->id,
        'patient_id' => $test->prescription->patient_id,
        'warehouse_id' => $test->warehouse->id,
        'dispensed_by' => $test->dispenser->id,
        ...$overrides,
    ];
}

it('acepta una dispensación coherente con fecha puesta por la base', function () {
    $id = DB::table('dispensations')->insertGetId(dispensationRow());

    expect(Dispensation::findOrFail($id)->created_at)->not->toBeNull();
});

it('rechaza en la base una dispensación cuyo paciente no es el de la prescripción', function () {
    expectRejectedByDatabase(
        fn () => DB::table('dispensations')->insert(dispensationRow(['patient_id' => Patient::factory()->create()->id])),
        '23503',
        'dispensations_prescription_patient_foreign',
    );
});

it('rechaza en la base un autorizador igual al dispensador', function () {
    expectRejectedByDatabase(
        fn () => DB::table('dispensations')->insert(dispensationRow(['authorized_by' => $this->dispenser->id])),
        '23514',
        'dispensations_authorizer_differs',
    );
});

describe('líneas', function () {
    beforeEach(function () {
        $this->stock = lotStock($this->warehouse, $this->product, 30, 10);
        $this->dispensationId = DB::table('dispensations')->insertGetId(dispensationRow());
        $this->movementId = movementsOf($this->stock)->sole()->id;
    });

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function lineRow(array $overrides = []): array
    {
        $test = test();

        return [
            'dispensation_id' => $test->dispensationId,
            'prescription_id' => $test->prescription->id,
            'prescription_item_id' => $test->prescription->items->sole()->id,
            'product_id' => $test->product->id,
            'lot_id' => $test->stock->lot_id,
            'quantity' => 1,
            'kardex_movement_id' => $test->movementId,
            ...$overrides,
        ];
    }

    it('acepta una línea coherente', function () {
        DB::table('dispensation_lines')->insert(lineRow());

        expect(DB::table('dispensation_lines')->count())->toBe(1);
    });

    it('rechaza en la base una línea incoherente', function (Closure $overrides, string $sqlState, string $constraint) {
        expectRejectedByDatabase(
            fn () => DB::table('dispensation_lines')->insert(lineRow($overrides())),
            $sqlState,
            $constraint,
        );
    })->with([
        'cantidad 0' => [fn () => ['quantity' => 0], '23514', 'dispensation_lines_quantity_positive'],
        'lote de otro producto' => [fn () => ['lot_id' => lotStock(test()->warehouse, Product::factory()->create(), 30, 1)->lot_id], '23503', 'dispensation_lines_lot_product_foreign'],
        'ítem de otra prescripción' => [fn () => ['prescription_item_id' => prescriptionWith([[test()->product, 3]])->items->sole()->id], '23503', 'dispensation_lines_item_foreign'],
        'prescripción distinta de la dispensación' => [fn () => ['prescription_id' => prescriptionWith([[test()->product, 3]])->id], '23503', 'dispensation_lines_dispensation_prescription_foreign'],
    ]);

    it('rechaza en la base dos líneas sobre el mismo movimiento del kardex', function () {
        DB::table('dispensation_lines')->insert(lineRow());
        $other = DB::table('dispensations')->insertGetId(dispensationRow());

        expectRejectedByDatabase(
            fn () => DB::table('dispensation_lines')->insert(lineRow(['dispensation_id' => $other])),
            '23505',
            'dispensation_lines_kardex_movement_unique',
        );
    });
});

describe('registros de idempotencia', function () {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function idempotencyRow(array $overrides = []): array
    {
        return [
            'user_id' => test()->dispenser->id,
            'key' => 'clave-valida-0123456789',
            'request_hash' => hash('sha256', 'cuerpo'),
            'response_status' => 201,
            'response_body' => '{"data":{}}',
            ...$overrides,
        ];
    }

    it('rechaza en la base una clave repetida del mismo usuario y admite la de otro usuario', function () {
        DB::table('idempotency_keys')->insert(idempotencyRow());

        expectRejectedByDatabase(fn () => DB::table('idempotency_keys')->insert(idempotencyRow()), '23505', 'idempotency_keys_user_key_unique');

        DB::table('idempotency_keys')->insert(idempotencyRow(['user_id' => User::factory()->auxiliar()->create()->id]));
        expect(DB::table('idempotency_keys')->count())->toBe(2);
    });

    it('rechaza en la base una clave, huella o estado fuera de formato', function (array $overrides, string $constraint) {
        expectRejectedByDatabase(fn () => DB::table('idempotency_keys')->insert(idempotencyRow($overrides)), '23514', $constraint);
    })->with([
        'clave de 10 caracteres' => [['key' => 'corta-1234'], 'idempotency_keys_key_format'],
        'clave con espacios' => [['key' => 'clave con espacios 123'], 'idempotency_keys_key_format'],
        'huella no hexadecimal' => [['request_hash' => str_repeat('z', 64)], 'idempotency_keys_request_hash_format'],
        'estado 409' => [['response_status' => 409], 'idempotency_keys_response_status_2xx'],
    ]);
});
