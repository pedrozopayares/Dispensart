# Spec Delta — runtime-environment

## MODIFIED Requirements

### Requirement: Secretos fuera del repositorio
`software/.env.example` SHALL documentar cada variable que lee el stack, con marcadores de posición y
`APP_KEY` vacía. Los archivos `.env` SHALL estar ignorados por git. Toda credencial SHALL poder
reemplazarse por entorno; los valores por defecto de credencial SHALL limitarse a la lista cerrada
`DB_PASSWORD` y `SEED_USER_PASSWORD`, contraseñas de desarrollo local marcadas como tales en
`.env.example`. Con `APP_ENV=production` el valor por defecto de `SEED_USER_PASSWORD` SHALL NOT usarse.

#### Scenario: Plantilla completa
- **WHEN** se compara cada variable `${...}` referida en `software/compose.yaml` con `software/.env.example`
- **THEN** todas aparecen en la plantilla y `APP_KEY` está vacía

#### Scenario: .env ignorado por git
- **WHEN** se ejecuta `git check-ignore software/.env software/api/.env software/web/.env`
- **THEN** git confirma las tres rutas como ignoradas

#### Scenario: Credencial reemplazable
- **WHEN** se arranca con `DB_PASSWORD` o `SEED_USER_PASSWORD` definida en el entorno
- **THEN** el stack usa ese valor y no el de desarrollo local: `db` y `api` con `DB_PASSWORD`, los usuarios semilla con `SEED_USER_PASSWORD`

#### Scenario: Lista cerrada de valores por defecto
- **WHEN** se revisan `software/compose.yaml`, `software/.env.example` y la configuración de la API en busca de credenciales con valor por defecto
- **THEN** solo `DB_PASSWORD` y `SEED_USER_PASSWORD` lo tienen, ambas marcadas como solo desarrollo, y compose no define valor por defecto para `SEED_USER_PASSWORD`

#### Scenario: Valor por defecto de la siembra inerte en producción
- **WHEN** el stack arranca con `APP_ENV=production` y sin `SEED_USER_PASSWORD`
- **THEN** no se crea ningún usuario semilla con el valor por defecto de desarrollo
