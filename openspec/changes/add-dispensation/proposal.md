# Proposal — add-dispensation (S3)

## Why

La dispensación pesa 20 % (§ 10) y concentra los defectos que motivan la prueba: sobreventa, lotes vencidos
entregados y doble salida por reintentos. Exige pacientes y prescripciones (RN-04) y trae el primer dato de
salud del sistema, protegido desde el día uno (RN-10, Ley 1581).

## What Changes

- Pacientes sintéticos: búsqueda y ficha con prescripciones por `patients.view` de S1 (`admin` sin acceso);
  enmascarado en el servidor para `auditor`.
- Prescripciones del `medico`: estado calculado `vigente`/`vencida`/`agotada`, saldo por ítem que la base
  impide superar (RN-04).
- `POST /api/dispensations/preview`: lotes que FEFO asignaría, sin escribir ni bloquear (parte B).
- `POST /api/dispensations`: FEFO sobre lotes no vencidos de una bodega (RN-01, RN-02), todo o nada, una
  transacción con bloqueo en orden determinista (RN-03), un `salida_dispensacion` por lote (RN-06, S2).
- `Idempotency-Key` obligatoria: reintento devuelve la respuesta original; otro cuerpo se rechaza (RN-09).
- Control especial: la petición lleva credenciales de un segundo usuario con `controlled_drugs.authorize`,
  distinto del dispensador; nunca guardadas ni registradas (RN-05).
- Bitácoras de acceso a pacientes y de operaciones sensibles, inmutables; sin datos personales en logs,
  rechazos ni excepciones (RN-10).

**Sin partición.** La permitida llevaría la bitácora a otra tajada y dejaría lecturas de pacientes sin
registro (debilita RN-10). RN-05 en línea no añade endpoints ni estados.

## Capabilities

### New Capabilities
- `patients`: búsqueda, ficha, acceso por rol, enmascarado, datos fuera de logs, siembra.
- `prescriptions`: creación por médico, estado, saldo acumulado, siembra.
- `dispensation`: vista previa y asignación FEFO, concurrencia, idempotencia, coautorización RN-05.
- `audit-trail`: bitácoras de acceso y de operaciones sensibles, solo inserción.

### Modified Capabilities
- Ninguna. Reutiliza capacidades y rechazos de S1; existencias, kardex e `insufficient_stock` de S2.

## Impact

Tier A. Parte A; RN-01..RN-06, RN-09, RN-10. Solo `software/api`: migraciones, triggers, servicios, 5
endpoints, seeders, OpenAPI, redacción del log. Requiere S1 y S2 archivados. Pantalla en S6.

## Assumptions

1. RN-05 por credenciales en la misma petición; descartados flujo pendiente→autorizado y pre-autorización.
2. Pacientes solo por siembra: § 3 no da a ningún rol su alta.
3. `medico` ve cualquier paciente.
4. `valid_until` inclusivo en `America/Bogota`; `agotada` prevalece sobre `vencida`.
5. Todo o nada: la parcialidad se pide con la cantidad.
6. Clave por usuario, sin caducidad; solo los éxitos se repiten.
7. Se dispensa desde cualquier bodega.
8. Sin consulta de bitácoras (S1 no da capacidad): se fila como deuda.
9. Sin segundo regente semilla (S1 D11).
10. La búsqueda registra una fila por paciente devuelto.
