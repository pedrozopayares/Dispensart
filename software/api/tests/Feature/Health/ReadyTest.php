<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('responde 200 ready con base disponible y todas las migraciones aplicadas', function () {
    $this->get('/ready')
        ->assertOk()
        ->assertExactJson(['status' => 'ready', 'checks' => ['database' => 'ok', 'migrations' => 'ok']]);
});

it('responde 503 con migrations pending si existe una migración sin aplicar', function () {
    $directory = sys_get_temp_dir().'/dispensart-pending-'.bin2hex(random_bytes(4));
    mkdir($directory);
    file_put_contents($directory.'/2999_01_01_000000_pending_probe.php', <<<'PHP'
        <?php

        use Illuminate\Database\Migrations\Migration;

        return new class extends Migration
        {
            public function up(): void {}

            public function down(): void {}
        };
        PHP);

    try {
        app('migrator')->path($directory);

        $this->get('/ready')
            ->assertStatus(503)
            ->assertExactJson(['status' => 'not_ready', 'checks' => ['database' => 'ok', 'migrations' => 'pending']]);
    } finally {
        array_map('unlink', glob($directory.'/*.php') ?: []);
        rmdir($directory);
    }
});
