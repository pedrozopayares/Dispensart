<?php

use App\Enums\DocumentType;
use App\Enums\Role;
use App\Models\Patient;
use App\Models\PatientAccessLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// patients "Búsqueda de pacientes", "Ficha del paciente con prescripciones", "Enmascarado para el auditor" y
// audit-trail "Bitácora de acceso a pacientes": GET /api/patients y GET /api/patients/{id} por HTTP real.

const ANA = ['document_number' => '9999010001', 'full_name' => 'Ana Sintética Pérez', 'phone' => '3000000012', 'birth_date' => '1985-03-12'];

beforeEach(function () {
    $this->ana = Patient::factory()->create(['document_type' => DocumentType::CedulaCiudadania, ...ANA]);
    $this->carlos = Patient::factory()->create(['document_number' => '9999010002', 'full_name' => 'Carlos Sintético Gómez']);
});

function userWithRole(Role $role): User
{
    return User::factory()->withRole($role)->create(['name' => 'Usuario Demo']);
}

describe('búsqueda', function () {
    it('encuentra por prefijo de documento con el documento completo y masked falso', function () {
        $response = $this->actingAs(userWithRole(Role::AuxiliarFarmacia))->getJson('/api/patients?q=999901');

        $response->assertOk()->assertJsonCount(2, 'data');
        $ana = collect($response->json('data'))->firstWhere('id', $this->ana->id);
        expect($ana)->toBe([
            'id' => $this->ana->id, 'document_type' => 'CC', 'document_number' => '9999010001',
            'full_name' => 'Ana Sintética Pérez', 'birth_date' => '1985-03-12', 'phone' => '3000000012', 'masked' => false,
        ]);
    });

    it('encuentra por nombre sin distinguir mayúsculas', function () {
        $this->actingAs(userWithRole(Role::Medico))->getJson('/api/patients?q=SINTÉTICA')
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->ana->id)
            ->assertJsonCount(1, 'data');
    });

    it('devuelve data vacío sin coincidencias y no registra filas de acceso', function () {
        $this->actingAs(userWithRole(Role::RegenteFarmacia))->getJson('/api/patients?q=zzzz')
            ->assertOk()
            ->assertExactJson(['data' => []]);

        expect(PatientAccessLog::count())->toBe(0);
    });

    it('rechaza un término ausente, corto o largo sin listar pacientes ni repetir el valor', function (string $query) {
        $response = $this->actingAs(userWithRole(Role::AuxiliarFarmacia))->getJson('/api/patients'.$query);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['q'], responseKey: 'errors')
            ->assertJsonMissingPath('data');
        expect((string) $response->getContent())->not->toContain('99')
            ->not->toContain(str_repeat('x', 51));
        expect(PatientAccessLog::count())->toBe(0);
    })->with([
        'sin q' => [''],
        '2 caracteres' => ['?q=99'],
        '51 caracteres' => ['?q='.str_repeat('x', 51)],
    ]);

    it('limita a 20 resultados ordenados por nombre y escapa los comodines', function () {
        Patient::factory()->count(25)->create();

        $names = array_column($this->actingAs(userWithRole(Role::Medico))->getJson('/api/patients?q=Sintético')->json('data'), 'full_name');
        $sorted = $names;
        sort($sorted);

        expect($names)->toHaveCount(20)->toBe($sorted);
        $this->getJson('/api/patients?q=%25%25%25')->assertOk()->assertExactJson(['data' => []]);
        $this->getJson('/api/patients?q=___')->assertOk()->assertExactJson(['data' => []]);
    });

    it('registra una fila search por cada paciente devuelto y ninguna por los demás', function () {
        Patient::factory()->create(['document_number' => '9999990000', 'full_name' => 'Otro Sintético']);
        $user = userWithRole(Role::AuxiliarFarmacia);

        $response = $this->actingAs($user)->getJson('/api/patients?q=999901')->assertOk();

        $rows = PatientAccessLog::orderBy('patient_id')->get();
        expect($rows->pluck('patient_id')->all())->toBe([$this->ana->id, $this->carlos->id])
            ->and($rows->pluck('action')->map->value->unique()->all())->toBe(['search'])
            ->and($rows->pluck('user_id')->unique()->all())->toBe([$user->id])
            ->and($rows->pluck('route')->unique()->all())->toBe(['/api/patients'])
            ->and($rows->pluck('correlation_id')->unique()->all())->toBe([$response->headers->get('X-Correlation-Id')]);
    });

    it('no guarda en la fila de acceso el término, el nombre ni el documento', function () {
        $this->actingAs(userWithRole(Role::AuxiliarFarmacia))->getJson('/api/patients?q=9999010001')->assertOk();

        $row = (string) json_encode(DB::table('patient_access_logs')->get()->all());
        expect(PatientAccessLog::sole()->patient_id)->toBe($this->ana->id); // control positivo: la fila existe
        foreach (['9999010001', 'Ana', 'Sintética', 'Pérez', 'Sintética'] as $forbidden) {
            expect($row)->not->toContain($forbidden);
        }
    });

    it('devuelve al auditor el paciente buscado por documento completo enmascarado', function () {
        $this->actingAs(userWithRole(Role::Auditor))->getJson('/api/patients?q=9999010001')
            ->assertOk()
            ->assertExactJson(['data' => [[
                'id' => $this->ana->id, 'document_type' => 'CC', 'document_number' => '*******001',
                'full_name' => 'A*** S*** P***', 'birth_date' => null, 'phone' => '********12', 'masked' => true,
            ]]]);
    });

    it('responde por rol: datos en claro, enmascarados o 403', function (Role $role, int $status, ?bool $masked) {
        $response = $this->actingAs(userWithRole($role))->getJson('/api/patients?q=9999010001');

        $response->assertStatus($status);
        if ($masked === null) {
            $response->assertExactJson(['code' => 'forbidden', 'message' => __('errors.forbidden')]);
            expect(PatientAccessLog::count())->toBe(0);
        } else {
            $response->assertJsonPath('data.0.masked', $masked);
        }
    })->with([
        'auxiliar_farmacia' => [Role::AuxiliarFarmacia, 200, false],
        'regente_farmacia' => [Role::RegenteFarmacia, 200, false],
        'medico' => [Role::Medico, 200, false],
        'auditor' => [Role::Auditor, 200, true],
        'admin' => [Role::Admin, 403, null],
    ]);

    it('responde 401 sin sesión', function () {
        $this->getJson('/api/patients?q=999901')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    });
});

describe('ficha', function () {
    beforeEach(function () {
        $this->product = Product::factory()->create();
        $this->older = prescriptionWith([[$this->product, 3, 3]], ['patient_id' => $this->ana->id, 'created_at' => now()->subDays(5)]);
        $this->prescription = prescriptionWith([[$this->product, 10, 4]], ['patient_id' => $this->ana->id]);
        $this->prescription->prescriber->forceFill(['name' => 'Médico Demo'])->save();
        $this->older->prescriber->forceFill(['name' => 'Médico Demo'])->save();
    });

    it('devuelve la ficha con prescripciones de la más reciente a la más antigua y sus saldos', function () {
        $response = $this->actingAs(userWithRole(Role::AuxiliarFarmacia))->getJson('/api/patients/'.$this->ana->id);

        $response->assertOk()
            ->assertJsonPath('data.masked', false)
            ->assertJsonPath('data.prescriptions.0.id', $this->prescription->id)
            ->assertJsonPath('data.prescriptions.0.status', 'vigente')
            ->assertJsonPath('data.prescriptions.0.prescriber', ['id' => $this->prescription->prescriber_id, 'name' => 'Médico Demo'])
            ->assertJsonPath('data.prescriptions.0.items.0.product.id', $this->product->id)
            ->assertJsonPath('data.prescriptions.0.items.0.product.is_controlled', false)
            ->assertJsonPath('data.prescriptions.0.items.0.prescribed_quantity', 10)
            ->assertJsonPath('data.prescriptions.0.items.0.dispensed_quantity', 4)
            ->assertJsonPath('data.prescriptions.0.items.0.pending_quantity', 6)
            ->assertJsonPath('data.prescriptions.1.id', $this->older->id)
            ->assertJsonPath('data.prescriptions.1.status', 'agotada');
        expect(array_keys($response->json('data.prescriptions.0')))
            ->toBe(['id', 'patient_id', 'status', 'valid_until', 'created_at', 'prescriber', 'items']);
    });

    it('devuelve prescriptions vacío para un paciente sin prescripciones', function () {
        $this->actingAs(userWithRole(Role::Medico))->getJson('/api/patients/'.$this->carlos->id)
            ->assertOk()
            ->assertJsonPath('data.id', $this->carlos->id)
            ->assertJsonPath('data.prescriptions', []);
    });

    it('responde 404 a un paciente inexistente sin fila de acceso', function () {
        $this->actingAs(userWithRole(Role::RegenteFarmacia))->getJson('/api/patients/999999')
            ->assertNotFound()
            ->assertExactJson(['code' => 'not_found', 'message' => __('errors.not_found')]);

        expect(PatientAccessLog::count())->toBe(0);
    });

    it('responde 403 al admin sin datos del paciente ni fila de acceso, también para un id inexistente', function () {
        foreach ([$this->ana->id, 999999] as $id) {
            $response = $this->actingAs(userWithRole(Role::Admin))->getJson('/api/patients/'.$id);

            $response->assertForbidden()->assertExactJson(['code' => 'forbidden', 'message' => __('errors.forbidden')]);
        }
        expect(PatientAccessLog::count())->toBe(0);
    });

    it('responde 401 sin sesión', function () {
        $this->getJson('/api/patients/'.$this->ana->id)->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    });

    it('enmascara la ficha para el auditor con sus prescripciones completas', function () {
        $response = $this->actingAs(userWithRole(Role::Auditor))->getJson('/api/patients/'.$this->ana->id);

        $response->assertOk()
            ->assertJsonPath('data.document_number', '*******001')
            ->assertJsonPath('data.full_name', 'A*** S*** P***')
            ->assertJsonPath('data.phone', '********12')
            ->assertJsonPath('data.birth_date', null)
            ->assertJsonPath('data.masked', true)
            ->assertJsonPath('data.prescriptions.0.items.0.pending_quantity', 6)
            ->assertJsonCount(2, 'data.prescriptions');
    });

    it('no deja ningún dato en claro en los cuerpos de búsqueda y ficha del auditor', function () {
        $auditor = userWithRole(Role::Auditor);
        $bodies = [];
        foreach (['/api/patients?q=9999010001', '/api/patients/'.$this->ana->id] as $uri) {
            $raw = (string) $this->actingAs($auditor)->getJson($uri)->assertOk()->getContent();
            // Texto crudo (unicode escapado por JSON) y decodificado (unicode literal).
            $bodies[] = $raw;
            $bodies[] = (string) json_encode(json_decode($raw, true, flags: JSON_THROW_ON_ERROR), JSON_UNESCAPED_UNICODE);
        }

        // Control positivo: el paciente enmascarado sí está en los cuatro textos.
        expect($bodies)->each->toContain('*******001');
        foreach (['9999010001', 'Ana', 'Sintética', 'Sint\\u00e9tica', 'Pérez', 'P\\u00e9rez', '3000000012', '1985-03-12'] as $forbidden) {
            expect($bodies)->each->not->toContain($forbidden);
        }
    });

    it('muestra al regente los datos en claro', function () {
        $this->actingAs(userWithRole(Role::RegenteFarmacia))->getJson('/api/patients/'.$this->ana->id)
            ->assertOk()
            ->assertJsonPath('data.document_number', '9999010001')
            ->assertJsonPath('data.full_name', 'Ana Sintética Pérez')
            ->assertJsonPath('data.masked', false);
    });

    it('registra exactamente una fila view con usuario, paciente, ruta, correlation_id y fecha', function (Role $role) {
        $user = userWithRole($role);

        $response = $this->actingAs($user)->getJson('/api/patients/'.$this->ana->id)->assertOk();

        $row = PatientAccessLog::sole();
        expect($row->user_id)->toBe($user->id)
            ->and($row->patient_id)->toBe($this->ana->id)
            ->and($row->action->value)->toBe('view')
            ->and($row->route)->toBe('/api/patients/{patient}')
            ->and($row->correlation_id)->toBe($response->headers->get('X-Correlation-Id'))
            ->and($row->created_at)->not->toBeNull();
    })->with([
        'auxiliar_farmacia' => [Role::AuxiliarFarmacia],
        'auditor' => [Role::Auditor],
    ]);

    it('responde 500 sin datos del paciente si la fila de acceso no puede escribirse', function () {
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION test_fail_access_log() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN RAISE EXCEPTION 'fallo forzado de la bitácora'; END; $$;
            CREATE TRIGGER test_fail_access_log BEFORE INSERT ON patient_access_logs
                FOR EACH ROW EXECUTE FUNCTION test_fail_access_log();
            SQL);

        $response = $this->actingAs(userWithRole(Role::AuxiliarFarmacia))->getJson('/api/patients/'.$this->ana->id);

        $response->assertStatus(500)->assertExactJson(['code' => 'server_error', 'message' => __('errors.server_error')]);
        foreach (['9999010001', 'Ana', 'Sintética', '3000000012', '"prescriptions"'] as $forbidden) {
            expect((string) $response->getContent())->not->toContain($forbidden);
        }
    });
});
