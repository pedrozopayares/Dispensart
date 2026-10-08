# Cycle tiers

Inherited from the author's previous harness, adapted to Dispensart. Each cycle costs what the change can
break. **The gates do not change. What each gate reads does.**

Iron rules 1 and 4 stand at every tier: no gate is skipped, and only the final-auditor issues APPROVED.

---

## Choosing the tier

The Orchestrator assigns the tier at `/proposal` and records it in the change's `journal.md` beside GATE 1.
A tier is assigned by **what the change can break**, never by how many files it touches.

**Tier A — correctness-critical.** Any one of these is enough:

- a migration, a DB constraint or trigger, or any stock / kardex write path
- FEFO allocation, locking, idempotency (RN-01..RN-03, RN-06, RN-09)
- prescription limits, controlled-drug authorization, transfer state machine, segregation of duties (RN-04, RN-05, RN-07, RN-08)
- authentication, authorization, a permission surface, or patient-data handling (RN-10, Ley 1581)
- the AI assistant's tool surface or prompt-injection defense
- **the test harness's isolation, ordering or reporting** — anything that can manufacture a false green
  or a false red. A defect here produces a wrong ANSWER to *is this change correct* in every later run.

**Tier B — behavioural.** A surface with real logic and no Tier A trigger: an alert query (RN-11), a
filterable kardex view, a screen flow, a CI job. The failure mode is a wrong screen, not a wrong record.

**Tier C — presentational.** Copy, layout, spacing, labels, navigation chrome, docs. No branch, no
computation, no persistence. If a change has to reason about *when* to show something, it is not Tier C.

When a change spans tiers, **the highest trigger sets the tier for the whole change** — with one exception:
a Tier C *block* inside a Tier A change carries Tier C evidence for that block alone, and says so.

---

## What each tier pays

| | **A** | **B** | **C** |
|---|---|---|---|
| `design.md` | required | only if the change introduces a new seam | never |
| `[MUT]` pins | every seam that carries a property | only seams where a lazy implementation would still pass the tests | none |
| Suite runs | baseline + one closing full run + audit confirmation (max 3) | delta runs, plus **one** closing full run | delta runs only |
| Final audit | full | delta over the diff | delta over the diff |
| Re-audit after findings | yes | only if a finding touched code or tests | only if a finding touched code or tests |

**`[MUT]` pin** = mutation as verification: revert or break the seam, confirm the pinning test FAILS,
restore, confirm it PASSES. A test whose subject is never seeded, or whose mock pre-cooks the asserted
behavior, is a defect, not coverage. Mandatory pins for this product: FEFO order, expired-lot exclusion,
last-unit race, idempotent replay, each forbidden transfer transition, requester ≠ approver, auditor masking.

**Re-audit rule, all tiers.** A finding that touched only prose (`verification.md`, `journal.md`,
`design.md`, `DEBT.md`) is verified by **spec-validator**, not by a second final audit. A finding that
touched code or tests goes back to the final-auditor in delta mode.

---

## Evidence discipline (binding at every tier)

1. **`verification.md` is tables, not narrative.** One row per scenario: `| Scenario | Test | File:line |`.
   One row per mutation: `| n | mutation | Applied → FAILS m/k: test | Restored → PASSES k/k |`.
2. **No prose paragraph may carry a count.** A number lives in a table cell, beside the command that
   produced it, run against the tree it is committed with.
3. **A narrative paragraph is admissible only for a decision** — why a thing was built this way, what was
   rejected and on what measurement. It states no counts.
4. **Every zero carries a positive control** in the same cell.
5. **Every shell pattern sweep names `/usr/bin/grep`, never bare `grep`.** In the agent shell `grep` may be
   a function that re-execs `ugrep`; on a pattern past its complexity limits it writes to stderr and
   **matches nothing**, and a `| wc -l` masks the exit code. The recorded **0** is an artefact.
   This binds the recorded command text too. `find`, `awk` and `sed` need no pin.

   **5b. A recursive sweep does not enter a symlinked directory.** Neither `-r` nor `-R` follows a link met
   during recursion. If a swept tree may hold links, enumerate first:
   `find -L <root> -type f -print0 | xargs -0 /usr/bin/grep -c <pattern>`, and use **the number of files
   actually read**, compared against the number present, as the positive control.
6. `verification.md` is hard-wrapped: normalize whitespace before sweeping prose, or the sweep lies.

A change whose `verification.md` reads like an essay is over budget regardless of its tier.

---

## The transport anchor — a GATE 1 condition, binding at every tier

Defect class it closes: a `THEN` clause asserting an HTTP status or an error shape that no test on that
route exercises. `verification.md` maps *scenario to test by TITLE*, never by clause, so a wrong clause body
passes unnoticed. Statuses filled in from what a REST surface would *plausibly* do are often not what this
codebase does (e.g. idempotent replay answers the ORIGINAL status, not 409).

**The rule.** At `/proposal`, before `design.md` and before any code, run from the change folder:

```
/usr/bin/grep -rnE '^- \*\*THEN\*\*.*(HTTP|[^0-9](200|201|204|400|401|403|404|409|419|422|423|500)[^0-9]|5xx|invalid_|not_found|conflict|error code|refus|reject|forbidden|insufficient|expired)' specs/
```

**Every hit carries an inline anchor**: the route/controller file:line that produces that status (after
apply), or the live-spec scenario that already asserts it. A hit that cannot produce an anchor does not
ship — it is rewritten to what the route does, or dropped. `verification.md` gains a column for these:
**`clause → route file:line`**.

---

## Task-file discipline

- A task that a tier removes is **deleted from `tasks.md` at apply time, with the tier named as the reason**
  — never left unticked and never ticked without being done.
- The MUT total in the header is the count the tasks declare. If the audit adds pins, the header records
  both: *declared N, shipped M*.
