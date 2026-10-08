---
status: accepted
date: 2026-10-07
---

# Public repository under MIT; the test statement stays private

The repository is public from the first push. Own code is MIT (compatible with the vendored Apache-2.0 and
MIT skills, see `THIRD_PARTY_NOTICES.md`). The original test statement (`project/`) is git-ignored and never
published; scope is restated in `openspec/config.yaml` and `openspec/ROADMAP.md`. No secret, real
`APP_KEY` or credential enters git: `.env.example` holds placeholders only, CI secrets live in GitHub
Environments, the AI provider defaults to `mock`, and all seed data is synthetic.
