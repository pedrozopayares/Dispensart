<?php

use Illuminate\Support\Facades\DB;

// CI › Pruebas contra PostgreSQL: la suite usa pgsql sobre la base de pruebas, nunca SQLite.
it('corre las pruebas contra PostgreSQL en la base de pruebas', function () {
    $connection = DB::connection();

    expect($connection->getDriverName())->toBe('pgsql')
        ->and($connection->getDatabaseName())->toBe('dispensart_test')
        ->and($connection->selectOne('select current_database() as name')->name)->toBe('dispensart_test')
        ->and($connection->selectOne('select version() as v')->v)->toStartWith('PostgreSQL 16');
});
