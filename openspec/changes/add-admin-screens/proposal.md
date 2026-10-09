# Proposal — add-admin-screens (S13)

## Why

La prueba (§ 3) da a `admin` la «gestión de usuarios y catálogos» y la API ya lo permite desde S1, pero la SPA no
le ofrece pantalla alguna: hoy `admin` solo ve "Asistente", que no figura entre sus funciones y cuyas herramientas le
niegan toda consulta.

## What Changes

- «Usuarios» (`/users`), solo `admin`: lista con nombre, correo y rol en español; alta con nombre, correo,
  contraseña enmascarada y rol; errores de campo del servidor junto al campo.
- «Catálogo» (`/catalog`), solo `admin`: bodegas y productos con lista, alta y edición en contexto.
- Ambas en menú e inicio de `admin`; otro rol ve el aviso de permiso existente, sin consultas.
- **BREAKING (solo SPA)**: `admin` deja de ver "Asistente" en menú e inicio, y `/assistant` le muestra el aviso de
  permiso. Los demás roles no cambian.
- Sin cambios de API, base de datos, OpenAPI ni despliegue.

## Capabilities

### New Capabilities
- `admin-screens`: pantallas de administración de la SPA (usuarios y catálogo) sobre los endpoints existentes.

### Modified Capabilities
- `assistant-screen`: «Pantalla Asistente para toda sesión» pasa a «…para los roles de operación» y excluye a `admin`.
- `operator-workspace`: «Navegación por rol» e «Inicio con accesos del rol» suman "Usuarios" y "Catálogo" y quitan
  "Asistente" a `admin`.
- `app-shell`: «Encabezado con sesión y cierre», escenario de inicio del `admin`.

## Impact

- Código: solo `software/web`.
- Contrato: `/api/users` (GET, POST), `/api/warehouses` y `/api/products` (GET, POST, PATCH), tal cual. Sin huecos.
- Partes A y B, § 3. RN-05: la marca de control especial se edita aquí y la sigue aplicando el servidor al
  dispensar. RN-10: sin datos de pacientes. Sin escritura de stock: RN-03 y RN-09 no aplican.
- Tier B: flujos de pantalla con escritura sobre endpoints existentes. Sin disparador A: sin migración, Policies y
  rutas de la API intactas, sin datos de pacientes ni superficie del asistente. La guarda de la SPA no autoriza.

## Supuestos

1. `POST /api/assistant/ask` sigue aceptando a `admin`; negarlo en la API es otro cambio, Tier A. Aquí solo se
   oculta en la SPA.
2. La API no edita, desactiva ni borra usuarios, ni borra bodegas o productos: la pantalla tampoco.
3. Mensajes de campo en español desde la API; la SPA solo valida localmente los obligatorios.
4. Contraseña enmascarada, sin confirmación (la API no la pide); nunca en lista, URL, almacenamiento ni consola.
5. Listas sin paginar, como las devuelve la API.
6. Presentación en blanco se envía como `null` y se lista "Sin presentación".
7. La exclusión de `admin` se resuelve en la tabla única de pantallas: sin costura nueva, sin `design.md`.
8. El `## Purpose` de `assistant-screen` se corrige en el spec vivo al archivar.
