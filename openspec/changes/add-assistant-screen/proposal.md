# Proposal — add-assistant-screen

## Why

El asistente de S7 (parte C) solo se alcanza con `POST /api/assistant/ask` desde una herramienta HTTP. Pedido
del usuario (2026-10-08): "Agrega una pantalla de asistente de IA para poder probar como la usaría un usuario
de la aplicación". La pantalla muestra lo que el servidor ya garantiza: solo herramientas de lectura, permisos
por rol y pacientes fuera del modelo (RN-10).

## What Changes

- **Pantalla Asistente** (`/assistant`): caja de pregunta (3 a 500 caracteres), "Preguntar" con bloqueo del
  doble envío, ejemplos que rellenan la caja, y por pregunta: etiqueta en español distinta por `outcome`,
  `answer` del servidor como texto plano y las `tool_calls` con herramienta, argumentos y estado en español.
- **Historial** de las últimas preguntas solo en memoria de la pantalla; nunca en almacenamiento, URL ni consola.
- **Errores** por `code` del catálogo central (`validation_failed`, `too_many_requests`,
  `assistant_unavailable`, red, desconocido), conservando la pregunta.
- **Navegación**: "Asistente" en menú e inicio para toda sesión.

## Capabilities

### New Capabilities
- `assistant-screen`: preguntas al asistente con resultado por `outcome`, consultas hechas, historial en memoria, errores y ejemplos.

### Modified Capabilities
- `operator-workspace`: "Navegación por rol" e "Inicio con accesos del rol" — "Asistente" para toda sesión.
- `app-shell`: "Encabezado con sesión y cierre" — el inicio del `admin` muestra el acceso "Asistente".

## Impact

Tier B (disparador: flujo de pantalla con lógica; sin disparador de Tier A). Solo `software/web`: fila en la
tabla de pantallas, `src/features/assistant/`, textos centrales, dos códigos en el catálogo de errores,
pruebas Vitest + MSW. **Sin cambio de contrato** (`api-schema.ts` tal cual), sin dependencias nuevas, sin
backend ni devops. Partes B y C; RN-10. Requiere S6 y S7 archivados.

## Assumptions

1. **Roles**: visible para toda sesión, como la ruta (`auth:sanctum` sin capacidad en `routes/api.php`); cada
   herramienta autoriza en el servidor, así `medico` y `admin` ven "Sin permiso" (`not_permitted`).
2. **Hueco de contrato**: el filtro previo de pacientes responde `out_of_scope` igual que una pregunta ajena;
   la SPA lo muestra como "Fuera de alcance" más un aviso fijo de privacidad. No se replica el filtro en el
   navegador; distinguirlo exige un cambio de API aparte.
3. Historial: 10 entradas, la más reciente arriba. Enter envía, Shift+Enter salta línea; tras responder la
   caja se vacía, tras un error conserva el texto. Un ejemplo rellena sin enviar.
4. Herramienta fuera del catálogo: "Herramienta fuera del catálogo", nunca el nombre pedido por el modelo.
5. 429 con mensaje fijo, sin cuenta regresiva. Sin streaming ni E2E: humo manual en compose.
