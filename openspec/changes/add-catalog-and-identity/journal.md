# Journal — add-catalog-and-identity (S1)

Append-only. Dueño: Orchestrator. Los agentes agregan su sección al volver.

## 2026-10-07 — Orchestrator: apertura

- shard = auv (reclamado en add-project-skeleton, misma sesión)
- Fase: /proposal, en paralelo con la propuesta de S0 (solo artefactos de spec; el apply espera el archivo de S0).
- Tier objetivo según ROADMAP: A.

## 2026-10-07 — spec-engineer: proposal, deltas, tasks borrador

Producido: `proposal.md`; deltas `specs/identity-access`, `specs/catalog`, `specs/seed-data`, `specs/app-shell`
(capacidades nuevas, `## Purpose` incluido); `tasks.md` borrador (8 grupos, `[MUT]` declarados M1–M11).
`openspec validate add-catalog-and-identity --strict`: válido. Ancla de transporte barrida con
`/usr/bin/grep`: todo hit lleva `[ancla: …]`; control positivo con una línea sin ancla plantada en el
scratchpad, detectada.

Supuestos (9, en `proposal.md` § Assumptions): códigos de rol literales de § 3; stock mínimo a S5; sin
escritura de lotes (S2); sin borrado, cambio de rol ni desactivación; lote vencido si `expires_on` ≤ hoy en
`America/Bogota`; mapa de capacidades completo de § 3 declarado ahora (auxiliar y regente con
`patients.view`); `SEED_USER_PASSWORD` con valor solo de desarrollo y sin usuarios demo en producción;
listados sin paginación; sin pantallas de administración.

Preguntas abiertas para el architect:
1. Dónde vive el login con sesión: `api` con estado (Sanctum `statefulApi`) o grupo `web` bajo `/api`; fija
   el estado observable del escenario "Origen ajeno a la SPA" (hoy sin código HTTP a propósito).
2. Cómo reactivar CSRF en pruebas (el framework la omite con `runningUnitTests`); sin eso 5.2 y M5 son
   falso verde.
3. Router de la SPA: si S0 no lo trae, es dependencia nueva (criterio de architect).
4. Herramienta gratuita de lint OpenAPI (5.16) y ubicación del archivo.
5. S0 dice "único valor por defecto de credencial = contraseña de BD"; `SEED_USER_PASSWORD` añade otro
   valor por defecto solo de desarrollo. Conciliar al aplicar S0/S1.

Candidatos de deuda (el Orchestrator asigna id): cambio de rol / desactivación de usuario con auditoría
(tras S3); RN-05 puede necesitar un segundo `regente_farmacia` semilla (decisión de S3).

## 2026-10-07 — architect: design.md + refinamiento de tasks

Producido: `design.md` (D1–D11, tabla API contract de 13 filas, Data impact, 3 riesgos). Respuestas a las preguntas abiertas:
1. Login en `routes/api.php` con `statefulApi()`. Origen ajeno o sin `Origin`/`Referer` → **403 `forbidden`** sin
   consultar base, sin limitador, sin `Set-Cookie` (evita 500 por sesión ausente y oráculo de contraseñas sin CSRF).
   Sin bearer: `getAccessTokenFromRequestUsing` nulo, sin tabla `personal_access_tokens`.
2. `App\Http\Middleware\ValidateCsrfToken` con `runningUnitTests()` = `false`, registrado en Sanctum y en `web`;
   `tests/Support/SpaClient` con tarro de cookies y `forgetGuards()` por petición (sin él M4 no prueba nada).
3. `react-router` v7 modo datos. Rechazadas: sin router, TanStack Router, wouter.
4. `dedoc/scramble` (require-dev) → `software/api/openapi.json` + `@redocly/cli` fijado en raíz + chequeo de deriva en CI.
   Rechazado YAML a mano (deriva silenciosa); queda como plan B acotado a 30 min.
5. Conflicto con S0: delta `MODIFIED` de `runtime-environment` (lista cerrada `DB_PASSWORD`, `SEED_USER_PASSWORD`;
   inerte en producción) → nueva tarea 0.1 para el spec-engineer, tras archivar S0. Valor por defecto único en
   `config/dispensart.php`; compose sin default.

Otras decisiones: `role varchar(32)` + `users_role_check` sin default (un default concedería permisos en silencio);
PG `ENUM` rechazado (no reversible limpio). `app.timezone` queda UTC (contrato de log de S0); reloj de negocio
`BusinessCalendar` en `America/Bogota` — corrige el borrador de 4.2. Sin `spatie/laravel-permission`, sin
`Gate::before`, sin `laravel-lang`, sin axios ni MSW. Respuestas con envoltura `data`.

Segundo `regente_farmacia`: **no se siembra en S1** (la spec fija exactamente 5 usuarios); S3 lo añade con un
`MODIFIED` de `seed-data` si su prueba de RN-05 lo exige. Recomendación: nota en la fila S3 del ROADMAP, no deuda.
Candidato de deuda confirmado: cambio de rol / desactivación con auditoría (tras S3).

Tasks: nueva 0.1 (precondición), 7.3 (CI de OpenAPI); 1.1, 2.3, 4.2, 5.1, 5.16, 6.1–6.3, 7.1 precisados con
referencias a design. Orden 0→5 api secuencial → 6 web ∥ 7.1 → 7.2–7.3 → 8. api→web obligatorio (contrato nuevo).

Riesgos: falso verde en sesión/CSRF (D2 + M4/M5 + humo 7.2); Scramble incompatible con Laravel 13 (plan B);
limitador por IP tras dos proxies (`TrustProxies` en rangos privados, revisar en S8).

## 2026-10-07 — spec-engineer: corrección de hallazgos del spec-validator (solo prosa)

- `tasks.md`: 1.4, 5.16, 7.3, 8.1, 8.2 marcadas "Cimiento" con comando de verificación; 3.2, 3.3 y 7.2 citan
  escenarios por nombre; definición de "Cimiento" en la cabecera. Orden del architect y tarea 0.1 intactos.
- `proposal.md` recortado a una página; Assumptions conservados (7 alineado con design D10). Specs sin cambios.
- `openspec validate add-catalog-and-identity --strict`: válido.

## 2026-10-07 — Orchestrator: GATE 1

- spec-validator: `VALID add-catalog-and-identity` tras corregir citas de tareas y largo de la propuesta
  (hallazgos solo de prosa, re-verificados por spec-validator). Ancla de transporte: 68 hits, 68 con ancla.
- Condición 1 (alcance exacto de S1): OK. Usuarios y 5 roles, Sanctum SPA, Policies, bodegas, productos,
  lotes, siembra. Login + shell de SPA: mínimo para que la autenticación sea alcanzable. OpenAPI de los
  endpoints nuevos: exigencia de la parte A sobre lo que esta tajada expone.
- Condición 2 (VALID + anclas): OK.
- Condición 3 (tier): A, coincide con ROADMAP.
- Condición 4 (ADR / RN): `react-router` y Scramble son dependencias nuevas, no reabren ADR; ninguna RN se debilita.
- GATE 1: preaprobado (ROADMAP 2026-10-07), condiciones 1-4 OK, tier A
- Candidato de deuda (cambio de rol con auditoría) queda fuera de alcance; se filará si una tajada lo
  descubre como necesario. Apply espera el archivo de S0.

## 2026-10-07 — spec-engineer: tarea 0.1

- Delta `MODIFIED` `specs/runtime-environment/spec.md`, "Secretos fuera del repositorio" (encabezado idéntico al
  de la spec viva): lista cerrada `DB_PASSWORD` + `SEED_USER_PASSWORD`, inerte en producción (design D10). Bloque
  completo copiado; "Credencial reemplazable" ampliado; dos escenarios nuevos. 0.1 `[x]`. Validación estricta: válida.

## 2026-10-07 — Orchestrator: /apply

- S0 archivado; specs vivas `runtime-environment`, `service-health`, `ci-pipeline`.
- Preflight: stack compose sano (db, api, web `healthy`). Línea base de suites (corrida 1 de 3):
  backend Pest 26 pasan / 143 aserciones; frontend Vitest 8 pasan.
- Insumo heredado de S0: las pruebas fuerzan `APP_KEY` vacía; Sanctum con sesión por cookie necesita una
  clave de prueba generada en tiempo de ejecución.
- Tarea 0.1 (delta MODIFIED sobre `runtime-environment`) delegada al spec-engineer.
