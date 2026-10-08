# Roadmap — prueba técnica FARTMAR

**APROBADO por el usuario el 2026-10-07.** Orden y alcance fijos. Cada tajada es un cambio OpenSpec. El
orden sigue el peso de evaluación (§ 10 de la prueba) y las dependencias. Presupuesto: 6–8 horas efectivas.

Este archivo es el **punto de reanudación** del autopiloto: una sesión limpia lee la columna Estado y
continúa desde la primera tajada no archivada. El Orchestrator actualiza Estado en cada transición.

| # | Change id | Alcance | Partes / reglas | Tier | Estado |
|---|---|---|---|---|---|
| S0 | `add-project-skeleton` | Esqueletos de Laravel 13 y SPA React bajo `software/`, compose, `/health` y `/ready`, esqueleto de CI con lint y tests | D, E | B | propuesto |
| S1 | `add-catalog-and-identity` | Usuarios con 5 roles, autenticación Sanctum SPA, Policies, bodegas, productos, lotes, datos semilla | A, § 6 | A | pendiente |
| S2 | `add-stock-and-kardex` | Existencias por bodega+producto+lote, kardex solo inserción, restricciones en DB, ajustes | A, RN-01, RN-06 | A | pendiente |
| S3 | `add-dispensation` | Pacientes, prescripciones, asignación FEFO, bloqueo, idempotencia, coautorización de control especial, bitácora de acceso, enmascarado | A, RN-02..05, RN-09, RN-10 | A | pendiente |
| S4 | `add-transfers` | Máquina de estados de traslados, despacho/recepción, discrepancias, segregación de funciones | A, RN-07, RN-08 | A | pendiente |
| S5 | `add-alerts` | Vencimiento ≤ 90 días, stock bajo mínimo por bodega | A, RN-11 | B | pendiente |
| S6 | `add-operator-screens` | Pantallas Dispensación, Traslados, Inventario, Kardex | B | B | pendiente |
| S7 | `add-inventory-assistant` | Interfaz de proveedor LLM, mock/Ollama, herramientas de solo lectura, defensa contra inyección, set de evaluación + script | C | A | pendiente |
| S8 | `add-delivery-pipeline` | CI/CD completo (imágenes, staging, producción con compuerta), documento de despliegue, README, AI_USAGE.md | D, E | B | pendiente |

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
