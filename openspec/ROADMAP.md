# Roadmap — prueba técnica FARTMAR

**APROBADO por el usuario el 2026-10-07.** Orden y alcance fijos. Cada tajada es un cambio OpenSpec. El
orden sigue el peso de evaluación (§ 10 de la prueba) y las dependencias. Presupuesto: 6–8 horas efectivas.

Este archivo es el **punto de reanudación** del autopiloto: una sesión limpia lee la columna Estado y
continúa desde la primera tajada no archivada. El Orchestrator actualiza Estado en cada transición.

| # | Change id | Alcance | Partes / reglas | Tier | Estado |
|---|---|---|---|---|---|
| S0 | `add-project-skeleton` | Esqueletos de Laravel 13 y SPA React bajo `software/`, compose, `/health` y `/ready`, esqueleto de CI con lint y tests | D, E | B | archivado |
| S1 | `add-catalog-and-identity` | Usuarios con 5 roles, autenticación Sanctum SPA, Policies, bodegas, productos, lotes, datos semilla | A, § 6 | A | archivado |
| S2 | `add-stock-and-kardex` | Existencias por bodega+producto+lote, kardex solo inserción, restricciones en DB, ajustes | A, RN-01, RN-06 | A | archivado |
| S3 | `add-dispensation` | Pacientes, prescripciones, asignación FEFO, bloqueo, idempotencia, coautorización de control especial, bitácora de acceso, enmascarado | A, RN-02..05, RN-09, RN-10 | A | archivado |
| S4 | `add-transfers` | Máquina de estados de traslados, despacho/recepción, discrepancias, segregación de funciones | A, RN-07, RN-08 | A | archivado |
| S5 | `add-alerts` | Vencimiento ≤ 90 días, stock bajo mínimo por bodega | A, RN-11 | A (usuario, 2026-10-08) | archivado |
| S6 | `add-operator-screens` | Pantallas Dispensación, Traslados, Inventario, Kardex | B | B | archivado |
| S7 | `add-inventory-assistant` | Interfaz de proveedor LLM, mock/Ollama, herramientas de solo lectura, defensa contra inyección, set de evaluación + script | C | A | archivado |
| S8 | `add-delivery-pipeline` | CI/CD completo (imágenes, staging, producción con compuerta), documento de despliegue, README, AI_USAGE.md | D, E | B | archivado |
| S9 | `fix-assistant-eval-and-health-docs` | `/health` y `/ready` en `openapi.json`; `assistant:eval` compara la bodega y el producto resueltos, no el texto (salda D-auv-7) | A, C, D | B | archivado |
| S10 | `add-sensitive-operation-audit` | Bitácora de auditoría para creación de usuarios y ajustes de stock (`audit_events`, migración de sus CHECK) | A, RN-09 | A | archivado |
| S11 | `use-registry-mirror-in-ci` | El CI descarga las imágenes base de Docker Hub (postgres, php, composer, node, nginx) desde el espejo `mirror.gcr.io`, sin credenciales; en local sigue Docker Hub | D | B | bloqueado: GATE 1 en pausa por el usuario (2026-10-09) |
| S12 | `apply-brand-palette` | Paleta de color de la IPS en la SPA: tokens de tema (claro y oscuro) con azul marino `#232955`, verde lima `#a9cd43`, fondo `#f8f9fa` y texto `#212b51`, con contraste AA; sin logo ni nombre comercial | B | C | archivado |
| S13 | `add-admin-screens` | Pantallas de administración para `admin` sobre la API existente: usuarios (listar, crear con rol) y catálogo (bodegas y productos: listar, crear, editar); `admin` deja de ver el Asistente en menú, inicio y ruta | A, B, § 3 | B | archivado |
| S14 | `fix-expiry-badge-contrast` | Salda D-auv-8: el badge «Vence en N días» del inventario cumple contraste WCAG AA, con prueba | B | C | archivado |
| S15 | `add-assistant-model-selector` | Selector de modelo en la pantalla Asistente: `mock` siempre disponible y elegido por defecto; modelos de Ollama solo si Ollama responde y el modelo está descargado y admite herramientas; la API lista los modelos disponibles y `ask` acepta el modelo elegido validado contra esa lista; la elección sobrevive a recargas en el navegador (sin base de datos) | A, B, C | A | archivado |
| S16 | `add-postman-collection` | Colección y entorno de Postman en `software/docs/postman/` sobre el contrato OpenAPI: sesión Sanctum SPA resuelta por script (cookie CSRF, `X-XSRF-TOKEN`, `Origin`), cuerpos de ejemplo y aserciones por flujo (roles, dispensación FEFO e idempotencia, control especial, traslados con discrepancia, alertas y kardex, asistente con modelo, permisos denegados, catálogo y usuarios) y guarda de cobertura frente a las operaciones del OpenAPI; ejecución con `newman` en local y en el staging simulado del CI | A, D, E, § 7 | B | en curso |
| S17 | `fix-test-env-isolation` | Salda D-auv-9: `phpunit.xml` fija `AI_PROVIDER=mock` y `OLLAMA_MODEL` para que la suite no herede `software/.env`, con prueba | E | A (usuario, 2026-10-10) | archivado |

Valores de Estado: `pendiente` · `propuesto` (GATE 1 registrado) · `en curso` (apply) · `aprobado` (GATE 2) ·
`archivado` · `bloqueado: <motivo>`.

## Preaprobación de GATE 1 para el lote S0–S8

Registrada el 2026-10-07 por instrucción del usuario: *"lanzar el roadmap completo en autopiloto desde una
sesión limpia"*. GATE 1 queda preaprobado para cada tajada de esta tabla **mientras se cumplan las cuatro
condiciones**; si una falla, el Orchestrator se detiene y pregunta:

1. La propuesta cubre exactamente el alcance de la fila (ni más ni menos). Scope contiguo que aparezca se
   fila como deuda, no se barre dentro.
2. El `spec-validator` devuelve `VALID` y el ancla de transporte (`CYCLE-TIERS.md`) no deja hits sin anchor.
3. El tier asignado coincide con la columna Tier. Un tier más alto exige detenerse y preguntar.
4. Ninguna decisión congelada (`docs/adr/0001..0006`) ni regla RN-xx se debilita.

Partición permitida sin preguntar: S3 puede dividirse en `add-dispensation` y
`add-controlled-drug-authorization` (RN-05 + bitácora) si el spec-engineer la juzga mayor de una tajada.
Ambas heredan la preaprobación y la fila S3 anota la división.

GATE 2 no se preaprueba nunca: lo emite `final-auditor`.

## Preaprobación de GATE 1 para S9–S10

Registrada el 2026-10-09 por instrucción del usuario: *"Haz los dos. Autopiloto"*, tras la auditoría de
cobertura contra la prueba. Mismas cuatro condiciones que el lote S0–S8, con el alcance y el tier de las
filas S9 y S10. GATE 2 sigue siendo del `final-auditor`.

## Preaprobación de GATE 1 para S12

Registrada el 2026-10-09 por instrucción del usuario: *"Aplica el camino 1, en autopiloto, inmediatamente"*, tras
proponer aplicar la paleta pública del sitio de la IPS a los tokens de tema de la SPA. Mismas cuatro condiciones, con
el alcance y el tier de la fila S12. GATE 2 sigue siendo del `final-auditor`.

## Preaprobación de GATE 1 para S13

Registrada el 2026-10-09 por instrucción del usuario: *"Parece que el admin no debe tener acceso al Asistente, porque
no aparece en el documento y porque de todas formas, su rol no tiene permisos que le permitan consultar a la IA.
Aprobado S13"*. Mismas cuatro condiciones, con el alcance y el tier de la fila S13. GATE 2 sigue siendo del
`final-auditor`.

## Preaprobación de GATE 1 para S14

Registrada el 2026-10-09 por instrucción del usuario: *"Resuelve D-auv-8 también"*. Mismas cuatro condiciones, con el
alcance y el tier de la fila S14. GATE 2 sigue siendo del `final-auditor`.

## Preaprobación de GATE 1 para S15

Registrada el 2026-10-09 por instrucción del usuario: *"Este ajuste es en autopiloto hasta terminar"*, sobre el pedido
del selector de modelo en la pantalla Asistente. Mismas cuatro condiciones, con el alcance y el tier de la fila S15.
GATE 2 sigue siendo del `final-auditor`.

## Preaprobación de GATE 1 para S16–S17

Registrada el 2026-10-10 por instrucción del usuario: *"Aplica S16, junto con D-auv-9"*, tras proponer la colección de
Postman (S16) y explicar D-auv-9 (S17). Mismas cuatro condiciones, con el alcance y el tier de cada fila. S17 se archiva
antes que S16 para que D-auv-9 no bloquee el archivo de S16. GATE 2 sigue siendo del `final-auditor`.

## Restricciones del entorno (vinculan a S0 y S8)

- Puertos del host ocupados en la máquina del autor por otros proyectos: 80, 5173, 5432, 8000, 8080, 8081,
  8088, 5433, 9000. Compose expone **solo dos** servicios al host, con puertos configurables por `.env` y
  estos valores por defecto: `WEB_PORT=8090` (Nginx: SPA + proxy `/api`) y `DB_PORT=5434` (PostgreSQL, solo
  para herramientas locales). La API no se expone al host; se alcanza a través de Nginx.
- Local hay PHP 8.4 y Composer 2.8 (sirven para `composer create-project`); la ejecución real es en Docker
  con PHP 8.5.
- Rama de trabajo: `dev`. `main` es del usuario. Commits en español, cortos (ADR-0006).

## Cómo lanzar el autopiloto

Desde una sesión limpia de Claude Code en la raíz del repo: `/autopilot`. El comando contiene el
procedimiento completo (`.claude/commands/autopilot.md`).
