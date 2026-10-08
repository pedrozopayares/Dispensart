# DEBT — no-carry ledger

Policy: no change archives with unsettled blocker/major debt. Minor debt carries max 1 change, then blocks.
An open major blocks the archive whether the change caused it or only discovered it. **Every debt requires
human review** — the Orchestrator keeps rows accurate and says which archives wait on what; it never
invents a category that lets them through.

Ids: `D-<shard>-<n>`, see `openspec/ID-CONVENTION.md`. On a merge conflict in this file: keep both rows.

## Open

| Id | Severity | Found in | Debt | Settle |
|---|---|---|---|---|
| D-auv-1 | minor | add-catalog-and-identity (final-auditor) | `software/api/tests/Support/SpaClient.php:126` envía `X-XSRF-TOKEN` en todo GET; la prueba "Lectura sin token CSRF" (`CsrfTest.php:57`) no puede fallar. | add-stock-and-kardex: cabecera solo en escrituras + mutación que demuestre el pin |

## Settled

| Id | Settled in | How |
|---|---|---|
