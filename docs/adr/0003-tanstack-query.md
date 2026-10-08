---
status: accepted
date: 2026-10-07
---

# TanStack Query as the SPA's server-state layer

Four operator screens need server data with cache, invalidation after writes (a dispensation changes
inventory and kardex), pending state to block double submit (explicitly graded) and uniform error
handling. TanStack Query gives all four; hand-rolled `fetch` + `useEffect` would re-implement them.

Constraint it must respect: one `Idempotency-Key` per user intent, generated when the form is submitted
the first time and reused on every retry of that same intent (RN-09). A new key per attempt would defeat
idempotency. Considered: SWR (weaker mutation story), RTK Query (drags Redux in), no library.
