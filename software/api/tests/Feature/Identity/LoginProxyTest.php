<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Support\SpaClient;

uses(RefreshDatabase::class);

// design D3 / riesgo 3: el limitador de login usa la IP real del cliente solo si la petición llega desde
// un proxy de confianza (rangos privados); un X-Forwarded-For desde otra fuente se ignora.

function loginVia(SpaClient $client, string $forwardedFor, string $password): TestResponse
{
    return $client->post(
        '/api/auth/login',
        ['email' => 'regente@dispensart.test', 'password' => $password],
        ['X-Forwarded-For' => $forwardedFor],
    );
}

beforeEach(function () {
    User::factory()->regente()->create(['email' => 'regente@dispensart.test']);
});

it('tras un proxy privado de confianza, cuenta los fallos por la IP reenviada del cliente', function () {
    // Mismo proxy (Nginx de web en la red de compose), dos clientes distintos.
    $attacker = new SpaClient($this, ip: '172.18.0.5');
    $attacker->csrfCookie();
    foreach (range(1, 5) as $attempt) {
        loginVia($attacker, '203.0.113.10', 'incorrecta-123')->assertStatus(422);
    }
    loginVia($attacker, '203.0.113.10', 'password')->assertStatus(429);

    $legitimate = new SpaClient($this, ip: '172.18.0.5');
    $legitimate->csrfCookie();

    // Sin confianza en el proxy ambos compartirían la IP 172.18.0.5 y este login daría 429.
    loginVia($legitimate, '198.51.100.20', 'password')->assertOk();
});

it('desde una fuente no confiable ignora X-Forwarded-For y cuenta por la IP de conexión', function () {
    $attacker = new SpaClient($this, ip: '198.51.100.7');
    $attacker->csrfCookie();
    foreach (range(1, 5) as $attempt) {
        // Rotar el encabezado falsificado no abre contadores nuevos.
        loginVia($attacker, "203.0.113.{$attempt}", 'incorrecta-123')->assertStatus(422);
    }

    loginVia($attacker, '203.0.113.99', 'password')
        ->assertStatus(429)
        ->assertJsonPath('code', 'too_many_attempts');
});
