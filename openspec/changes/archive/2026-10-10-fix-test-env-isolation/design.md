# Design — fix-test-env-isolation (S17, tier A)

## Context
`api-tools` hereda `AI_PROVIDER`/`OLLAMA_MODEL` de `software/.env` (`compose.yaml:132-134`); `phpunit.xml` no las fija.
Laravel lee `$_SERVER` → `$_ENV` → `getenv()` (`Env::getRepository`, `ServerConstAdapter` primero): el entorno del
proceso decide el proveedor dentro de la suite. Disparador A: aislamiento del arnés (falso verde/rojo).

## Goals / Non-Goals
- Goals: suite con `mock` y `qwen2.5:3b` sea cual sea el proceso; prueba que falla si el pin desaparece;
  `assistant:eval` sigue al proceso.
- Non-Goals: `ci.yml`, `compose.yaml`, `.env.example`, docs, código de producción, `OLLAMA_BASE_URL`/`OLLAMA_TIMEOUT`.

## Decisions
- **D1 Pin doble `<server>` + `<env force="true">`** por variable, patrón `DB_*`. `<server>` es la línea que gana en
  `env()`/`config()` (PHPUnit la escribe siempre; su `force` es simétrico, no operativo). `<env force>` cubre
  `getenv()`/`$_ENV`, que leen los procesos hijos (Symfony Process: `RaceRunner::launch` arranca workers que heredan el
  entorno). Rechazado: solo `<env>` (pierde ante `$_SERVER` del contenedor); solo `<server>` (hijos ven `ollama`);
  `.env.testing` (Dotenv inmutable no pisa el proceso); `config([...])` en `Pest.php` (no cubre `env()` ni hijos).
  Trade-off: pin duplicado por variable. Revisar si Laravel antepone otro adaptador a `ServerConstAdapter`.
- **D2 Prueba de comportamiento bajo entorno hostil**, archivo `tests/Feature/TestEnvironmentIsolationTest.php`
  (fuera del hook de `Feature/Assistant`, así que declara su propio `beforeEach(Http::preventStrayRequests())`). Afirma
  en cada lector: `config('assistant.provider')`, `getenv('AI_PROVIDER')`, `ModelCatalog::defaultChoice()->provider`,
  `app(LlmProvider::class)` instancia de `MockLlmProvider`; ídem `OLLAMA_MODEL` con `config` y `getenv`. Elegir `mock`
  no toca la red; `preventStrayRequests` convierte cualquier salida en fallo. Rechazado: probar vía
  `POST /api/assistant/ask` (siembra y costo sin señal nueva). Revisar si el catálogo consulta red para `mock`.
- **D3 Guarda estática para «Pin retirado…»**: XPath sobre `base_path('phpunit.xml')` exige `env` y `server`
  `AI_PROVIDER=mock` (e `OLLAMA_MODEL`); el mensaje nombra la variable. Motivo: en `api-tools` la variable siempre
  existe (`${AI_PROVIDER:-mock}`) y en CI no existe; en ambos casos D2 pasa con o sin pin. Rechazado:
  `PHPUnit\TextUI\Configuration\Registry` (`@internal`, cambia entre mayores); comparar `/proc/self/environ` (no
  distingue pin de proceso igual al pin). Revisar si la suite se corre con otro `-c`.
- **D4 `assistant:eval` intacto**: Artisan no carga `phpunit.xml`; `-e AI_PROVIDER=ollama` sigue mandando. Se prueba
  contra un puerto cerrado (tarea 1.5), sin red real.
- **D5 CI sin cambios**: el paso Pest de `ci.yml` no define `AI_PROVIDER` (pin = no-op, guarda D3 activa); el paso
  eval ya fija `mock`.
- **D6 Sin enmascarar pruebas que fijan proveedor**: `config([...])` en el cuerpo de la prueba corre tras el arranque
  y gana sobre el valor derivado del pin; la app se recrea por prueba. Rechazado: congelar `config('assistant')`.

## API contract
Ninguna ruta nueva ni cambiada.

## Data impact
Ninguno: sin migraciones, sin restricciones, sin escritura de stock.

## Risks
1. Filtro de Pest que no casa (acento) → verde con cero pruebas. Mitigación: `--fail-on-empty-test-suite` en todo comando.
2. Guarda D3 rota por renombrar el XML. Mitigación: falla ruidosa (archivo ausente = rojo), no silenciosa.
3. Prueba futura que confíe en heredar `ollama`. Mitigación: la regla del delta la obliga a fijarlo; barrido de 1.4.
