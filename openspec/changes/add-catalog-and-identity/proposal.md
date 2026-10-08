# Proposal — add-catalog-and-identity (S1)

## Why

Ninguna operación de negocio existe sin saber quién actúa, con qué rol y sobre qué bodega, producto y lote.
La prueba (parte A, § 3, § 6) exige Sanctum, autorización por rol con Policies/Gates y un catálogo sembrado
con datos sintéticos; S2–S7 dependen de los tres.

## What Changes

- Usuarios con exactamente uno de 5 roles, respaldado por restricción en la base.
- Sesión Sanctum SPA por cookie HttpOnly + CSRF (login, logout, usuario actual), sin bearer (ADR-0001),
  con límite de intentos fallidos.
- Mapa rol → capacidades de § 3 con denegación por defecto; forma JSON única de rechazo.
- `admin` lista y crea usuarios, y crea/modifica bodegas y productos; todo rol lee el catálogo.
- Lotes de solo lectura con estado de vencimiento calculado.
- Siembra sintética, idempotente y automática al arrancar.
- SPA: login y shell con sesión. OpenAPI de los endpoints nuevos.

## Capabilities

### New Capabilities
- `identity-access`: roles, capacidades, sesión, CSRF, rechazos y gestión de usuarios.
- `catalog`: bodegas, productos y lotes, con integridad en la base.
- `seed-data`: datos semilla sintéticos e idempotentes.
- `app-shell`: login en la SPA, rutas protegidas, encabezado con sesión, expiración.

### Modified Capabilities
- Ninguna hoy; tarea 0.1 añade `runtime-environment` cuando S0 se archive.

## Impact

Tier A. Partes A y B (parcial), § 3, § 6; base de RN-01, RN-05 y RN-10. Toca `software/api`, `software/web`
y el arranque de `api`. Fuera: existencias y kardex (S2), pacientes y bitácora (S3), traslados (S4),
alertas y stock mínimo (S5), pantallas de operación (S6).

## Assumptions

1. Códigos de rol literales de § 3; no se traducen.
2. Stock mínimo por bodega queda en S5.
3. Sin escritura de lotes: nacen con las entradas de S2.
4. Sin borrado, cambio de rol ni desactivación (exigen auditoría de S3); candidato de deuda.
5. Lote vencido si `expires_on` ≤ hoy en `America/Bogota`.
6. El mapa declara ya las capacidades futuras de § 3; auxiliar y regente tienen `patients.view` para dispensar.
7. `SEED_USER_PASSWORD` con valor por defecto solo de desarrollo, inerte en producción (design D10).
8. Listados sin paginación en S1.
9. Sin pantallas de administración: el admin usa la API documentada.
