---
status: accepted
date: 2026-10-07
---

# Stack: Laravel 13 / PHP 8.5, React + Vite SPA, PostgreSQL 16, Docker, GitHub Actions

The test mandates PHP ≥ 8.2 + Laravel (current stable), React, Docker and GitHub/GitLab. We take the latest
stable of each: Laravel 13.35 on PHP 8.5, React + TypeScript + Vite as a same-origin SPA behind Nginx,
PostgreSQL 16 (named by the test; chosen over MySQL for `CHECK` constraints, `SELECT … FOR UPDATE` and
partial indexes, which back RN-01/RN-03/RN-06 at the database), Docker Compose as the single run command,
GitHub Actions because the repository is public on GitHub (free runners, Environments with required
reviewers for the manual production gate).

Consequences: the SPA uses Sanctum cookie auth (HttpOnly + CSRF) through the Nginx proxy, never a bearer
token in `localStorage`. All feature tests run against PostgreSQL, never SQLite.
