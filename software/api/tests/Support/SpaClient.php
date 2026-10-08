<?php

namespace Tests\Support;

use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Cliente de prueba que se comporta como la SPA en el navegador (design D2).
 *
 * - Envía el Origin de la SPA: la petición pasa por la pila con estado de Sanctum (sesión + CSRF real).
 * - Guarda las cookies de cada respuesta tal como llegan (cifradas) y las reenvía sin tocarlas.
 * - Pone X-XSRF-TOKEN desde la cookie XSRF-TOKEN, como hace la SPA.
 * - Antes de cada petición olvida guardias, sesión en memoria y cookies en cola, igual que un proceso
 *   FPM nuevo: el usuario y su rol salen de la base en cada petición, nunca de memoria de la anterior.
 * - Sesión en la base (driver database), como en el stack; nunca el driver array, que comparte estado.
 */
final class SpaClient
{
    public const SPA_ORIGIN = 'http://localhost:8090';

    /** @var array<string, string> */
    private array $cookies = [];

    public function __construct(
        private readonly TestCase $test,
        private readonly ?string $origin = self::SPA_ORIGIN,
        private readonly string $ip = '127.0.0.1',
    ) {
        config(['session.driver' => 'database']);
    }

    public function csrfCookie(): TestResponse
    {
        return $this->send('GET', '/sanctum/csrf-cookie');
    }

    /**
     * Flujo de la SPA: cookie CSRF y luego login con el token.
     */
    public function login(string $email, string $password = 'password'): TestResponse
    {
        $this->csrfCookie();

        return $this->post('/api/auth/login', ['email' => $email, 'password' => $password]);
    }

    public function loginAs(User $user, string $password = 'password'): TestResponse
    {
        return $this->login($user->email, $password)->assertOk();
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function get(string $uri, array $headers = []): TestResponse
    {
        return $this->send('GET', $uri, null, $headers);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    public function post(string $uri, array $data = [], array $headers = [], bool $withXsrf = true): TestResponse
    {
        return $this->send('POST', $uri, $data, $headers, $withXsrf);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     */
    public function patch(string $uri, array $data = [], array $headers = [], bool $withXsrf = true): TestResponse
    {
        return $this->send('PATCH', $uri, $data, $headers, $withXsrf);
    }

    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    /**
     * Valor descifrado de una cookie (p. ej. el id de sesión), para comparar entre respuestas.
     */
    public function decryptedCookie(string $name): ?string
    {
        $value = $this->cookie($name);

        return $value === null ? null : CookieValuePrefix::remove((string) decrypt($value, false));
    }

    /**
     * @return array<string, string>
     */
    public function cookies(): array
    {
        return $this->cookies;
    }

    /**
     * @param  array<string, string>  $cookies
     */
    public function useCookies(array $cookies): self
    {
        $this->cookies = $cookies;

        return $this;
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @param  array<string, string>  $headers
     */
    private function send(string $method, string $uri, ?array $data = null, array $headers = [], bool $withXsrf = true): TestResponse
    {
        $this->forgetProcessState();

        $server = ['HTTP_ACCEPT' => 'application/json', 'REMOTE_ADDR' => $this->ip];
        if ($this->origin !== null) {
            $server['HTTP_ORIGIN'] = $this->origin;
        }
        if ($withXsrf && isset($this->cookies['XSRF-TOKEN'])) {
            $server['HTTP_X_XSRF_TOKEN'] = $this->cookies['XSRF-TOKEN'];
        }
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        $content = null;
        if ($data !== null) {
            $content = (string) json_encode($data);
            $server['CONTENT_TYPE'] = 'application/json';
        }

        $response = $this->test->call($method, $uri, [], $this->cookies, [], $server, $content);

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->isCleared()) {
                unset($this->cookies[$cookie->getName()]);
            } else {
                $this->cookies[$cookie->getName()] = (string) $cookie->getValue();
            }
        }

        return $response;
    }

    private function forgetProcessState(): void
    {
        $app = app();
        $app['auth']->forgetGuards();
        $app['session']->forgetDrivers();
        $app->forgetInstance('session.store');
        $app['cookie']->flushQueuedCookies();
    }
}
