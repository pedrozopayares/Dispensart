<?php

use App\Models\AuditEvent;
use App\Models\Lot;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// transfers "Integridad del traslado en la base de datos" (design D3) y audit-trail ampliada (design D12):
// sentencias directas, sin pasar por la API. Cada rechazo afirma SQLSTATE y nombre de la restricción.

beforeEach(function () {
    $this->creator = User::factory()->auxiliar()->create();
    $this->regente = User::factory()->regente()->create();
    $this->origin = Warehouse::factory()->create();
    $this->destination = Warehouse::factory()->create();
    $this->lot = Lot::factory()->create();
});

/**
 * @param  array<string, mixed>  $overrides
 */
function insertTransfer(array $overrides = []): int
{
    return (int) DB::table('transfers')->insertGetId([
        'origin_warehouse_id' => test()->origin->id,
        'destination_warehouse_id' => test()->destination->id,
        'status' => 'BORRADOR',
        'created_by' => test()->creator->id,
        'created_at' => now(),
        'updated_at' => now(),
        ...$overrides,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function insertTransferLine(int $transferId, array $overrides = []): int
{
    return (int) DB::table('transfer_lines')->insertGetId([
        'transfer_id' => $transferId,
        'lot_id' => test()->lot->id,
        'product_id' => test()->lot->product_id,
        'quantity' => 3,
        ...$overrides,
    ]);
}

it('guarda un traslado BORRADOR entre dos bodegas distintas con una línea de cantidad 3', function () {
    $id = insertTransfer();
    insertTransferLine($id);

    expect(DB::table('transfers')->where('id', $id)->value('status'))->toBe('BORRADOR')
        ->and(DB::table('transfer_lines')->where('transfer_id', $id)->value('quantity'))->toBe(3);
});

it('rechaza un estado fuera de los 7 de RN-07 y conserva el anterior', function () {
    $id = insertTransfer();

    expectRejectedByDatabase(
        fn () => DB::table('transfers')->where('id', $id)->update(['status' => 'PERDIDO']), '23514', 'transfers_status_check',
    );

    expect(DB::table('transfers')->where('id', $id)->value('status'))->toBe('BORRADOR');
});

it('rechaza origen igual a destino', function () {
    expectRejectedByDatabase(
        fn () => insertTransfer(['destination_warehouse_id' => test()->origin->id]), '23514', 'transfers_distinct_warehouses',
    );

    expect(DB::table('transfers')->count())->toBe(0);
});

it('rechaza como aprobador al mismo usuario que solicitó y el aprobador sigue nulo', function () {
    $id = insertTransfer(['status' => 'SOLICITADO', 'requested_by' => test()->creator->id, 'requested_at' => now()]);

    expectRejectedByDatabase(
        fn () => DB::table('transfers')->where('id', $id)->update(['approved_by' => test()->creator->id]),
        '23514', 'transfers_approver_differs',
    );

    expect(DB::table('transfers')->where('id', $id)->value('approved_by'))->toBeNull();
});

it('admite como aprobador a otro usuario', function () {
    $id = insertTransfer(['status' => 'SOLICITADO', 'requested_by' => test()->creator->id]);

    DB::table('transfers')->where('id', $id)->update(['status' => 'APROBADO', 'approved_by' => test()->regente->id]);

    expect(DB::table('transfers')->where('id', $id)->value('approved_by'))->toBe(test()->regente->id);
});

it('rechaza un solicitante distinto del creador', function () {
    expectRejectedByDatabase(
        fn () => insertTransfer(['status' => 'SOLICITADO', 'requested_by' => test()->regente->id]),
        '23514', 'transfers_requester_is_creator',
    );
});

it('rechaza cantidades fuera de rango en líneas y discrepancias', function (Closure $write, string $constraint) {
    $id = insertTransfer();

    expectRejectedByDatabase(fn () => $write($id), '23514', $constraint);
})->with([
    'línea con cantidad 0' => [fn (int $id) => insertTransferLine($id, ['quantity' => 0]), 'transfer_lines_quantity_positive'],
    'recibida -1' => [fn (int $id) => insertTransferLine($id, ['received_quantity' => -1]), 'transfer_lines_received_range'],
    'recibida 4 sobre 3' => [fn (int $id) => insertTransferLine($id, ['received_quantity' => 4]), 'transfer_lines_received_range'],
    'discrepancia con faltante 0' => [fn (int $id) => DB::table('transfer_discrepancies')->insert([
        'transfer_id' => $id, 'transfer_line_id' => insertTransferLine($id), 'shortage' => 0, 'status' => 'pending',
        'created_at' => now(), 'updated_at' => now(),
    ]), 'transfer_discrepancies_shortage_positive'],
]);

it('rechaza el mismo lote dos veces en un traslado', function () {
    $id = insertTransfer();
    insertTransferLine($id);

    expectRejectedByDatabase(fn () => insertTransferLine($id), '23505', 'transfer_lines_transfer_lot_unique');

    expect(DB::table('transfer_lines')->where('transfer_id', $id)->count())->toBe(1);
});

it('rechaza un producto que no es el del lote', function () {
    $id = insertTransfer();
    $other = Lot::factory()->create();

    expectRejectedByDatabase(
        fn () => insertTransferLine($id, ['product_id' => $other->product_id]), '23503', 'transfer_lines_lot_product_foreign',
    );
});

it('rechaza una segunda discrepancia de la misma línea y una de una línea de otro traslado', function () {
    $id = insertTransfer();
    $line = insertTransferLine($id);
    $free = insertTransferLine($id, ['lot_id' => ($other = Lot::factory()->create())->id, 'product_id' => $other->product_id]);
    $row = fn (int $transferId, int $lineId) => [
        'transfer_id' => $transferId, 'transfer_line_id' => $lineId, 'shortage' => 1, 'status' => 'pending',
        'created_at' => now(), 'updated_at' => now(),
    ];
    DB::table('transfer_discrepancies')->insert($row($id, $line));

    expectRejectedByDatabase(fn () => DB::table('transfer_discrepancies')->insert($row($id, $line)), '23505', 'transfer_discrepancies_line_unique');
    expectRejectedByDatabase(fn () => DB::table('transfer_discrepancies')->insert($row(insertTransfer(), $free)), '23503', 'transfer_discrepancies_line_transfer_foreign');
});

it('rechaza una discrepancia resuelta sin datos de resolución o con motivo en blanco', function (bool $withData) {
    $id = insertTransfer();
    $resolution = $withData
        ? ['resolution' => 'written_off', 'resolution_reason' => '   ', 'resolved_by' => test()->regente->id, 'resolved_at' => now()]
        : [];

    expectRejectedByDatabase(fn () => DB::table('transfer_discrepancies')->insert([
        'transfer_id' => $id, 'transfer_line_id' => insertTransferLine($id), 'shortage' => 1, 'status' => 'resolved',
        'created_at' => now(), 'updated_at' => now(), ...$resolution,
    ]), '23514', 'transfer_discrepancies_resolution_coherent');
})->with([
    'sin datos' => [false],
    'motivo en blanco' => [true],
]);

it('admite en la bitácora las 3 acciones de traslado sobre el traslado', function (string $action, array $details) {
    DB::table('audit_events')->insert([
        'actor_id' => test()->regente->id, 'action' => $action, 'subject_type' => 'transfer', 'subject_id' => 1,
        'details' => json_encode((object) $details),
    ]);

    expect(AuditEvent::sole()->action->value)->toBe($action);
})->with([
    'aprobación' => ['transfer.approved', []],
    'anulación' => ['transfer.voided', []],
    'resolución' => ['transfer.discrepancy_resolved', ['discrepancy_id' => 4]],
]);

it('rechaza en la bitácora una acción de traslado sobre otro tipo de objeto', function () {
    expectRejectedByDatabase(fn () => DB::table('audit_events')->insert([
        'actor_id' => test()->regente->id, 'action' => 'transfer.approved', 'subject_type' => 'dispensation', 'subject_id' => 1,
    ]), '23514', 'audit_events_action_subject_check');
});
