<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// audit-trail "Integridad de las acciones de la bitácora en la base" (S10): sentencias directas con el
// usuario de base de la aplicación, sin pasar por la API. Los nombres de restricción son deterministas:
// PostgreSQL evalúa los CHECK de una tabla en orden alfabético de nombre (design D8).

beforeEach(function () {
    $this->actor = User::factory()->admin()->create();
    $this->subject = User::factory()->auxiliar()->create();
    // Movimiento `entrada` real de una existencia de fábrica: objeto de stock.adjusted.
    $this->movementId = movementsOf(stockOf(10))->sole()->id;
});

/**
 * Inserta una fila directa en audit_events y devuelve su id.
 *
 * @param  array<string, mixed>  $overrides
 */
function insertAuditRow(string $action, string $subjectType, int $subjectId, array $overrides = []): int
{
    return (int) DB::table('audit_events')->insertGetId([
        'actor_id' => test()->actor->id, 'action' => $action, 'subject_type' => $subjectType,
        'subject_id' => $subjectId, 'details' => '{}', 'correlation_id' => 'traza-s10', ...$overrides,
    ]);
}

/**
 * @return list<array<string, mixed>>
 */
function auditRows(): array
{
    return DB::table('audit_events')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
}

test('Acciones nuevas admitidas: user.created sobre un usuario y stock.adjusted sobre un movimiento de kardex', function () {
    insertAuditRow('user.created', 'user', $this->subject->id);
    insertAuditRow('stock.adjusted', 'kardex_movement', $this->movementId, ['details' => json_encode(['warehouse_id' => 1, 'lot_id' => 2])]);

    expect(DB::table('audit_events')->orderBy('id')->get(['action', 'subject_type', 'subject_id'])->map(fn ($r) => (array) $r)->all())
        ->toBe([
            ['action' => 'user.created', 'subject_type' => 'user', 'subject_id' => $this->subject->id],
            ['action' => 'stock.adjusted', 'subject_type' => 'kardex_movement', 'subject_id' => $this->movementId],
        ]);
});

test('Acciones vigentes siguen admitidas: cada acción sobre su tipo de objeto', function (string $action, string $subjectType) {
    insertAuditRow($action, $subjectType, 7);

    expect(DB::table('audit_events')->where('action', $action)->where('subject_type', $subjectType)->count())->toBe(1);
})->with([
    'prescription.created' => ['prescription.created', 'prescription'],
    'dispensation.created' => ['dispensation.created', 'dispensation'],
    'controlled_drug.authorized' => ['controlled_drug.authorized', 'dispensation'],
    'controlled_drug.authorization_failed' => ['controlled_drug.authorization_failed', 'prescription'],
    'transfer.approved' => ['transfer.approved', 'transfer'],
    'transfer.voided' => ['transfer.voided', 'transfer'],
    'transfer.discrepancy_resolved' => ['transfer.discrepancy_resolved', 'transfer'],
]);

test('Emparejamiento cruzado rechazado por la base: cada acción nueva sobre el tipo de la otra', function (string $action, string $subjectType) {
    expectRejectedByDatabase(fn () => insertAuditRow($action, $subjectType, 7), '23514', 'audit_events_action_subject_check');

    expect(DB::table('audit_events')->count())->toBe(0);
})->with([
    'user.created sobre kardex_movement' => ['user.created', 'kardex_movement'],
    'stock.adjusted sobre user' => ['stock.adjusted', 'user'],
]);

test('Acción fuera del conjunto rechazada por la base: user.role_changed', function () {
    expectRejectedByDatabase(fn () => insertAuditRow('user.role_changed', 'user', $this->subject->id), '23514', 'audit_events_action_check');

    expect(DB::table('audit_events')->count())->toBe(0);
});

test('Detalle con texto rechazado por la base: correo en el detalle de user.created', function () {
    expectRejectedByDatabase(
        fn () => insertAuditRow('user.created', 'user', $this->subject->id, ['details' => json_encode(['email' => 'nueva.aux@dispensart.test'])]),
        '23514',
        'audit_events_details_ids_only',
    );

    expect(DB::table('audit_events')->count())->toBe(0);
});

test('Filas nuevas inmutables: UPDATE, DELETE y TRUNCATE rechazados con las dos acciones nuevas presentes', function (Closure $statement) {
    insertAuditRow('user.created', 'user', $this->subject->id);
    insertAuditRow('stock.adjusted', 'kardex_movement', $this->movementId, ['details' => json_encode(['warehouse_id' => 1, 'lot_id' => 2])]);
    $before = auditRows();

    expectRejectedByDatabase($statement, 'P0001');

    expect(auditRows())->toBe($before)->toHaveCount(2);
})->with([
    'UPDATE' => [fn () => DB::table('audit_events')->update(['subject_id' => 999])],
    'DELETE' => [fn () => DB::statement('DELETE FROM audit_events')],
    'TRUNCATE' => [fn () => DB::statement('TRUNCATE audit_events')],
]);
