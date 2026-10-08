# DEBT — no-carry ledger

Policy: no change archives with unsettled blocker/major debt. Minor debt carries max 1 change, then blocks.
An open major blocks the archive whether the change caused it or only discovered it. **Every debt requires
human review** — the Orchestrator keeps rows accurate and says which archives wait on what; it never
invents a category that lets them through.

Ids: `D-<shard>-<n>`, see `openspec/ID-CONVENTION.md`. On a merge conflict in this file: keep both rows.

## Open

| Id | Severity | Found in | Debt | Settle |
|---|---|---|---|---|
| D-auv-2 | minor | add-stock-and-kardex (final-auditor) | `kardex_movements.created_at` usa `CURRENT_TIMESTAMP` (inicio de transacción); con transacciones solapadas el listado de `GET /api/kardex` (`InventoryQuery.php:50`, orden por `created_at`) puede mostrar la historia fuera del orden de `balance_after`. | add-dispensation: migración a `clock_timestamp()` + prueba de transacciones solapadas en orden inverso |

## Settled

| Id | Settled in | How |
|---|---|---|
| D-auv-1 | add-stock-and-kardex (`1349b07`) | `SpaClient` envía `X-XSRF-TOKEN` solo en escrituras; mutación sobre `GET /api/auth/me` hace fallar `CsrfTest.php:57`; verificado por final-auditor. |
