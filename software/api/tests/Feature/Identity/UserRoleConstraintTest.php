<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// identity-access "Un rol por usuario": la base defiende el rol aunque la escritura no pase por la API.

function userRow(array $overrides = []): array
{
    return array_merge([
        'name' => 'Usuario Directo',
        'email' => 'directo@dispensart.test',
        'password' => 'hash-ficticio',
        'role' => 'regente_farmacia',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
}

it('guarda un usuario con rol válido insertado directamente', function () {
    DB::table('users')->insert(userRow());

    expect(DB::table('users')->where('email', 'directo@dispensart.test')->value('role'))
        ->toBe('regente_farmacia');
});

it('rechaza en la base un rol fuera del conjunto', function () {
    expectRejectedByDatabase(
        fn () => DB::table('users')->insert(userRow(['role' => 'superusuario'])),
        sqlState: '23514',
        constraint: 'users_role_check',
    );

    expect(DB::table('users')->count())->toBe(0);
});

it('rechaza en la base un usuario sin rol', function () {
    $row = userRow();
    unset($row['role']);

    expectRejectedByDatabase(fn () => DB::table('users')->insert($row), sqlState: '23502');

    expect(DB::table('users')->count())->toBe(0);
});
