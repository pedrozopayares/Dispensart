<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

// audit-trail "Ingresos de lote registrados" (S18 D4): down() de la migración con filas stock.received ya escritas.
// Retroceso real con filas confirmadas: nada de RefreshDatabase.

uses(DatabaseMigrations::class);

test('Filas stock.received sobreviven al retroceso de la migración y la acción vuelve a admitirse al reaplicarla', function () {
    $regente = User::factory()->regente()->create();
    $row = ['actor_id' => $regente->id, 'action' => 'stock.received', 'subject_type' => 'kardex_movement', 'subject_id' => 42, 'details' => '{}'];
    DB::table('audit_events')->insert($row);
    $before = DB::table('audit_events')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

    $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful()->run();

    expect(DB::table('audit_events')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all())->toBe($before);
    expectRejectedByDatabase(fn () => DB::table('audit_events')->insert($row), '23514');

    $this->artisan('migrate')->assertSuccessful()->run();

    DB::table('audit_events')->insert($row);
    expect(DB::table('audit_events')->where('action', 'stock.received')->count())->toBe(2);
});
