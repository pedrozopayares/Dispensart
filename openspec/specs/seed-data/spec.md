# seed-data Specification

## Purpose
Deja el sistema evaluable desde el primer arranque con datos exclusivamente sintéticos (§ 6, ADR-0005):
bodegas, productos, lotes y un usuario por rol, sembrados de forma automática y repetible sin duplicar ni
pisar datos existentes.

## Requirements

### Requirement: Catálogo semilla
La siembra SHALL crear las bodegas `Farmacia Central`, `Farmacia Urgencias` y `Bodega Hospitalización`; 6
productos, exactamente uno con `is_controlled` `true` y uno de ellos acetaminofén; y entre 2 y 3 lotes por
producto, con vencimientos relativos a la fecha de siembra en `America/Bogota`.

#### Scenario: Bodegas y productos sembrados
- **WHEN** se siembra una base vacía
- **THEN** existen exactamente las 3 bodegas con esos nombres y 6 productos, uno solo de control especial y uno cuyo nombre contiene `Acetaminofén`

#### Scenario: Lotes por producto
- **WHEN** se siembra una base vacía
- **THEN** cada uno de los 6 productos tiene 2 o 3 lotes, y el producto de control especial tiene al menos uno no vencido

#### Scenario: Distribución de vencimientos
- **WHEN** se siembra una base vacía
- **THEN** existe al menos un lote vencido, al menos uno que vence entre 1 y 29 días después de hoy, al menos uno que vence entre 31 y 90 días después de hoy, y al menos uno que vence a más de 90 días

#### Scenario: Sin datos reales
- **WHEN** se revisan los datos semilla en el código fuente
- **THEN** no contienen nombres de personas reales, documentos de identidad, registros sanitarios ni correos con dominio distinto de `dispensart.test`

### Requirement: Usuarios semilla
La siembra SHALL crear un usuario por rol con correos `auxiliar@dispensart.test`, `regente@dispensart.test`,
`medico@dispensart.test`, `auditor@dispensart.test` y `admin@dispensart.test`, todos con la contraseña de
`SEED_USER_PASSWORD` o, si falta, un valor por defecto solo de desarrollo documentado en `.env.example`.

#### Scenario: Un usuario por rol
- **WHEN** se siembra una base vacía
- **THEN** existen exactamente 5 usuarios, uno por cada rol, con los correos indicados

#### Scenario: Inicio de sesión con la contraseña documentada
- **WHEN** se inicia sesión como `regente@dispensart.test` con el valor por defecto documentado y sin `SEED_USER_PASSWORD` definida
- **THEN** la API responde HTTP 200 con `role` `regente_farmacia` [ancla: ruta `POST /api/auth/login`, archivo:línea al aplicar]

#### Scenario: Contraseña reemplazada por entorno
- **WHEN** se siembra con `SEED_USER_PASSWORD` definida y se intenta iniciar sesión con el valor por defecto
- **THEN** la API responde HTTP 422 con `code` `invalid_credentials`, y con el valor de la variable responde HTTP 200 [ancla: ruta `POST /api/auth/login`, archivo:línea al aplicar]

#### Scenario: Contraseña guardada con hash
- **WHEN** se lee la columna de contraseña de los usuarios semilla
- **THEN** ningún valor es igual a la contraseña en claro

#### Scenario: Producción sin contraseña explícita
- **WHEN** se siembra con `APP_ENV=production` y sin `SEED_USER_PASSWORD`
- **THEN** no se crea ningún usuario semilla, el catálogo sí se siembra, la siembra termina sin error y el log registra un aviso sin contraseña alguna

### Requirement: Siembra automática e idempotente
El arranque del stack SHALL sembrar después de migrar. Repetir la siembra SHALL crear solo lo que falte,
identificado por clave natural (código de bodega, código de producto, producto + código de lote, correo);
SHALL NOT duplicar, borrar ni modificar filas que ya existan.

#### Scenario: Arranque desde cero
- **WHEN** se ejecuta `docker compose -f software/compose.yaml up --build` con volúmenes vacíos
- **THEN** al quedar `api` sano existen los datos semilla y se puede iniciar sesión con cada usuario semilla

#### Scenario: Siembra repetida
- **WHEN** se siembra dos veces seguidas la misma base
- **THEN** la segunda corrida termina sin error y los conteos de bodegas, productos, lotes y usuarios son iguales a los de la primera

#### Scenario: Cambios del admin sobreviven al reinicio
- **WHEN** un `admin` renombra una bodega semilla y luego se reinicia el stack
- **THEN** la bodega conserva el nombre nuevo y no aparece una segunda bodega con el nombre original

#### Scenario: Filas no semilla intactas
- **WHEN** existe un producto creado por el admin y se vuelve a sembrar
- **THEN** ese producto sigue existiendo sin cambios
