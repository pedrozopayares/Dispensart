# Retrospectives — process-learning ledger

Append-only. One entry per lesson: date | change-id | what failed/worked | rule adjustment.

---

## Inherited lessons (author's previous harness, distilled)

- **Tests that cannot fail are the recurring defect.** Caught only by mutating the source, never by
  reading the assertion. Rule: behavioral pins report the mutation outcome (reverted → FAILS,
  restored → PASSES); auditors verify by mutation. A test whose subject is never seeded is a defect.
- **Agent types register only from the session's project root.** Starting a session one directory off left
  every role running as `general-purpose`. Rule here: `.claude/` lives at the repo root; start sessions there.
- **Cycles spent on wording move nothing.** Rule: wording is not a defect (Iron rule 12); the record is
  written once, at close.

---

## Autopilot run S0–S8 (2026-10-07 → 2026-10-08)

- 2026-10-07 | all | Agent files created mid-session were not registered; a workaround with `general-purpose`
  agents was rejected by the user. | Agent types register at session start only: after adding
  `.claude/agents/*`, restart the session from the repo root before delegating.
- 2026-10-08 | add-catalog-and-identity, add-dispensation, add-transfers | Every OpenAPI change broke CI drift
  until the SPA types were regenerated. | Any task that touches `openapi.json` ends with `npm run api:types` and
  commits `api-schema.ts` in the same push.
- 2026-10-08 | add-dispensation / add-transfers | A concurrent agent truncated `routes/api.php` and swept another
  agent's uncommitted routes. | Parallel agents stage explicit paths only, run `git status` before each commit,
  and never write the same file; two backend agents never share `software/api` at once.
- 2026-10-08 | add-alerts, add-inventory-assistant | Mutant patches with syntax errors "failed" for the wrong
  reason; `expect([ns…])->not->toUse()` in Pest passed while violated (false green). | Every mutant patch passes
  `php -l` first, and every mutant pairs with a positive control; arch rules never group namespaces under a
  negated expectation.
- 2026-10-08 | add-alerts | The roadmap tier (B) contradicted a CYCLE-TIERS trigger (migration with CHECK). |
  Assign the tier per row from CYCLE-TIERS triggers when writing the roadmap, not from the feature's feel.
- 2026-10-08 | all | spec-validator drifted between runs on "todos" citations and Cimiento verify commands. |
  The accepted convention is written down; findings contradicting it are closed under Iron rule 12.
- 2026-10-08 | add-alerts, add-delivery-pipeline | Audits probed a stale running image or a renamed DB and
  reported environment noise as findings. | Rebuild the touched service before any stack probe; confirmation
  runs copy `.env.example` to `.env` and expect only `DatabaseConnectionTest` to fail on a renamed DB.
