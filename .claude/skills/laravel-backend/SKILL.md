---
name: laravel-backend
description: Dispensart conventions for the Laravel 13 API — request lifecycle, where business logic lives, stock transactions and locking, idempotency, append-only kardex, Policies, API Resources, Pest arch tests, PostgreSQL-only tests. Use for any task under software/api.
---

# Laravel backend conventions (software/api)

Laravel 13 · PHP 8.5 · PostgreSQL 16 · Sanctum SPA auth · Pest (ADR-0002) · Pint · Larastan.
Official framework docs win over memory: `php artisan docs` or https://laravel.com/docs/13.x. When Laravel
Boost is installed (`composer require laravel/boost --dev`), its guidelines bind too.

## Request lifecycle (one shape, every endpoint)
```
Route → middleware (auth:sanctum, role) → FormRequest (validate + authorize via Policy)
      → Controller (≤ 10 lines: call one Action/Service, return Resource)
      → Action/Service (business rule, DB::transaction) → Eloquent models
      → API Resource (shape, masking by role) → JSON
```
- Controllers never contain `if` on business state. Pest `arch()` test pins it:
  `arch('controllers are thin')->expect('App\Http\Controllers')->not->toUse(['Illuminate\Support\Facades\DB'])`.
- One Action class per use case: `App\Actions\Dispensing\DispenseMedication`, `App\Actions\Transfers\ApproveTransfer`.
- Domain failures are typed exceptions (`InsufficientStock`, `LotExpired`, `TransferStateConflict`,
  `RequesterCannotApprove`) mapped in `bootstrap/app.php` to a stable JSON error shape:
  `{ "code": "insufficient_stock", "message": "<Spanish, user-facing>", "details": {...} }`.
  Codes are the frontend contract; messages come from `lang/es/errors.php`.

## Stock mutations (RN-01, RN-02, RN-03, RN-06)
```php
DB::transaction(function () {
    $stocks = Stock::query()
        ->where('warehouse_id', $w)->where('product_id', $p)
        ->whereHas('lot', fn ($q) => $q->where('expires_at', '>', today()))
        ->where('quantity', '>', 0)
        ->join('lots', ...)->orderBy('lots.expires_at')->orderBy('stocks.id')   // FEFO, then id
        ->lockForUpdate()
        ->get();
    // allocate; throw InsufficientStock before any write if total < requested
    // decrement each stock row; insert one kardex movement per row touched
});
```
- Lock order is always FEFO then id → no deadlocks between concurrent dispensations.
- DB backs every invariant: `CHECK (quantity >= 0)`, `UNIQUE (warehouse_id, product_id, lot_id)`,
  trigger on `kardex_movements` that raises on UPDATE/DELETE. Code is the first guard, not the only one.
- Never `decrement()` outside the locked transaction. Never compute stock from kardex at request time.

## Idempotency (RN-09)
- `POST /api/dispensations` requires `Idempotency-Key` (UUID). Table `idempotency_keys(key, user_id,
  request_hash, response_code, response_body, created_at)` with unique `(key, user_id)`.
- Same key + same hash → replay stored response, same status, no new movement.
  Same key + different hash → 422 `idempotency_key_reused`.
- The key row is inserted inside the same transaction as the stock change.

## Transfers (RN-07, RN-08)
- `enum TransferStatus: string` + `TransferTransition` table of allowed moves. Illegal move → 409
  `transfer_state_conflict`. Requester id == approver id → 403 `segregation_of_duties`.
- Dispatch: stock leaves origin, kardex `salida_traslado`. Receive: enters destination, kardex
  `entrada_traslado`; shortfall creates `transfer_discrepancies` rows and status `RECIBIDO_PARCIAL`.

## Patients and privacy (RN-10)
- `PatientResource` masks for role `auditor` (`documento` → `****1234`, name → initials).
- `PatientAccessLog` row on every patient read (observer or middleware, not in controllers).
- `Log::` never receives model instances or request bodies that hold patient fields.

## Tests
- Pest, PostgreSQL service (never SQLite: CHECK semantics and `FOR UPDATE` differ).
- Feature tests hit real HTTP (`$this->actingAs($u)->postJson(...)`), one per endpoint per outcome.
- Concurrency test: two real DB connections (`DB::connection('pgsql_b')`), no `RefreshDatabase`
  transaction wrapper for that test (`DatabaseTruncation` trait instead); assert exactly one success and
  `quantity >= 0`.
- Factories for everything; seeders idempotent (`updateOrCreate`) so `migrate --seed` is repeatable.

## Quality gates (run from software/api)
`vendor/bin/pint --test` · `vendor/bin/phpstan analyse` (level set in `phpstan.neon`) · `php artisan test --parallel`.
