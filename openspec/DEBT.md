# DEBT — no-carry ledger

Policy: no change archives with unsettled blocker/major debt. Minor debt carries max 1 change, then blocks.
An open major blocks the archive whether the change caused it or only discovered it. **Every debt requires
human review** — the Orchestrator keeps rows accurate and says which archives wait on what; it never
invents a category that lets them through.

Ids: `D-<shard>-<n>`, see `openspec/ID-CONVENTION.md`. On a merge conflict in this file: keep both rows.

## Open

| Id | Severity | Found in | Debt | Settle |
|---|---|---|---|---|
| D-auv-4 | minor | add-alerts (devops-implementer, 6.1) | El trabajo de staging de CI arranca de base vacía pero solo corre `smoke.sh`; `alerts-smoke.sh` (y los humos de dominio) nunca corren sobre una base recién creada. | add-delivery-pipeline: invocar los humos de dominio desde `smoke.sh` o desde el trabajo de staging |

## Settled

| Id | Settled in | How |
|---|---|---|
| D-auv-1 | add-stock-and-kardex (`1349b07`) | `SpaClient` envía `X-XSRF-TOKEN` solo en escrituras; mutación sobre `GET /api/auth/me` hace fallar `CsrfTest.php:57`; verificado por final-auditor. |
| D-auv-2 | add-dispensation (`fabb1c9`) | `created_at` del kardex con `clock_timestamp()`; prueba de transacciones solapadas falla con el valor anterior; verificado por final-auditor. |
| D-auv-3 | add-transfers (`102c1a4`) | Tope `[0-9]{1,18}` en los ids de `routes/api.php`; pruebas de 404 con id de 19 dígitos en productos y bodegas; verificado por final-auditor. |
