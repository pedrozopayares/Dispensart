# Spec Delta — project-documentation

## ADDED Requirements

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
