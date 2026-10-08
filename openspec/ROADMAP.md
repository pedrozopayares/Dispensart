# Roadmap — FARTMAR technical test

DRAFT — slice order proposed by the Orchestrator, pending user approval. Each slice = one OpenSpec change.
Order follows evaluation weight (test § 10) and dependency. Budget: 6-8 effective hours.

| # | Change id | Scope | Test parts / rules | Tier |
|---|---|---|---|---|
| S0 | `add-project-skeleton` | Laravel 13 + React SPA scaffolds under `software/`, compose, health/ready, CI lint+test skeleton | D, E | B |
| S1 | `add-catalog-and-identity` | users + 5 roles, Sanctum SPA auth, Policies, warehouses, products, lots, seed data | A, § 6 | A |
| S2 | `add-stock-and-kardex` | stock per warehouse+product+lot, append-only kardex, DB constraints, adjustments | A, RN-01, RN-06 | A |
| S3 | `add-dispensation` | patients, prescriptions, FEFO allocation, locking, idempotency, controlled-drug co-authorization, access log, masking | A, RN-02..05, RN-09, RN-10 | A |
| S4 | `add-transfers` | transfer state machine, dispatch/receive, discrepancies, segregation of duties | A, RN-07, RN-08 | A |
| S5 | `add-alerts` | expiry ≤ 90 days, below-minimum stock per warehouse | A, RN-11 | B |
| S6 | `add-operator-screens` | Dispensación, Traslados, Inventario, Kardex screens | B | B |
| S7 | `add-inventory-assistant` | LLM provider interface, mock/Ollama, read-only tools, injection defense, eval set + script | C | A |
| S8 | `add-delivery-pipeline` | full CI/CD (images, staging, gated production), deployment doc, README, AI_USAGE.md | D, E | B |
