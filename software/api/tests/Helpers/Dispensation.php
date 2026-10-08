<?php

use App\Models\AuditEvent;
use App\Models\Dispensation;
use App\Models\DispensationLine;
use App\Models\IdempotencyKey;
use App\Models\KardexMovement;
use App\Models\Lot;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Product;
use App\Models\Stock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

// Ayudas de las pruebas de pacientes, prescripciones y dispensación (S3).

/**
 * Existencia de un lote nuevo del producto en la bodega, que vence en `$days` días desde hoy en Bogotá.
 * Nace con su movimiento `entrada` (StockFactory).
 */
function lotStock(Warehouse $warehouse, Product $product, int $days, int $quantity): Stock
{
    $lot = Lot::factory()->expiringInDays($days)->create(['product_id' => $product->id]);

    return stockOf($quantity, ['warehouse_id' => $warehouse->id, 'lot_id' => $lot->id]);
}

/**
 * Prescripción vigente (o con los atributos dados) con un ítem por [producto, prescrita, dispensada].
 *
 * @param  list<array{0: Product, 1: int, 2?: int}>  $items
 * @param  array<string, mixed>  $attributes
 */
function prescriptionWith(array $items, array $attributes = []): Prescription
{
    $prescription = Prescription::factory()->create($attributes);
    foreach ($items as $item) {
        PrescriptionItem::factory()->create([
            'prescription_id' => $prescription->id,
            'product_id' => $item[0]->id,
            'prescribed_quantity' => $item[1],
            'dispensed_quantity' => $item[2] ?? 0,
        ]);
    }

    return $prescription->load('items');
}

/**
 * Ítem de la prescripción para un producto.
 */
function itemOf(Prescription $prescription, Product $product): PrescriptionItem
{
    return PrescriptionItem::query()
        ->where('prescription_id', $prescription->id)
        ->where('product_id', $product->id)
        ->sole();
}

/**
 * Cuerpo de vista previa / dispensación: cantidades por producto de la prescripción, en el orden dado.
 *
 * @param  list<array{0: Product, 1: int}>  $quantities
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function dispensationBody(Prescription $prescription, Warehouse $warehouse, array $quantities, array $extra = []): array
{
    return [
        'prescription_id' => $prescription->id,
        'warehouse_id' => $warehouse->id,
        'items' => array_map(
            fn (array $q): array => ['prescription_item_id' => itemOf($prescription, $q[0])->id, 'quantity' => $q[1]],
            $quantities,
        ),
        ...$extra,
    ];
}

function newIdempotencyKey(): string
{
    return 'clave-'.Str::random(24);
}

/**
 * POST /api/dispensations como el usuario dado (actingAs), con la clave dada o una nueva.
 *
 * @param  array<string, mixed>  $body
 */
function dispense(User $user, array $body, ?string $key = null): TestResponse
{
    return test()->actingAs($user)->postJson('/api/dispensations', $body, ['Idempotency-Key' => $key ?? newIdempotencyKey()]);
}

/**
 * Foto de todo lo que una dispensación puede cambiar: "nada cambia" = misma foto antes y después.
 *
 * @return array<string, mixed>
 */
function dispensationState(): array
{
    return [
        'stocks' => Stock::query()->orderBy('id')->pluck('quantity', 'id')->all(),
        'kardex' => KardexMovement::count(),
        'items' => PrescriptionItem::query()->orderBy('id')->pluck('dispensed_quantity', 'id')->all(),
        'dispensations' => Dispensation::count(),
        'lines' => DispensationLine::count(),
        'idempotency_keys' => IdempotencyKey::count(),
        'operations' => AuditEvent::query()
            ->whereIn('action', ['dispensation.created', 'controlled_drug.authorized'])
            ->count(),
    ];
}
