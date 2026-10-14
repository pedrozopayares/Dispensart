# Tasks — add-lot-intake (S18, tier A)

`[MUT]` declarados: M1–M6.

## 1. API — ingreso de lote

- [ ] 1.1 Migración `2026_10_14_000001_extend_audit_events_for_lot_intake` (D4) y caso `StockReceived` en `AuditAction`.
- [ ] 1.2 `StockPolicy::receive()` con `inventory.adjust` (D1); `StoreStockEntryRequest` con las reglas de la spec.
- [ ] 1.3 Acción `ReceiveLot` (D2) y `StockEntryController`; ruta `POST /api/stock-entries`.
- [ ] 1.4 Pruebas Pest: escenarios de `inventory` «Ingreso de lote» y de `audit-trail` «Ingresos de lote registrados», incluida la carrera con `RaceRunner`.
- [ ] 1.5 `[MUT]` M1 sin la regla de vencido → falla «Lote vencido rechazado»; M2 sin la comparación de vencimiento → falla «Vencimiento distinto»; M3 `insert` en vez de `insertOrIgnore` → falla la carrera; M4 sin fila de bitácora → falla «Ingreso registrado»; M5 `receive()` devuelve `true` → falla «Otro rol».
- [ ] 1.6 `openapi.json` exportado (`composer openapi`); petición en la colección de Postman; `check-coverage.sh` en verde.

## 2. SPA — formulario de ingreso

- [ ] 2.1 `api-schema.ts` regenerado (`npm run api:types`); `createStockEntry` y su mutación con `invalidateAfterStockWrite`.
- [ ] 2.2 `StockEntryForm` en Inventario, solo con `inventory.adjust`; textos en `strings.ts`.
- [ ] 2.3 Pruebas Vitest de los escenarios de `inventory-screen` «Registro de ingreso de lote».
- [ ] 2.4 `[MUT]` M6 formulario sin la condición de capacidad → falla «Otros roles sin formulario».

## 3. Cierre

- [ ] 3.1 Pint, Larastan, ESLint, typecheck; corrida de cierre de ambas suites.
- [ ] 3.2 `verification.md` en tablas; `journal.md`; fila S18 del ROADMAP.
