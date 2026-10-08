# DEBT — no-carry ledger

Policy: no change archives with unsettled blocker/major debt. Minor debt carries max 1 change, then blocks.
An open major blocks the archive whether the change caused it or only discovered it. **Every debt requires
human review** — the Orchestrator keeps rows accurate and says which archives wait on what; it never
invents a category that lets them through.

Ids: `D-<shard>-<n>`, see `openspec/ID-CONVENTION.md`. On a merge conflict in this file: keep both rows.

## Open

| Id | Severity | Found in | Debt | Settle |
|---|---|---|---|---|
| D-auv-5 | minor | add-inventory-assistant (final-auditor) | `QuestionPreFilter.php:13` solo bloquea `pacient|prescrip|receta` y 7+ dígitos: «¿Qué le dispensaron a <nombre>?» llega al proveedor con el nombre. Con mock u Ollama local nada sale de la máquina. | add-delivery-pipeline (saldo de deuda): ampliar el patrón (`dispens`, `fórmula`) con prueba que falle sin el cambio; acotar la cláusula absoluta del spec a datos que el sistema lee |
| D-auv-6 | minor | add-inventory-assistant (final-auditor) | Nombres de herramienta inventados por el modelo en llamadas rechazadas entran sin sanear al log y a `tool_calls` (`ToolCallRecord.php:25`, `AssistantQueryLogger.php:21`). | add-delivery-pipeline (saldo de deuda): restringir a `[A-Za-z0-9_-]` en `ToolCallRecord`, como `ToolResultEnvelope::attribute`, con prueba |
| D-auv-4 | minor | add-alerts (devops-implementer, 6.1) | El trabajo de staging de CI arranca de base vacía pero solo corre `smoke.sh`; `alerts-smoke.sh` (y los humos de dominio) nunca corren sobre una base recién creada. | add-delivery-pipeline: invocar los humos de dominio desde `smoke.sh` o desde el trabajo de staging |

## Settled

| Id | Settled in | How |
|---|---|---|
| D-auv-1 | add-stock-and-kardex (`1349b07`) | `SpaClient` envía `X-XSRF-TOKEN` solo en escrituras; mutación sobre `GET /api/auth/me` hace fallar `CsrfTest.php:57`; verificado por final-auditor. |
| D-auv-2 | add-dispensation (`fabb1c9`) | `created_at` del kardex con `clock_timestamp()`; prueba de transacciones solapadas falla con el valor anterior; verificado por final-auditor. |
| D-auv-3 | add-transfers (`102c1a4`) | Tope `[0-9]{1,18}` en los ids de `routes/api.php`; pruebas de 404 con id de 19 dígitos en productos y bodegas; verificado por final-auditor. |
