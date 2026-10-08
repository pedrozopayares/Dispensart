<?php

use App\Models\AuditEvent;
use App\Models\Dispensation;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

// dispensation "Coautorización de control especial" (RN-05) y audit-trail "Bitácora de operaciones sensibles":
// POST /api/dispensations por HTTP real con credenciales del autorizador en la misma petición.

beforeEach(function () {
    $this->auxiliar = User::factory()->auxiliar()->create();
    $this->regente = User::factory()->regente()->create();
    $this->warehouse = Warehouse::factory()->create();
    $this->controlled = Product::factory()->controlled()->create();
    lotStock($this->warehouse, $this->controlled, 30, 20);
    $this->prescription = prescriptionWith([[$this->controlled, 10]]);
});

/**
 * @return array<string, mixed>
 */
function controlledBody(?string $email, ?string $password, int $quantity = 1): array
{
    $test = test();
    $extra = array_filter(['authorizer_email' => $email, 'authorizer_password' => $password], fn ($v) => $v !== null);

    return dispensationBody($test->prescription, $test->warehouse, [[$test->controlled, $quantity]], $extra);
}

it('dispensa con la autorización del regente, guarda authorized_by y registra ambas filas', function () {
    $response = dispense($this->auxiliar, controlledBody($this->regente->email, 'password'));

    $response->assertCreated()->assertJsonPath('data.authorized_by', $this->regente->id);
    expect((string) $response->getContent())->not->toContain('authorizer_password')->not->toContain('password');

    $events = AuditEvent::orderBy('id')->get()->map(fn ($e) => [$e->action->value, $e->actor_id, $e->subject_type, $e->subject_id])->all();
    expect($events)->toBe([
        ['dispensation.created', $this->auxiliar->id, 'dispensation', $response->json('data.id')],
        ['controlled_drug.authorized', $this->regente->id, 'dispensation', $response->json('data.id')],
    ]);
});

it('permite a un regente dispensar con la autorización de otro regente', function () {
    $second = User::factory()->regente()->create();

    dispense($this->regente, controlledBody(strtoupper($second->email), 'password'))
        ->assertCreated()
        ->assertJsonPath('data.dispensed_by', $this->regente->id)
        ->assertJsonPath('data.authorized_by', $second->id);
});

it('exige correo y contraseña del autorizador sin cambios', function (Closure $body) {
    $before = dispensationState();

    dispense($this->auxiliar, $body())
        ->assertStatus(422)
        ->assertExactJson(['code' => 'authorization_required', 'message' => __('errors.authorization_required')]);

    expect(dispensationState())->toBe($before);
})->with([
    'sin datos' => [fn () => controlledBody(null, null)],
    'sin contraseña' => [fn () => controlledBody(test()->regente->email, null)],
    'sin correo' => [fn () => controlledBody(null, 'password')],
]);

it('rechaza que el regente se autorice a sí mismo sin cambios', function () {
    $before = dispensationState();

    dispense($this->regente, controlledBody(' '.strtoupper($this->regente->email).' ', 'password'))
        ->assertStatus(422)
        ->assertExactJson(['code' => 'authorizer_must_differ', 'message' => __('errors.authorizer_must_differ')]);

    expect(dispensationState())->toBe($before);
});

it('responde igual a un correo inexistente, una contraseña errada y un usuario sin la capacidad, y registra cada fallo', function () {
    $medico = User::factory()->medico()->create();
    $otherAux = User::factory()->auxiliar()->create();
    $before = dispensationState();

    $bodies = [];
    foreach ([
        ['nadie@dispensart.test', 'password'],
        [$this->regente->email, 'incorrecta'],
        [$otherAux->email, 'password'],
        [$medico->email, 'password'],
    ] as [$email, $password]) {
        $response = dispense($this->auxiliar, controlledBody($email, $password));
        $response->assertStatus(422);
        $bodies[] = $response->getContent();
    }

    expect(array_unique($bodies))->toBe([json_encode(['code' => 'invalid_authorizer', 'message' => __('errors.invalid_authorizer')])])
        ->and(dispensationState())->toBe($before);
    $failed = AuditEvent::where('action', 'controlled_drug.authorization_failed')->get();
    expect($failed)->toHaveCount(4)
        ->and($failed->pluck('actor_id')->unique()->all())->toBe([$this->auxiliar->id])
        ->and($failed->pluck('subject_id')->unique()->all())->toBe([$this->prescription->id])
        ->and($failed->pluck('details')->unique()->values()->all())->toBe([['warehouse_id' => $this->warehouse->id]]);
    $raw = (string) json_encode(DB::table('audit_events')->get()->all());
    expect($raw)->not->toContain('nadie@')->not->toContain($this->regente->email)->not->toContain('incorrecta');
});

it('responde 429 con Retry-After al sexto intento tras 5 fallos, aun con la contraseña correcta', function () {
    for ($i = 1; $i <= 5; $i++) {
        dispense($this->auxiliar, controlledBody($this->regente->email, 'incorrecta-'.$i))->assertStatus(422);
    }
    $before = dispensationState();

    $response = dispense($this->auxiliar, controlledBody($this->regente->email, 'password'));

    $response->assertStatus(429)->assertJsonPath('code', 'too_many_attempts');
    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0)
        ->and(dispensationState())->toBe($before);
});

it('ignora credenciales erradas cuando ningún producto es controlado', function () {
    $plain = Product::factory()->create();
    lotStock($this->warehouse, $plain, 30, 5);
    $prescription = prescriptionWith([[$plain, 5]]);

    dispense($this->auxiliar, dispensationBody($prescription, $this->warehouse, [[$plain, 1]], [
        'authorizer_email' => 'nadie@dispensart.test', 'authorizer_password' => 'incorrecta',
    ]))->assertCreated()->assertJsonPath('data.authorized_by', null);

    expect(AuditEvent::where('action', 'controlled_drug.authorization_failed')->count())->toBe(0);
});

it('no deja la contraseña del autorizador en respuestas, log, bitácoras ni registros de idempotencia', function () {
    $secret = 'Clave-Sintetica-123';
    $logPath = captureLog();
    $authorizer = User::factory()->regente()->create(['password' => $secret]);
    $noCapability = User::factory()->auxiliar()->create(['password' => $secret]);

    $ok = dispense($this->auxiliar, controlledBody($authorizer->email, $secret))->assertCreated();
    $rejected = dispense($this->auxiliar, controlledBody($noCapability->email, $secret))->assertStatus(422);

    $sinks = [
        'respuestas' => $ok->getContent().$rejected->getContent(),
        'log' => (string) file_get_contents($logPath),
        'audit_events' => (string) json_encode(DB::table('audit_events')->get()->all()),
        'patient_access_logs' => (string) json_encode(DB::table('patient_access_logs')->get()->all()),
        'idempotency_keys' => (string) json_encode(DB::table('idempotency_keys')->get()->all()),
    ];
    // Control positivo: cada fuente tiene contenido (2 filas de operación + 1 de fallo, 1 registro, 4 líneas de log).
    expect(DB::table('audit_events')->count())->toBe(3)
        ->and(DB::table('idempotency_keys')->count())->toBe(1)
        ->and(count(logLines($logPath)))->toBeGreaterThanOrEqual(2);
    foreach ($sinks as $name => $text) {
        expect(str_contains($text, $secret))->toBeFalse("la contraseña aparece en {$name}");
    }

    // Control positivo del barrido: una línea plantada se detecta con el mismo criterio.
    Log::info('plantada '.$secret);
    expect(str_contains((string) file_get_contents($logPath), $secret))->toBeTrue();
});

it('no escribe filas de operación en un 409 ni en un 422 aun con autorización válida', function () {
    $before = dispensationState();

    // 409: se piden 21 y la bodega tiene 20 no vencidas.
    $big = prescriptionWith([[$this->controlled, 50]]);
    dispense($this->auxiliar, dispensationBody($big, $this->warehouse, [[$this->controlled, 21]], [
        'authorizer_email' => $this->regente->email, 'authorizer_password' => 'password',
    ]))->assertStatus(409)->assertJsonPath('code', 'insufficient_stock');
    // 422: prescripción agotada.
    $exhausted = prescriptionWith([[$this->controlled, 2, 2]]);
    dispense($this->auxiliar, dispensationBody($exhausted, $this->warehouse, [[$this->controlled, 1]], [
        'authorizer_email' => $this->regente->email, 'authorizer_password' => 'password',
    ]))->assertStatus(422)->assertJsonPath('code', 'prescription_exhausted');

    expect(dispensationState()['operations'])->toBe($before['operations'])
        ->and(AuditEvent::whereIn('action', ['dispensation.created', 'controlled_drug.authorized'])->count())->toBe(0);
    // Control positivo: la misma autorización sí deja ambas filas en un éxito.
    dispense($this->auxiliar, controlledBody($this->regente->email, 'password'))->assertCreated();
    expect(AuditEvent::whereIn('action', ['dispensation.created', 'controlled_drug.authorized'])->count())->toBe(2);
});
