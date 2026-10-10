# Design — add-assistant-model-selector (S15)

## Context

Motivo y alcance: `proposal.md` § Why / What Changes. Hoy `AssistantServiceProvider` enlaza un solo `LlmProvider`
por `AI_PROVIDER` (design D2 de S7) y `AskAssistant`/`AssistantOrchestrator` lo reciben por constructor; las pruebas
del asistente sustituyen ese enlace (`recordProvider()`, `scriptProvider()` en `tests/Helpers/Assistant.php`). La caché
por defecto de la API es `database` (`.env.example`) y Laravel 13 no deserializa clases desde la caché.

## Goals / Non-Goals

**Goals**
- Un único punto que decide qué modelos son elegibles (`ModelCatalog`) y un único punto que construye proveedores
  concretos (`AssistantServiceProvider`), también para la elección por petición.
- Ninguna cadena del cliente llega a Ollama salvo un nombre que el propio Ollama listó; la URL nunca sale del cliente.
- `ask` con modelo de Ollama no paga la consulta de disponibilidad en cada pregunta.
- Comportamiento de S7 intacto sin `model`: `AI_PROVIDER`, `assistant:eval`, CI y los dobles de prueba existentes.

**Non-Goals**
- Preferencia de modelo en el servidor o por usuario en la base (spec: solo `localStorage`).
- Proveedores nuevos (OpenAI u otros), streaming, descarga de modelos desde la app.
- Cambiar herramientas, `SystemPrompt`, filtro previo, defensa contra inyección o el limitador de `ask`.
- Fijar `AI_PROVIDER` en `phpunit.xml` (fuga de `software/.env` a `api-tools`): se mitiga con `-e AI_PROVIDER=mock`
  en cada comando y se reporta como deuda, fuera del alcance de la fila S15.

## Decisions

### D1. Identificador `ModelChoice`: `mock` | `ollama:<name>`

Objeto de valor `App\Services\Assistant\Llm\ModelChoice` (`provider`, `name`, `id()`), con `parse(string): ?self`:
`mock` → (`mock`, `mock`); `ollama:<name>` → (`ollama`, `<name>`), partiendo en el **primer** `:` (los nombres de Ollama
llevan `:` de etiqueta, p. ej. `gemma4:e2b-mlx`). `<name>` debe casar `^[A-Za-z0-9_][A-Za-z0-9_.:/-]{0,199}$`
(admite `hf.co/org/repo:Q4_K_M`); otra forma → `null`. El mismo patrón filtra lo que devuelve `/api/tags`: un nombre
fuera de él no entra a la lista, así ningún `id` ofrecido lleva marcado ni control. Un tercer valor
(`unavailable`) solo existe para el proveedor por defecto desconocido (D4); `parse()` nunca lo produce.

- Rechazado: `id` opaco (hash o índice) que el servidor traduce. Exige estado o mapa en el servidor y el historial no
  podría mostrar el nombre sin otra llamada. El nombre ya es público para quien usa la pantalla.
- Rechazado: `{provider, name}` como objeto en el cuerpo. Duplica validación y complica `localStorage` (un texto basta).
- Trade-off: el `id` expone el nombre de un modelo local; no es secreto (no hay URL, tamaño ni digest).
- Revisar si: aparece un segundo proveedor remoto; entonces `<provider>:<name>` sigue valiendo sin migrar valores.

### D2. `ModelCatalog`: disponibilidad con plazo total de 2 s

`App\Services\Assistant\Llm\ModelCatalog` (final, sin conocer clases de proveedor concretas):

- `available(): list<ModelChoice>` → `mock` primero; luego los de Ollama ordenados por `name` (determinista; el orden de
  `/api/tags` depende de `modified_at`).
- `contains(string $id): bool` → `mock`: `true` **sin red**; `parse()` nulo o proveedor ≠ `ollama`: `false` **sin red**;
  si no, pertenencia a `available()`.
- `defaultChoice(): ModelChoice` → desde `config('assistant.provider')`: `mock`/vacío → `mock`; `ollama` →
  `ollama:<assistant.ollama.model>`; otro → `unavailable`. Sin red.

Descubrimiento (solo en fallo de caché, D3): plazo `assistant.models.budget_seconds` = 2, medido con `hrtime`.
1. `GET {base_url}/api/tags` con `connectTimeout(min(1, restante))` y `timeout(restante)`.
2. `models` debe ser lista; nombres válidos por D1, únicos. Lista vacía → fin, sin `/api/show`.
3. Restante ≤ 0 → fin con lista vacía (no espera respuestas tardías).
4. `POST {base_url}/api/show {"model": name}` para todos a la vez con `Http::pool`, `timeout(restante)`.
   Se queda un modelo solo si su respuesta es 2xx, JSON y `capabilities` es lista que contiene `tools`. Un elemento
   del pool que es excepción o no 2xx se omite sin afectar a los demás.
5. Cualquier `ConnectionException`, `RequestException` o cuerpo no JSON en el paso 1 → lista vacía. Ninguna
   excepción sale de `ModelCatalog`; la ruta responde 200 con solo `mock`.

`base_url` y `budget_seconds` salen solo de `config/assistant.php`. Nada se registra con cuerpo ni URL.

- Rechazado: `/api/ps` (modelos cargados). Un modelo descargado se carga al primer uso (proposal, supuesto 1).
- Rechazado: `/api/show` en serie. N modelos × latencia rompe el plazo de 2 s; el pool lo acota a una ronda.
- Rechazado: ofrecer modelos sin `capabilities` (Ollama antiguo) suponiendo `tools`. Un modelo sin herramientas no
  puede consultar y devolvería `unknown` siempre; mejor no ofrecerlo.
- Trade-off: un Ollama anterior a `capabilities` en `/api/show` no ofrece ningún modelo (solo `mock`).
- Revisar si: Ollama cambia el formato de `capabilities` o se necesita ofrecer modelos sin herramientas.

### D3. Caché corta en almacén `file`, nunca en la base

`available()` guarda **solo la lista de nombres** (arreglo de cadenas, sin objetos: la caché de Laravel 13 no
deserializa clases) con `Cache::store(config('assistant.models.cache_store'))->remember($key, ttl, …)`:
- clave `assistant.models:` + `sha1(base_url)` (cambiar la URL invalida sola);
- TTL `assistant.models.cache_ttl_seconds` = 30, **también para la lista vacía** (Ollama caído): una pregunta con
  `ollama:…` no espera 2 s mientras Ollama sigue caído;
- `cache_store` = `env('ASSISTANT_MODELS_CACHE_STORE', 'file')`; `storage/framework/cache/data` ya es escribible por el
  usuario no root de la imagen (`docker/api/Dockerfile`). `phpunit.xml` fuerza `array` (`<env … force="true"/>`):
  cada prueba arranca con caché vacía y nada se comparte entre corridas.
- Sin candado contra estampida: dos fallos simultáneos hacen dos descubrimientos de solo lectura; costo acotado.

- Rechazado: almacén por defecto (`database`). Escribe en la tabla `cache` y contradice «Lista de modelos disponibles»
  (SHALL NOT escribir en la base).
- Rechazado: sin caché. Cada `ask` con `ollama:…` pagaría `/api/tags` + `/api/show` (hasta 2 s si Ollama cuelga).
- Rechazado: caché en memoria del proceso (`array`/estático). PHP-FPM no comparte memoria entre peticiones.
- Trade-off: hasta 30 s de desfase: un modelo borrado sigue en la lista y su pregunta da 503 (proposal, supuesto 7);
  un Ollama recién encendido tarda hasta 30 s en aparecer.
- Revisar si: varias réplicas de la API (el almacén `file` no se comparte; pasaría a Redis si existiera).

### D4. Proveedor por petición: `LlmProviderResolver` con fábrica cerrada en el service provider

`App\Services\Assistant\Llm\LlmProviderResolver` (final) con `for(?ModelChoice $choice): LlmProvider`:
- `null` → `$container->make(LlmProvider::class)`: el enlace por `AI_PROVIDER` de S7, que las pruebas existentes
  sustituyen; así `scriptProvider()`/`recordProvider()` y `assistant:eval` siguen sin cambio.
- elección explícita → la fábrica `Closure(ModelChoice): LlmProvider` que recibe por constructor.

`AssistantServiceProvider` define esa fábrica una vez (`match` sobre `provider`: `mock` → `MockLlmProvider`; `ollama` →
`OllamaLlmProvider(base_url, $choice->name, timeout)` con `base_url`/`timeout` del config; otro →
`UnavailableLlmProvider`) y la usa en dos sitios: el enlace de `LlmProvider` (con `defaultChoice()`) y el
`singleton` de `LlmProviderResolver`. Los proveedores concretos siguen usados solo ahí: las reglas `arch()` de
`tests/Arch/AssistantArchTest.php` **no cambian** y deben seguir verdes.

Flujo: `AskAssistant::handle(User, string $question, ?ModelChoice $choice = null)` resuelve el proveedor con el
resolver y el `id` con `$choice ?? $catalog->defaultChoice()`; `AssistantOrchestrator::answer()` recibe el
`LlmProvider` como argumento (deja de inyectarlo por constructor). El `id` se sella en `AssistantAnswer` (campo
`model`) en `AskAssistant`, también cuando responde el filtro previo. `assistant:eval` llama sin elección.

- Rechazado: enlazar `LlmProvider` leyendo `request()->input('model')` en el contenedor. Entrada del cliente fuera
  del FormRequest (sin validar en el punto de lectura), acopla el puerto a HTTP y rompe el comando de consola.
- Rechazado: resolver con `new OllamaLlmProvider` dentro de `App\Services`. Rompe `toOnlyBeUsedIn` y abre un segundo
  sitio que conoce clases concretas.
- Rechazado: construir el orquestador por petición con `makeWith`. Localizador de servicios escondido.
- Trade-off: con `model` explícito un doble de prueba enlazado a `LlmProvider` no se usa; las pruebas de Ollama por
  modelo usan `Http::fake` en el borde, que es lo que el spec pide.
- Revisar si: un tercer proveedor; se agrega un brazo al `match`, nada más.

### D5. Validación en `AskAssistantRequest` contra el catálogo

Reglas: `question` sin cambio; `model` → `['sometimes', 'bail', 'string', 'max:220', new AvailableModel($catalog)]`
(`rules(ModelCatalog $catalog)` por inyección del contenedor). `AvailableModel` (`App\Rules`, `ValidationRule`) llama
`contains()` y falla con `__('assistant.model_unavailable')` = "El modelo elegido no está disponible.". Tipos
inválidos (`7`, `["mock"]`, `null`) los corta `string` antes de tocar el catálogo (`bail`). `model()` del request
devuelve `?ModelChoice` (`parse()` sobre el valor ya validado).

Precedencia sin cambio (D12 de S7): 419 → 401 → 429 → 422 → 200/503. `question` y `model` inválidos a la vez
devuelven ambos en `errors`; no hay prioridad entre campos. Una consulta de catálogo solo ocurre con `ollama:…`
bien formado; `mock`, ausente, otro prefijo o URL no salen a la red.

Modelo elegido que desaparece entre la validación y `/api/chat` → `OllamaLlmProvider` lanza `AssistantUnavailable` →
503 (spec «Ollama falla al responder con el modelo elegido»).

- Rechazado: validar en `AskAssistant`. La entrada del cliente se valida en el FormRequest (skill laravel-backend) y
  así el 422 sale antes del caso de uso, sin log de consulta.
- Rechazado: aceptar cualquier `ollama:<name>` y dejar que Ollama falle. Texto arbitrario del cliente llegaría al
  servidor de modelos y el error sería 503, no 422.
- Revisar si: el catálogo se vuelve caro (más proveedores); entonces se valida por proveedor.

### D6. Ruta `GET /api/assistant/models` y autorización

Ruta dentro del grupo `auth:sanctum`, junto a `assistant.ask`, nombre `assistant.models`, controlador
`AssistantModelController` invocable de una línea: `AssistantModelResource::collection($catalog->available())`. El
recurso expone solo `id`, `provider`, `name` (mock: `name` = `mock`).

Autorización: igual que `ask` hoy, toda sesión (sin `can`); la API no excluye a `admin` en `ask` y la lista debe ser
coherente con lo que `ask` acepta. La SPA ya oculta la pantalla a `admin` (S13); su guarda impide montar la consulta.
Sin `throttle`: la caché de D3 acota las llamadas a Ollama a un descubrimiento por TTL sea cual sea la tasa.
La costura `auth:sanctum` de la ruta nueva lleva su propio pin (M14, D10): sacar la ruta del grupo debe hacer fallar
«Lista sin sesión» mientras «Modelos con Ollama disponible» sigue pasando (control: la ruta responde, solo falta la
autenticación).

- Rechazado: `throttle:assistant` en la lista. Gastaría el cupo de preguntas del usuario al abrir la pantalla.
- Rechazado: excluir `admin` en la API. Sería un cambio de autorización fuera de la fila S15 e incoherente con `ask`.
- Revisar si: S13 u otra fila cierra `ask` a `admin` en la API; la lista sigue el mismo cambio.

### D7. Registro: proveedor y modelo, nunca contenido

`AssistantQueryLogger::log($outcome, $trace, $provider, $model, $durationMs)`: `provider` = `name()` del proveedor
resuelto (conserva las pruebas de log de S7, incluidos los dobles), `model` = `id` de la elección o del valor por
defecto. Sin pregunta, respuesta, argumentos ni URL. Ningún `Log::` nuevo en `ModelCatalog`.

- Rechazado: registrar el descubrimiento de modelos. No hay decisión que depurar que no muestre la propia lista y
  agregaría líneas por cada fallo de caché.

### D8. SPA: consulta, selector y preferencia

- `api.ts`: `listAssistantModels()` (`GET /assistant/models`) y `askAssistant({ question, model })`.
- `queries.ts`: `useAssistantModels()` con `useQuery` (ADR-0003), clave `['assistant', 'models']`, `retry: false`
  (el fallo cae a `mock` de inmediato; spec «Fallo de la lista»), `staleTime: 30_000` (igual al TTL del servidor),
  `refetchOnWindowFocus` por defecto del cliente (false). Tras un 422 con `errors.model`, `invalidateQueries` de esa
  clave (spec «Modelo rechazado por el servidor»).
- `model-preference.ts`: clave `localStorage` **`dispensart.assistant.model`**; `readStoredModel()` y
  `storeModel(id)` envueltos en `try/catch` (sin almacenamiento → `null` / no-op, sin error visible); función pura
  `resolveModel(ids, stored, chosen)` → `{ id, storedMissing }`: elección de la visita si está en la lista; si no,
  el valor guardado si está en la lista; si no, `mock`. El valor guardado jamás se usa ni se pinta si no es un `id`
  de la lista recibida. Se escribe solo en el `onChange` del selector; volver a `mock` por caída no sobrescribe.
- `assistant-model-select.tsx`: `NativeSelect` + `NativeSelectOption` dentro de `Field`/`FieldLabel` "Modelo" (ADR-0004,
  sin dependencia nueva), sobre la caja de pregunta. Deshabilitado en carga y con `ask.isPending`. `FieldError` con
  `errors.model`; avisos (lista fallida, modelo ya no disponible) con `FieldDescription`, texto del módulo central.
- Etiquetas: `modelLabel(id)` en `assistant-labels.ts`: `mock` → "Simulado (sin red)"; `ollama:<n>` →
  "Ollama · <n>"; otro → "Modelo desconocido" (sin el texto recibido). El historial etiqueta con
  `entry.answer.model`, nunca con la selección actual.
- "Preguntar" deshabilitado mientras `models.isPending`; la caja admite escritura.

- Rechazado: `Select` de Radix/shadcn. El kit del repo ya trae `NativeSelect` y es accesible por teclado sin
  dependencia nueva.
- Rechazado: `sessionStorage`. El spec pide que la elección sobreviva a recargas y a nuevas pestañas.
- Rechazado: guardar la preferencia vía API. Contradice el spec (sin base) y abre una escritura.
- Revisar si: aparece una segunda preferencia de usuario; entonces un módulo de preferencias común.

### D9. Contrato OpenAPI y tipos

`composer openapi` (Scramble) tras cada cambio de contrato; `openapi.json` versionado. Luego
`npm --prefix software/web run api:types`; `api-types.ts` gana `AssistantModel` y `AskAssistantBody`. El 401 de la
lista y el `model` del 422 deben aparecer en el documento (docblock en el controlador si Scramble no lo infiere).

### D10. Pruebas y pins `[MUT]` (Tier A)

API: Pest contra PostgreSQL por la ruta real; Ollama solo en el borde con `Http::fake`; `Http::preventStrayRequests()`
ya rige en toda la suite (`tests/Pest.php`). "Sin red" se afirma con `Http::assertNothingSent()` además de la
prevención. "Ollama lento": prueba con `assistant.models.budget_seconds` reducido y un `Http::fake` de `/api/tags`
que duerme más que el plazo; afirma que `/api/show` no se llamó y que las opciones enviadas llevan `timeout` ≤ plazo
(segundo argumento `$options` del callback de `Http::fake`). Pruebas que necesitan dos estados de Ollama en una
misma prueba vacían el almacén del catálogo entre ambos (`Cache::store(config('assistant.models.cache_store'))->flush()`).

SPA: Vitest + Testing Library + MSW en el borde (`src/test/http.ts`). Toda prueba existente de la pantalla gana el
manejador por defecto de `GET /api/assistant/models` (las peticiones sin manejador fallan la prueba).
`localStorage` se limpia en `beforeEach`.

| Pin | Costura | Mutación | Prueba que debe FALLAR |
|---|---|---|---|
| M1 | filtro `tools` | aceptar todo modelo de `/api/tags` | «Modelo sin herramientas omitido» |
| M2 | Ollama caído → solo `mock` | relanzar la `ConnectionException` de `/api/tags` | «Ollama caído» |
| M3 | plazo de 2 s | quitar el corte por plazo restante antes de `/api/show` | «Ollama lento» |
| M4 | validación contra la lista | `AvailableModel` pasa siempre | «Modelo fuera de la lista» |
| M5 | proveedor por elección | la fábrica construye Ollama con `assistant.ollama.model` | «Pregunta con un modelo de Ollama disponible» |
| M6 | `mock` sin red | `contains('mock')` pasa por `available()` | «Modelo simulado sin red» |
| M7 | log sin contenido | agregar `question` al contexto del log | «Línea con un modelo de Ollama» |
| M8 | valor por defecto sin `model` | el resolver con `null` devuelve siempre `MockLlmProvider` | «Sin modelo usa el valor por defecto» |
| M9 | caché del catálogo | quitar `remember` (descubrir siempre) | «Pregunta con un modelo de Ollama disponible» (variante de caché, D3) |
| M10 | preferencia validada en la SPA | usar el valor guardado sin comprobar la lista | «Modelo guardado que ya no está disponible» |
| M11 | historial por `data.model` | etiquetar con la selección actual | «Cambiar el selector no reescribe el historial» |
| M12 | `localStorage` solo con el `id` | guardar además la pregunta | «Almacenamiento solo con el modelo» |
| M13 | persistencia al elegir | no escribir en `onChange` | «La elección sobrevive a una recarga» |
| M14 | `auth:sanctum` de la lista | sacar `GET /api/assistant/models` del grupo `auth:sanctum` en `routes/api.php` | «Lista sin sesión»; control con la mutación aplicada: «Modelos con Ollama disponible» PASA |

Cada pin: el árbol limpio y con commit (`git status --porcelain` vacío) antes de aplicar; el parche en
`openspec/changes/add-assistant-model-selector/mutants/M<n>.patch`; aplicar → la prueba nombrada FALLA → `git apply -R`
→ PASA. Parches PHP verificados con `php -l` sobre el archivo mutado antes de correr la prueba. Comando de M14, desde
la raíz: `docker compose -f software/compose.yaml --profile tools run --rm -e AI_PROVIDER=mock api-tools vendor/bin/pest
tests/Feature/Assistant/AssistantModelsEndpointTest.php --filter 'Lista sin sesión'` (FALLA aplicado, PASA restaurado)
y el mismo con `--filter 'Modelos con Ollama disponible'` (PASA aplicado: control).

## API contract

| Método y ruta | Middleware | Entrada | 200 | Rechazos | Prueba HTTP real |
|---|---|---|---|---|---|
| `GET /api/assistant/models` (nuevo) | `auth:sanctum` | — | `{"data":[{"id","provider","name"}]}`, `mock` primero | 401 `unauthenticated` | `tests/Feature/Assistant/AssistantModelsEndpointTest.php` (tarea 2.1) |
| `POST /api/assistant/ask` (cambia) | `auth:sanctum`, `throttle:assistant` | `question` (3–500), `model` opcional (`mock` \| `ollama:<name>` de la lista) | `data` de S7 + `model` (`id` que atendió) | 401, 419, 422 `validation_failed` (`errors.question` y/o `errors.model`), 429, 503 `assistant_unavailable` | `tests/Feature/Assistant/AssistantModelChoiceTest.php` (tarea 3.1) |

## Data impact

Sin migración, sin tabla, sin escritura en PostgreSQL. Estado nuevo fuera de la base: la caché `file` del catálogo
(lista de nombres de modelo, sin dato personal, TTL 30 s) y una clave `localStorage` con un `id` de modelo. Reversión:
revertir el código; el archivo de caché caduca solo y la clave del navegador se ignora.

## Risks / Trade-offs

1. [Caché desfasada: un modelo borrado en Ollama sigue en la lista hasta 30 s] → `ask` responde 503 como cualquier
   fallo de Ollama (spec «Ollama falla al responder con el modelo elegido»); la SPA ya refresca la lista ante el 422
   y el TTL es corto.
2. [Falso verde o falso rojo del arnés: `software/.env` fija `AI_PROVIDER=ollama` y `api-tools` lo hereda; una
   caché `file` compartida entre pruebas] → todo comando de verificación lleva `-e AI_PROVIDER=mock`; `phpunit.xml`
   fuerza `ASSISTANT_MODELS_CACHE_STORE=array`; fijar `AI_PROVIDER` en `phpunit.xml` queda como deuda propuesta.
3. [Entrada del cliente decide el destino de la pregunta (superficie Tier A)] → el `id` solo se acepta si figura en
   la lista que el servidor obtuvo de `OLLAMA_BASE_URL`; nombre restringido a un alfabeto seguro (D1); URL solo del
   entorno; pins M4, M5, M6; filtro previo de pacientes intacto y pinneado por «Pregunta sobre un paciente con un
   modelo de Ollama».

## Migration Plan

Sin datos que migrar. Despliegue: reconstruir imagen; `ASSISTANT_MODELS_CACHE_STORE` no se declara en compose ni en
`.env.example` (vale `file` por defecto). Rollback: revertir commits; la SPA vieja ignora `data.model` y no envía
`model`, la API vieja ignora `model` si llegara.

## Open Questions

Ninguna.
