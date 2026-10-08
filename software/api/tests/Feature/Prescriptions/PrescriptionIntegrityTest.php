<?php

use App\Models\PrescriptionItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// prescriptions "Saldo acumulado por ítem": la base impide salir de 0 ≤ dispensada ≤ prescrita y prescrita < 1.

it('rechaza en la base una dispensada mayor que la prescrita y el ítem conserva su valor', function () {
    $item = prescriptionWith([[Product::factory()->create(), 10, 4]])->items->sole();

    expectRejectedByDatabase(
        fn () => DB::table('prescription_items')->where('id', $item->id)->update(['dispensed_quantity' => 11]),
        '23514',
        'prescription_items_dispensed_range',
    );
    expect($item->fresh()?->dispensed_quantity)->toBe(4);
});

it('rechaza en la base una dispensada negativa o una prescrita cero', function (string $column, int $value, string $constraint) {
    $product = Product::factory()->create();
    $prescription = prescriptionWith([]);

    expectRejectedByDatabase(
        fn () => DB::table('prescription_items')->insert([
            'prescription_id' => $prescription->id, 'product_id' => $product->id,
            'prescribed_quantity' => 10, 'dispensed_quantity' => 0, $column => $value,
        ]),
        '23514',
        $constraint,
    );
    expect(PrescriptionItem::count())->toBe(0);
})->with([
    'dispensada -1' => ['dispensed_quantity', -1, 'prescription_items_dispensed_range'],
    'prescrita 0' => ['prescribed_quantity', 0, 'prescription_items_prescribed_positive'],
]);

it('rechaza en la base un producto repetido en la misma prescripción', function () {
    $product = Product::factory()->create();
    $prescription = prescriptionWith([[$product, 5]]);

    expectRejectedByDatabase(
        fn () => DB::table('prescription_items')->insert([
            'prescription_id' => $prescription->id, 'product_id' => $product->id, 'prescribed_quantity' => 3,
        ]),
        '23505',
        'prescription_items_prescription_product_unique',
    );
});
