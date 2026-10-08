<?php

use App\Models\AuditEvent;
use App\Models\Patient;
use App\Models\PatientAccessLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// audit-trail "Bitácoras de solo inserción": sentencias directas con el usuario de base de la aplicación.

beforeEach(function () {
    $this->user = User::factory()->auxiliar()->create();
    $this->patient = Patient::factory()->create();

    DB::table('patient_access_logs')->insert([
        'user_id' => $this->user->id, 'patient_id' => $this->patient->id, 'action' => 'view',
        'route' => '/api/patients/{patient}', 'correlation_id' => 'traza-bitacora',
    ]);
    DB::table('audit_events')->insert([
        'actor_id' => $this->user->id, 'action' => 'prescription.created', 'subject_type' => 'prescription',
        'subject_id' => 7, 'details' => json_encode(['warehouse_id' => 3]), 'correlation_id' => 'traza-bitacora',
    ]);
});

/**
 * @return array<string, list<array<string, mixed>>>
 */
function auditSnapshot(): array
{
    return [
        'patient_access_logs' => DB::table('patient_access_logs')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
        'audit_events' => DB::table('audit_events')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
    ];
}

it('admite la inserción directa en ambas bitácoras con fecha puesta por la base', function () {
    expect(PatientAccessLog::sole()->created_at)->not->toBeNull()
        ->and(AuditEvent::sole()->created_at)->not->toBeNull()
        ->and(AuditEvent::sole()->details)->toBe(['warehouse_id' => 3]);
});

it('rechaza en la base un UPDATE sobre cada bitácora y las filas conservan sus valores', function (string $table, array $change) {
    $before = auditSnapshot();

    expectRejectedByDatabase(fn () => DB::table($table)->update($change), 'P0001');

    expect(auditSnapshot())->toBe($before);
})->with([
    'acceso a pacientes' => ['patient_access_logs', ['route' => '/otra']],
    'operaciones sensibles' => ['audit_events', ['subject_id' => 8]],
]);

it('rechaza en la base un DELETE o un TRUNCATE sobre cada bitácora y el número de filas no cambia', function (string $table, string $statement) {
    expectRejectedByDatabase(fn () => DB::statement(sprintf($statement, $table)), 'P0001');

    expect(DB::table($table)->count())->toBe(1);
})->with([
    'DELETE acceso' => ['patient_access_logs', 'DELETE FROM %s'],
    'TRUNCATE acceso' => ['patient_access_logs', 'TRUNCATE %s'],
    'DELETE operaciones' => ['audit_events', 'DELETE FROM %s'],
    'TRUNCATE operaciones' => ['audit_events', 'TRUNCATE %s'],
]);

it('rechaza en la base un detalle con texto, una acción desconocida o una acción sobre otro tipo de objeto', function (array $overrides, string $constraint) {
    expectRejectedByDatabase(fn () => DB::table('audit_events')->insert([
        'actor_id' => test()->user->id, 'action' => 'dispensation.created', 'subject_type' => 'dispensation',
        'subject_id' => 1, ...$overrides,
    ]), '23514', $constraint);
})->with([
    'correo en el detalle' => [['details' => json_encode(['authorizer' => 'regente@dispensart.test'])], 'audit_events_details_ids_only'],
    'detalle que no es objeto' => [['details' => json_encode([1, 2])], 'audit_events_details_ids_only'],
    'acción desconocida' => [['action' => 'patient.deleted'], 'audit_events_action_check'],
    'dispensación sobre prescripción' => [['subject_type' => 'prescription'], 'audit_events_action_subject_check'],
]);
