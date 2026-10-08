# Dispensart

Medication dispensing, lot-level inventory (FEFO) and inter-warehouse transfers for a healthcare provider
with several pharmacies and warehouses. Built as a senior full-stack technical test.

> Status: harness and planning in place; application code starts with the first OpenSpec change (`S0`).
> This README grows with the product. Final deliverable sections (run in one command, design trade-offs,
> assumptions, what was left out) land with the last slice.

## Stack

| Layer | Choice |
|---|---|
| Backend | PHP 8.5 · Laravel 13 · REST API · Sanctum SPA auth · Policies/Gates · Form Requests · API Resources |
| Frontend | React · TypeScript · Vite SPA · TanStack Query · Tailwind + shadcn/ui |
| Database | PostgreSQL 16 (CHECK constraints, row locks, append-only kardex) |
| Containers | Docker multi-stage (PHP-FPM + Nginx, non-root) · Docker Compose |
| CI/CD | GitHub Actions · Pint · Larastan · ESLint · Pest · Vitest |
| AI assistant | Laravel service behind an `LlmProvider` interface · read-only tools · `AI_PROVIDER=mock` by default |

Business rules and scope live in `openspec/config.yaml` and `openspec/ROADMAP.md`.

## Run the application

Requires Docker only.

```sh
git clone <repo-url> && cd Dispensart
docker compose up --build
```

Available once `S0` ships: URLs, ports and one synthetic user per role will be listed here.

## Repository layout

| Path | Holds |
|---|---|
| `software/` | all application code: `api/` (Laravel), `web/` (React SPA), `docker/`, `compose.yaml` |
| `openspec/` | spec-driven development: live specs, changes, roadmap, debt and retrospectives |
| `docs/adr/` | architecture decision records (ADR-0001..0005: stack, Pest, TanStack Query, UI kit, license) |
| `.claude/`, `CLAUDE.md` | AI harness: agents, skills, commands, hooks (see below) |
| `.github/workflows/` | CI/CD |
| `package.json` | dev tooling only — pins the OpenSpec CLI. Not the app |

## Development with the AI harness

The repo ships a [Claude Code](https://claude.com/claude-code) harness that drives an
[OpenSpec](https://github.com/Fission-AI/OpenSpec) spec-driven workflow: propose → apply → archive, with
human gates. Requires Node.js ≥ 20.19 and Claude Code.

```sh
claude            # from the repo root; accept workspace trust
/opsx:propose "…" # start a change
```

The first session installs the pinned OpenSpec CLI automatically (`npm ci`). Outside Claude:
`npx openspec list`. Process rules: `CLAUDE.md`, `openspec/CYCLE-TIERS.md`.

AI usage for this test is declared in `AI_USAGE.md` (added with the final slice).

## License

See `LICENSE`. Third-party skills vendored under `.claude/skills/` keep their own licenses:
`THIRD_PARTY_NOTICES.md`.
