# Proposal — add-inventory-assistant (S7)

## Why

La parte C (10 % de § 10) pide un asistente de inventario en lenguaje natural sin SQL libre, sin datos de
pacientes, resistente a inyección y evaluable sin llaves de API; § 7 exige el script de evaluación. S1–S5 ya
dejan roles, existencias, alertas y traslados consultables.

## What Changes

- `POST /api/assistant/ask`: pregunta y respuesta en español, con `outcome` decidido por el servidor
  (`answered`, `no_results`, `out_of_scope`, `not_permitted`, `unknown`) y las herramientas usadas.
- 4 herramientas de solo lectura (function calling): lotes por vencer, existencias, stock bajo mínimo, estado
  de traslados. Autorizan con la capacidad de S1 del usuario y corren en transacción de solo lectura.
- Proveedor por `AI_PROVIDER`: `mock` (por defecto, determinista, sin red) u `ollama` (local). Cambiarlo no
  toca el resto. Cero costo.
- Defensa contra inyección: instrucciones fijas; observaciones y resultados viajan como dato no confiable;
  herramienta desconocida o argumentos fuera de esquema no se ejecutan; ciclo acotado; respuesta sin
  herramienta exitosa se reemplaza por el mensaje de fuera de alcance.
- Filtro previo: preguntas sobre pacientes, prescripciones o con número de documento no llegan al modelo.
  Ninguna herramienta lee pacientes. Pregunta y respuesta fuera del log (RN-10).
- Conjunto de evaluación versionado (≥ 10 preguntas) y `php artisan assistant:eval` aislado de los datos
  operativos; corre en CI con `mock`.
- Documento breve del asistente: proveedor, compromiso del modelo local, defensa, evaluación.

## Capabilities

### New Capabilities
- `inventory-assistant`: endpoint, herramientas por rol, resultados, exclusión de pacientes, defensa contra
  inyección, proveedor configurable y modo simulado.
- `assistant-evaluation`: conjunto de evaluación, comando de aciertos y su corrida en CI.

### Modified Capabilities
- Ninguna. Reutiliza capacidades y rechazos de S1, consultas de S2/S5 y traslados de S4.

## Impact

Tier A. Parte C, § 7, § 8; RN-10, RN-11, apoyo de RN-01 y RN-07. `software/api`, compose, `.env.example`, un
paso de CI, `software/docs/`. Sin migraciones ni escritura de stock: sin escenarios de concurrencia ni
idempotencia. Requiere S1–S5 archivados.

## Assumptions

1. Sin panel en la SPA (fuera de la fila S7); candidato de ROADMAP.
2. Endpoint abierto a toda sesión; cada herramienta autoriza: `medico` y `admin` reciben `not_permitted`.
3. Sin proveedor de pago; `ollama` usa un Ollama del anfitrión, sin servicio nuevo en compose ni CI.
4. Observaciones de traslados llegan al modelo como dato delimitado (proveedor local, sin campos de paciente);
   un proveedor externo debería excluirlas.
5. Ventana por defecto 90 días (RN-11), rango 1–365; vencidos con existencia aparecen marcados.
6. 20 preguntas por minuto por usuario (429 `too_many_requests`); proveedor caído → 503
   `assistant_unavailable`; sin fila en la bitácora de operaciones.
7. Solo español.
