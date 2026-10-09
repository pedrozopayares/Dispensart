<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

// audit-trail "Integridad de las acciones de la bitácora en la base" (S10): comportamiento declarado del
// down() de la migración (design D5). Retroceso real con filas nuevas confirmadas: nada de RefreshDatabase.
// DatabaseMigrations migra al entrar y hace rollback de todo al salir, otra vez con las filas presentes.

uses(DatabaseMigrations::class);

const SENSITIVE_OPERATION_CHECKS = ['audit_events_action_check', 'audit_events_action_subject_check', 'audit_events_subject_type_check'];

/**
 * `convalidated` de los tres CHECK reemplazados, por nombre.
 *
 * @return array<string, bool>
 */
function sensitiveOperationChecksValidated(): array
{
    return DB::table('pg_constraint')
        ->whereRaw("conrelid = 'audit_events'::regclass")
        ->whereIn('conname', SENSITIVE_OPERATION_CHECKS)
        ->orderBy('conname')
        ->pluck('convalidated', 'conname')
        ->all();
}

test('Filas nuevas inmutables: sobreviven al retroceso de la migración y se revalidan', function () {
    $admin = User::factory()->admin()->create();
    $created = User::factory()->auxiliar()->create();
    $row = fn (string $action, string $type, int $id): array => [
        'actor_id' => $admin->id, 'action' => $action, 'subject_type' => $type, 'subject_id' => $id, 'details' => '{}',
    ];
    DB::table('audit_events')->insert($row('user.created', 'user', $created->id));
    DB::table('audit_events')->insert($row('stock.adjusted', 'kardex_movement', 42));
    $before = DB::table('audit_events')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    expect(sensitiveOperationChecksValidated())->toBe(array_fill_keys(SENSITIVE_OPERATION_CHECKS, true));

    $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful()->run();

    expect(DB::table('audit_events')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all())->toBe($before)
        ->and(sensitiveOperationChecksValidated())->toBe(array_fill_keys(SENSITIVE_OPERATION_CHECKS, false));
    expectRejectedByDatabase(fn () => DB::table('audit_events')->insert($row('user.created', 'user', $admin->id)), '23514');

    $this->artisan('migrate')->assertSuccessful()->run();

    expect(sensitiveOperationChecksValidated())->toBe(array_fill_keys(SENSITIVE_OPERATION_CHECKS, true))
        ->and(DB::table('audit_events')->count())->toBe(2);
});
