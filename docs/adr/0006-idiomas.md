---
status: accepted
date: 2026-10-07
---

# Idiomas: código en inglés; comentarios, commits, documentación e interfaz en español

El equipo evaluador y los usuarios finales hablan español; el código se escribe sobre un framework y
librerías en inglés. Identificadores de código (clases, métodos, variables, tablas, columnas, rutas, códigos
de error) en inglés, para leerse igual que Laravel y React. Comentarios en el código, mensajes de commit,
documentación del proyecto (README, ADRs, documento de despliegue, AI_USAGE) y la prosa de los artefactos
OpenSpec en español. Cadenas de la interfaz y mensajes al usuario en español, centralizados en un módulo de
textos.

Excepciones: las palabras clave estructurales que las herramientas analizan se quedan en inglés (encabezados
de OpenSpec `## ADDED Requirements`, `#### Scenario:`, `WHEN`/`THEN`, `SHALL`; tipos de Conventional Commits
`feat`, `fix`, `chore`). Los archivos de instrucciones del harness (`CLAUDE.md`, `.claude/**`) siguen en
inglés: son instrucciones para el agente, no documentación del proyecto, y las skills de terceros ya vienen
en inglés.
