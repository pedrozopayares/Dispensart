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
