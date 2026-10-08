# Proposal — add-operator-screens (S6)

## Why

La parte B pide cuatro pantallas React (Dispensación, Traslados, Inventario, Kardex) y evalúa errores
comprensibles (stock insuficiente, lote vencido, falta de autorización), prevención de doble clic y claridad
en un entorno de trabajo rápido (10 % de § 10). S1–S5 dejan la API completa; sin pantallas, el operador no la
alcanza y RN-09 (una clave por intención) solo existe del lado del servidor.

## What Changes

- **Dispensación**: buscar paciente (enmascarado para `auditor`), elegir prescripción vigente, bodega y
  cantidades, ver lotes asignados por FEFO (vista previa), confirmar. Control especial pide correo y contraseña
  del regente coautorizador. Una `Idempotency-Key` por intención, reutilizada en cada reintento (ADR-0003).
  `auditor` y `medico` la abren en modo consulta (RN-02, RN-04, RN-05, RN-09, RN-10).
- **Traslados**: listar, crear, solicitar, aprobar (oculto para quien solicitó; el servidor sigue siendo la
  autoridad), despachar, recibir por línea y anular; estado, actores y discrepancias visibles (RN-01, RN-07,
  RN-08).
- **Inventario**: existencias por bodega, producto y lote con vencimiento, lotes por vencer o vencidos y
  productos bajo mínimo resaltados (RN-01, RN-11).
- **Kardex**: movimientos filtrables por bodega, producto y lote, paginados, sin controles de edición (RN-06).
- **Espacio de trabajo**: navegación por rol, guarda de ruta por capacidad, catálogo de mensajes por `code`,
  estados de carga/error/vacío, botones que bloquean el doble envío, teclado primero, ningún dato de paciente
  en consola, almacenamiento del navegador ni URL (RN-10).

## Capabilities

### New Capabilities
- `operator-workspace`: navegación por rol, guarda de ruta, inicio con accesos, mensajes por código, doble envío, privacidad en el cliente.
- `dispensation-screen`: búsqueda de paciente, prescripción, vista previa FEFO, confirmación idempotente, coautorización.
- `transfers-screen`: listado, detalle, creación y acciones de estado de traslados con discrepancias.
- `inventory-screen`: existencias por bodega con alertas de vencimiento y stock mínimo.
- `kardex-screen`: historial de movimientos filtrable y paginado.

### Modified Capabilities
- `app-shell`: "Encabezado con sesión y cierre" — el inicio deja el estado vacío "Las pantallas de operación
  aparecerán aquí." por los accesos de su rol (tarea 0.1, escrita tras archivarse S1).

## Impact

Tier B. Parte B; RN-01, RN-02, RN-04..RN-11 desde la interfaz. Solo `software/web`: tipos derivados de
`software/api/openapi.json`, rutas, pantallas, textos en el módulo central, pruebas Vitest + Testing Library
con la red simulada en el borde HTTP. Sin endpoints nuevos ni cambios de API. Requiere S1–S5 archivados.
Posible pase de architect: nueva dependencia de desarrollo (generador de tipos, quizá MSW).

## Assumptions

1. Sin pantalla de ajustes de inventario ni de resolución de discrepancias: la parte B no las pide. Candidatas de ROADMAP.
2. Dispensación visible con `dispensations.create` (opera) o `patients.view` (consulta).
3. "Intención" = prescripción + bodega + ítems; las credenciales del autorizador no la cambian. Éxito o cambio de datos → clave nueva.
4. Vista previa explícita con botón; editar cantidades la invalida y bloquea Confirmar.
5. Resaltado de vencimiento y mínimo sale de `GET /api/alerts`, nunca del reloj del navegador.
6. Rutas en inglés (`/dispensations`, `/transfers`, `/inventory`, `/kardex`), etiquetas en español.
7. Sin pruebas E2E en navegador: humo manual sobre el stack de compose, registrado en tablas.
