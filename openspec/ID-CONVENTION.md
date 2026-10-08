# ID convention — sharded ledger ids

Inherited from the author's previous harness. Keeps sequential ledger ids safe to allocate from two or more
sessions running at the same time, on different machines or worktrees, without coordination between them.

## The problem this closes

A flat counter (`D-7` = "one more than D-6") requires reading the whole ledger and being the only writer.
Two concurrent sessions both read `D-6`, both file `D-7`, and the two rows silently describe different
debts. The collision does not announce itself: git merges both rows, every later reference to `D-7` is ambiguous, and the ledger — a
queue a human is meant to work from — stops being trustworthy.

## The format

    D-<shard>-<n>

- `<shard>` — three lowercase base36 characters, **one per session**, fixed for that session's whole life.
- `<n>` — a counter **local to that shard**, starting at `1`, incremented per id the session allocates.

Examples: `D-xef-1`, `D-xef-2`, `D-q7k-1` — these are illustrations, not claims; the checks below exclude
this file so its examples never read as taken shards.

Because no two sessions share a shard, no two sessions can produce the same id, and neither has to read
the other's rows to allocate. The counter stays sequential and human-sized inside a session, which is the
only place a person ever counts.

## Claiming a shard

The Orchestrator claims the session's shard **once**, at the first allocation of the session — not at
session start, so sessions that file nothing cost nothing.

1. Derive it: the last three alphanumeric characters of the session id, lowercased
   (`session_01VvzLkYbyo2R8msfmMKnXEF` → `xef`). If no session id is available, generate one:
   `openssl rand -hex 2 | cut -c1-3`.
2. Verify it is free, under `/usr/bin/grep` (see `CYCLE-TIERS.md` § Evidence discipline 5):

       /usr/bin/grep -rn --include="*.md" --exclude=ID-CONVENTION.md -oE "D-<shard>-[0-9]+" openspec .claude | sort -u

   Zero hits = free, claim it. Any hit = a past session already owns it; generate a new one and re-verify.
3. Record the claim in the journal entry that files the first id: `shard = <shard>`.

Highest used number for a shard, when a session resumes its own shard after a compaction or a restart:

    /usr/bin/grep -rho --include="*.md" --exclude=ID-CONVENTION.md -E "D-<shard>-[0-9]+" openspec .claude | sort -t- -k3 -n | tail -1

## Who allocates

The Orchestrator, and only the Orchestrator, allocates ids — this is unchanged. Leaf agents report a debt
in prose on return and do not name it; the Orchestrator files the row and assigns the id. Every debt still
requires human review (`DEBT.md`); sharding changes how a row is named, nothing about
what a row costs.

Matching an id:

    D-[a-z0-9]{3}-[0-9]+

## Scope

Applies to any ledger id a session allocates on its own, today `D-` in `openspec/DEBT.md`.

Does **not** apply to:

- `ADR-NN` and roadmap `SN` — user-owned, allocated in one place by one person, no concurrency.
- Audit observation numbers (`O1`, `O2`, …) and task numbers inside `tasks.md` — already scoped to one
  change's one document, so two sessions cannot collide on them.
- Change ids, which are kebab names, not counters.

## Merges

Two sessions appending rows to the same `DEBT.md` table still produce a git conflict in that region. The
resolution is mechanical and always the same: **keep both rows**. There is no id to reconcile, which is
the whole point.
