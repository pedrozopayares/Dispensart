---
status: accepted
date: 2026-10-07
---

# Repositorio público bajo MIT; el enunciado de la prueba se mantiene privado

El repositorio es público desde el primer push. El código propio es MIT (compatible con las skills
Apache-2.0 y MIT incluidas, ver `THIRD_PARTY_NOTICES.md`). El enunciado original de la prueba (`project/`)
está en `.gitignore` y nunca se publica; el alcance se reformula en `openspec/config.yaml` y
`openspec/ROADMAP.md`. Ningún secreto, `APP_KEY` real ni credencial entra a git: `.env.example` solo lleva
marcadores de posición, los secretos de CI viven en GitHub Environments, el proveedor de IA arranca en
`mock` y todos los datos semilla son sintéticos.
