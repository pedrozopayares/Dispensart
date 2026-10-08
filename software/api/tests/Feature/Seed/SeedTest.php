<?php

use App\Enums\Role;
use App\Models\Lot;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\BusinessCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\SpaClient;

uses(RefreshDatabase::class);

// seed-data: catálogo y usuarios semilla sintéticos, siembra idempotente por clave natural.

// Valor por defecto documentado en .env.example (solo desarrollo local, design D10).
const DOCUMENTED_DEV_PASSWORD = 'dispensart-dev-only';

/**
 * @return array{warehouses: int, products: int, lots: int, users: int}
 */
function seedCounts(): array
{
    return [
        'warehouses' => Warehouse::count(),
        'products' => Product::count(),
        'lots' => Lot::count(),
        'users' => User::count(),
    ];
}

describe('catálogo semilla', function () {
    it('siembra exactamente las 3 bodegas y 6 productos, uno de control especial y uno de acetaminofén', function () {
        $this->seed();

        expect(Warehouse::pluck('name')->all())
            ->toEqualCanonicalizing(['Farmacia Central', 'Farmacia Urgencias', 'Bodega Hospitalización'])
            ->and(Product::count())->toBe(6)
            ->and(Product::where('is_controlled', true)->count())->toBe(1)
            ->and(Product::where('name', 'like', '%Acetaminofén%')->count())->toBe(1);
    });

    it('siembra 2 o 3 lotes por producto y al menos uno no vencido del controlado', function () {
        $this->seed();
        $today = BusinessCalendar::today();

        foreach (Product::with('lots')->get() as $product) {
            expect($product->lots->count())->toBeGreaterThanOrEqual(2)->toBeLessThanOrEqual(3);
        }
        $controlled = Product::where('is_controlled', true)->firstOrFail();
        expect($controlled->lots->reject(fn (Lot $lot) => $lot->isExpiredOn($today)))->not->toBeEmpty();
    });

    it('distribuye vencimientos: vencido, 1–29, 31–90 y más de 90 días', function () {
        $this->seed();
        $today = BusinessCalendar::today();
        $days = Lot::all()->map(fn (Lot $lot) => (int) $today->diffInDays($lot->expires_on, false));

        expect($days->filter(fn (int $d) => $d <= 0))->not->toBeEmpty()
            ->and($days->filter(fn (int $d) => $d >= 1 && $d <= 29))->not->toBeEmpty()
            ->and($days->filter(fn (int $d) => $d >= 31 && $d <= 90))->not->toBeEmpty()
            ->and($days->filter(fn (int $d) => $d > 90))->not->toBeEmpty();
    });

    it('no contiene correos fuera de dispensart.test en el código de siembra', function () {
        $source = implode("\n", array_map('file_get_contents', glob(database_path('seeders/*.php'))));
        preg_match_all('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]+/', $source, $matches);

        expect($matches[0])->not->toBeEmpty();
        foreach ($matches[0] as $email) {
            expect($email)->toEndWith('@dispensart.test');
        }
    });
});

describe('usuarios semilla', function () {
    it('crea exactamente un usuario por rol con los correos indicados', function () {
        $this->seed();

        expect(User::pluck('role', 'email')->map(fn (Role $role) => $role->value)->all())->toEqual([
            'auxiliar@dispensart.test' => 'auxiliar_farmacia',
            'regente@dispensart.test' => 'regente_farmacia',
            'medico@dispensart.test' => 'medico',
            'auditor@dispensart.test' => 'auditor',
            'admin@dispensart.test' => 'admin',
        ]);
    });

    it('permite iniciar sesión con la contraseña documentada si SEED_USER_PASSWORD no está definida', function () {
        expect(config('dispensart.seed_user_password'))->toBeEmpty();
        $this->seed();

        (new SpaClient($this))->login('regente@dispensart.test', DOCUMENTED_DEV_PASSWORD)
            ->assertOk()
            ->assertJsonPath('data.role', 'regente_farmacia');
    });

    it('usa SEED_USER_PASSWORD si está definida y descarta el valor por defecto', function () {
        config(['dispensart.seed_user_password' => 'clave-de-entorno-123']);
        $this->seed();

        (new SpaClient($this))->login('regente@dispensart.test', DOCUMENTED_DEV_PASSWORD)
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_credentials');
        (new SpaClient($this))->login('regente@dispensart.test', 'clave-de-entorno-123')->assertOk();
    });

    it('guarda las contraseñas con hash', function () {
        $this->seed();

        foreach (DB::table('users')->pluck('password') as $stored) {
            expect($stored)->not->toBe(DOCUMENTED_DEV_PASSWORD)->toStartWith('$2y$');
        }
    });

    it('en producción sin SEED_USER_PASSWORD siembra el catálogo, ningún usuario y avisa sin contraseña', function () {
        $log = captureLog();
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->app->detectEnvironment(fn () => 'testing');
        expect(User::count())->toBe(0)
            ->and(Warehouse::count())->toBe(3)
            ->and(Product::count())->toBe(6);
        $warnings = array_values(array_filter(logLines($log), fn (array $line) => $line['level'] === 'warning'));
        expect($warnings)->toHaveCount(1)
            ->and($warnings[0]['message'])->toContain('SEED_USER_PASSWORD')
            ->and((string) file_get_contents($log))->not->toContain(DOCUMENTED_DEV_PASSWORD);
    });
});

describe('siembra idempotente', function () {
    it('repite la siembra sin error y con los mismos conteos', function () {
        $this->seed();
        $first = seedCounts();

        $this->seed();

        expect(seedCounts())->toBe($first)
            ->and($first)->toBe(['warehouses' => 3, 'products' => 6, 'lots' => 14, 'users' => 5]);
    });

    it('conserva el nombre que el admin dio a una bodega semilla', function () {
        $this->seed();
        Warehouse::where('code', 'FC')->update(['name' => 'Farmacia Central Norte']);

        $this->seed();

        expect(Warehouse::where('code', 'FC')->value('name'))->toBe('Farmacia Central Norte')
            ->and(Warehouse::where('name', 'Farmacia Central')->exists())->toBeFalse()
            ->and(Warehouse::count())->toBe(3);
    });

    it('deja intactas las filas que no son semilla', function () {
        $this->seed();
        $own = Product::factory()->create(['code' => 'MED-900', 'name' => 'Producto del admin', 'presentation' => null]);
        $snapshot = DB::table('products')->where('id', $own->id)->first();

        $this->seed();

        expect(DB::table('products')->where('id', $own->id)->first())->toEqual($snapshot);
    });
});
