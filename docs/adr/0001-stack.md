---
status: accepted
date: 2026-10-07
---

# Stack: Laravel 13 / PHP 8.5, SPA React + Vite, PostgreSQL 16, Docker, GitHub Actions

La prueba exige PHP ≥ 8.2 + Laravel (estable vigente), React, Docker y GitHub o GitLab. Tomamos la última
versión estable de cada uno: Laravel 13.35 sobre PHP 8.5; React + TypeScript + Vite como SPA del mismo
origen detrás de Nginx; PostgreSQL 16 (lo nombra la prueba; elegido sobre MySQL por las restricciones
`CHECK`, `SELECT … FOR UPDATE` y los índices parciales, que respaldan RN-01/RN-03/RN-06 en la base de datos);
Docker Compose como único comando de ejecución; GitHub Actions porque el repositorio es público en GitHub
(runners gratuitos, Environments con revisores obligatorios para la compuerta manual a producción).

Consecuencias: la SPA usa autenticación por cookie de Sanctum (HttpOnly + CSRF) a través del proxy Nginx,
nunca un bearer token en `localStorage`. Todas las pruebas de integración corren contra PostgreSQL, nunca
SQLite.
