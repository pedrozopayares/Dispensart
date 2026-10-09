# DEBT — no-carry ledger

Policy: no change archives with unsettled blocker/major debt. Minor debt carries max 1 change, then blocks.
An open major blocks the archive whether the change caused it or only discovered it. **Every debt requires
human review** — the Orchestrator keeps rows accurate and says which archives wait on what; it never
invents a category that lets them through.

Ids: `D-<shard>-<n>`, see `openspec/ID-CONVENTION.md`. On a merge conflict in this file: keep both rows.

## Open

| Id | Severity | Found in | Debt | Settle |
|---|---|---|---|---|

## Settled

| Id | Settled in | How |
|---|---|---|
| D-auv-1 | add-stock-and-kardex (`1349b07`) | `SpaClient` envía `X-XSRF-TOKEN` solo en escrituras; mutación sobre `GET /api/auth/me` hace fallar `CsrfTest.php:57`; verificado por final-auditor. |
| D-auv-2 | add-dispensation (`fabb1c9`) | `created_at` del kardex con `clock_timestamp()`; prueba de transacciones solapadas falla con el valor anterior; verificado por final-auditor. |
| D-auv-3 | add-transfers (`102c1a4`) | Tope `[0-9]{1,18}` en los ids de `routes/api.php`; pruebas de 404 con id de 19 dígitos en productos y bodegas; verificado por final-auditor. |
| D-auv-4 | add-delivery-pipeline (`59db1e7`) | `smoke.sh` corre los 6 humos de dominio en staging sobre base nueva; runs 37782508066 y 37785649410; verificado por final-auditor. |
| D-auv-5 | add-delivery-pipeline (`3c8e709`) | Filtro previo bloquea lo dispensado/prescrito a una persona y «fórmula»; prueba HTTP con la pregunta del auditor falla sin el cambio; residuo aceptado en README (Compromiso 6); verificado por final-auditor. |
| D-auv-6 | add-delivery-pipeline (`df71b72`) | Nombres de herramienta saneados a `[A-Za-z0-9_-]` (64) en `ToolCallRecord`; pruebas de log y respuesta HTTP fallan sin el cambio; verificado por final-auditor. |
| D-auv-7 | fix-assistant-eval-and-health-docs (`225e911`, `93a5195`) | `EvaluationMatcher` compara bodega y producto resueltos con `CatalogResolver`; M4–M6 hacen fallar las pruebas del comparador; verificado por final-auditor. |
| D-auv-8 | fix-expiry-badge-contrast (`a41100d`) | Insignia «Vence en N días» con tokens `warning`/`warning-foreground` (6.39:1 claro, 8.28:1 oscuro); prueba de contraste y controles C1–C2 fallan sin el cambio; verificado por final-auditor. |
