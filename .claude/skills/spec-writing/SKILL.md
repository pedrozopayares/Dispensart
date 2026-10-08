---
name: spec-writing
description: Spec engineer craft — scenario grammar, negative-scenario mandate, task discipline for OpenSpec changes. Use when writing proposals, spec deltas, or tasks.md.
---

# Spec Writing

## Scenario grammar
`#### Scenario: <name>` then WHEN/THEN prose. One observable behavior per scenario. THEN states behavior + HTTP code (API) or visible UI state (frontend).

## Mandates
- **Negative-scenario mandate**: every requirement ≥1 negative scenario. A requirement only proven by its happy path is unwritten.
- **Write-operation rule**: enumerate ALL outcomes — happy + each rejection (validation, auth, not-found, conflict, idempotent-replay). Each rejection: observable behavior + code.
- **Partial-input rule**: state behavior for missing/partial data explicitly. Silence = defect.
- **Idempotency scenarios**: anything stock-touching gets replay scenarios (same `Idempotency-Key` twice → same response, no duplicate kardex movement) — RN-09.
- **Concurrency scenarios**: anything that decrements stock gets a last-unit race scenario (two requests, one unit → one success, one 409, stock never negative) — RN-03.
- **Entity states**: cover special states (transfer BORRADOR/SOLICITADO/APROBADO/EN_TRANSITO/RECIBIDO/RECIBIDO_PARCIAL/ANULADO; prescription vigente/vencida/agotada; lot vigente/vencido). Every forbidden transition is a negative scenario — RN-07.
- **Business-rule traceability**: each requirement names the RN-xx rule(s) it enforces.

## UI requirements
- Loading/error/empty states are acceptance criteria, not decoration.
- Error copy is user-comprehensible for the evaluated cases: insufficient stock, expired lot, missing authorization.
- Double-submit prevention is an acceptance criterion for every write action.
- Spanish strings named in scenario (centralized strings module).
- **Entry-point criterion**: capability reachable from navigation. Orphan route = incomplete requirement.
- Experiential criteria: layout fidelity, in-context interaction, reuse of existing components.

## Tasks
- Atomic ≤1h. Each cites the scenario(s) it satisfies.
- **Intent, not enumeration**: "implement rejection scenarios of Requirement X" not a list of if-branches.
- Order: database → domain → application → infrastructure → api → web UI → devops.
- Every new/changed endpoint → one real-HTTP feature test task (Laravel HTTP test against PostgreSQL, not SQLite).

## Proposals
≤1 page. Why, what changes, impact, assumptions. One AskUserQuestion round max (≤4 questions); non-blocking ambiguities → Assumptions section.
