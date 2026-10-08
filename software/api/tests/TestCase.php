<?php

namespace Tests;

use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml fuerza APP_KEY vacía (nunca se versiona una clave). Las cookies cifradas de la
        // sesión Sanctum necesitan una: se genera una aleatoria por prueba, solo en memoria.
        if (blank(config('app.key'))) {
            config(['app.key' => 'base64:'.base64_encode(Encrypter::generateKey((string) config('app.cipher')))]);
            $this->app->forgetInstance('encrypter');
        }
    }
}
