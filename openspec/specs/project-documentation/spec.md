# project-documentation Specification

## Purpose
Entrega al evaluador, en español y sin abrir el código, cómo ejecutar, desplegar, revertir y respaldar el
sistema, qué se decidió y qué quedó fuera, y cómo se usó la IA; cada comando y cada cita son verificables
(partes D y E, § 7, § 8).

## Requirements

### Requirement: Documento de despliegue de una página
`software/docs/deployment.md` SHALL describir en ≤ 550 palabras la estrategia de despliegue (imágenes por
digest, staging, aprobación), el rollback y el respaldo y restauración de la base con `pg_dump` y
`pg_restore` contra el stack de compose. Cada comando SHALL ejecutarse tal como está escrito. SHALL NOT
contener credenciales distintas de la contraseña de desarrollo local marcada como tal.

#### Scenario: Extensión y secciones
- **WHEN** se cuenta el documento con `wc -w` y se buscan sus encabezados
- **THEN** tiene ≤ 550 palabras y secciones de estrategia, rollback y respaldo/restauración

#### Scenario: Respaldo y restauración de ida y vuelta
- **WHEN** con el stack sembrado se ejecuta el respaldo documentado, luego `down -v` y `up`, y luego la restauración documentada
- **THEN** el conteo de filas del kardex y de existencias es igual al previo al respaldo y `/ready` responde HTTP 200 [ancla: SH › API lista — live spec `service-health`]

#### Scenario: Rollback con esquema incompatible
- **WHEN** se lee la sección de rollback
- **THEN** indica cómo volver al digest anterior y qué hacer si esa versión no es compatible con migraciones ya aplicadas (restaurar el respaldo previo)

#### Scenario: Sin credenciales reales
- **WHEN** se buscan contraseñas, tokens o `APP_KEY` con valor en el documento
- **THEN** solo aparecen variables de entorno o la contraseña de desarrollo local marcada como tal

### Requirement: README de entrega
`README.md` SHALL indicar en español: el comando único de arranque desde un clon limpio, URLs y un usuario
sintético por rol, decisiones de diseño con ≥ 3 compromisos, las librerías relevantes y por qué, supuestos,
qué quedó fuera y por qué, la ubicación de la documentación de la API y el comando de evaluación del
asistente.

#### Scenario: Comando único desde un clon limpio
- **WHEN** en un clon limpio sin `.env` se ejecuta literalmente el comando de arranque del README
- **THEN** `db`, `api` y `web` llegan a `healthy` y la URL indicada responde HTTP 200 con la SPA [ancla: RE › Arranque desde cero sin .env — live spec `runtime-environment`]

#### Scenario: Compromisos de diseño
- **WHEN** se lee la sección de decisiones
- **THEN** hay ≥ 3 compromisos, cada uno con lo elegido, lo descartado y el costo aceptado

#### Scenario: Fuera de alcance con motivo
- **WHEN** se lee la sección de lo que quedó fuera
- **THEN** cada ítem trae su motivo, y aparecen la lectura de bitácoras por el `auditor`, el rol de base de mínimo privilegio para la app y toda fila abierta de `openspec/DEBT.md` al momento del cierre

#### Scenario: Rutas citadas existentes
- **WHEN** se extrae cada ruta relativa del repositorio citada en el README
- **THEN** cada una existe en el árbol, incluidas la especificación OpenAPI y el documento de despliegue

#### Scenario: Ruta citada inexistente
- **WHEN** el README cita una ruta relativa que no existe en el árbol
- **THEN** la verificación de rutas del README falla y la tarea no se cierra

#### Scenario: Comando de evaluación del asistente
- **WHEN** se ejecuta literalmente el comando de evaluación del README con el stack en marcha y `AI_PROVIDER=mock`
- **THEN** termina con código 0 y reporta los aciertos

### Requirement: Declaración de uso de IA
`AI_USAGE.md` SHALL declarar en español las herramientas de IA usadas, para qué tareas, un ejemplo
aceptado y uno rechazado o corregido con su porqué (§ 8). Cada ejemplo SHALL citar la ruta de un journal o
verificación del repositorio donde consta el hecho. SHALL NOT incluir ejemplos sin fuente.

#### Scenario: Secciones exigidas
- **WHEN** se buscan las secciones del archivo
- **THEN** existen herramientas, tareas, ejemplo aceptado y ejemplo rechazado o corregido, este con su motivo

#### Scenario: Ejemplos con fuente verificable
- **WHEN** se sigue la ruta citada por cada ejemplo
- **THEN** el archivo existe y contiene el hecho descrito

#### Scenario: Ejemplo sin fuente
- **WHEN** un ejemplo no cita ruta, o cita una ruta inexistente
- **THEN** la verificación del archivo falla y la tarea no se cierra

### Requirement: Guía de la colección de Postman
`README.md`, en su sección de documentación de la API, y una guía en `software/docs/postman/` SHALL indicar en
español la ubicación de la colección y del entorno, cómo importarlos en Postman, que la sesión SPA la resuelve
el script de colección, que la contraseña del entorno es el valor por defecto solo de desarrollo y cómo
sobrescribirla, y el comando `newman` con versión exacta que se ejecuta tal como está escrito.

#### Scenario: Comando documentado ejecutable
- **WHEN** con el stack recién levantado según el README se ejecuta literalmente el comando `newman` de la guía desde la raíz del repo
- **THEN** termina con código 0

#### Scenario: Rutas de la guía existentes
- **WHEN** se extrae cada ruta relativa del repositorio citada en la guía y en la sección de API del README
- **THEN** cada una existe en el árbol, incluidas la colección, el entorno y la guarda de cobertura

#### Scenario: Contraseña marcada como solo de desarrollo
- **WHEN** se busca la contraseña en la guía
- **THEN** solo aparece `dispensart-dev-only`, marcada como valor por defecto solo de desarrollo y no secreto, junto a la forma de sobrescribirla

#### Scenario: Versión desalineada
- **WHEN** la versión de `newman` del README, la de la guía o la del workflow difieren
- **THEN** la verificación de la guía falla y la tarea no se cierra
