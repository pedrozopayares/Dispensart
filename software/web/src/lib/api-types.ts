import type { components, paths } from '@/lib/api-schema'

// Ayudantes por ruta sobre los tipos generados desde `software/api/openapi.json` (design D1).
// Se indexa por ruta y método, nunca por nombre de componente de Scramble (inestable).
// Las rutas del documento no llevan el prefijo `/api` (servidor `/api`).

type Method = 'get' | 'post' | 'put' | 'patch' | 'delete'
type Operation<P extends keyof paths, M extends Method> = NonNullable<paths[P][M]>

type JsonOf<R> = R extends { content: { 'application/json': infer Body } } ? Body : never

// Cuerpo JSON de la respuesta exitosa (200 o 201).
export type ResponseOf<P extends keyof paths, M extends Method> =
  Operation<P, M> extends { responses: infer Responses }
    ? Responses extends { 200: infer Ok }
      ? JsonOf<Ok>
      : Responses extends { 201: infer Created }
        ? JsonOf<Created>
        : never
    : never

// Parámetros de consulta admitidos por la ruta.
export type QueryOf<P extends keyof paths, M extends Method> =
  Operation<P, M> extends { parameters: { query?: infer Query } } ? NonNullable<Query> : never

// Cuerpo JSON de la petición.
export type BodyOf<P extends keyof paths, M extends Method> =
  Operation<P, M> extends { requestBody?: infer Body } ? JsonOf<NonNullable<Body>> : never

// Recursos que las pantallas leen, derivados de sus rutas.
export type StockRow = ResponseOf<'/stock', 'get'>['data'][number]
export type StockQuery = QueryOf<'/stock', 'get'>
export type KardexPage = ResponseOf<'/kardex', 'get'>
export type KardexMovement = KardexPage['data'][number]
export type KardexQuery = QueryOf<'/kardex', 'get'>
export type MovementType = KardexMovement['type']
export type Warehouse = ResponseOf<'/warehouses', 'get'>['data'][number]
export type Product = ResponseOf<'/products', 'get'>['data'][number]
export type Lot = ResponseOf<'/lots', 'get'>['data'][number]
export type LotQuery = QueryOf<'/lots', 'get'>
// Escrituras del catálogo (S13, solo catalog.manage). La edición envía todos los campos del formulario.
export type NewWarehouse = BodyOf<'/warehouses', 'post'>
export type WarehouseChanges = BodyOf<'/warehouses/{warehouse}', 'patch'>
export type NewProduct = BodyOf<'/products', 'post'>
export type ProductChanges = BodyOf<'/products/{product}', 'patch'>
export type ApiErrorBody = components['schemas']['ApiError']
// Faltante por ítem de `insufficient_stock` en la dispensación (publicado por S3).
export type Shortage = NonNullable<ApiErrorBody['shortages']>[number]

// Pacientes, prescripciones y dispensación (S3).
export type PatientSummary = ResponseOf<'/patients', 'get'>['data'][number]
export type PatientRecord = ResponseOf<'/patients/{patient}', 'get'>['data']
export type Prescription = NonNullable<PatientRecord['prescriptions']>[number]
export type PrescriptionItem = Prescription['items'][number]
export type NewPrescription = BodyOf<'/prescriptions', 'post'>
export type CreatedPrescription = ResponseOf<'/prescriptions', 'post'>['data']
export type PreviewRequest = BodyOf<'/dispensations/preview', 'post'>
export type DispensationPreview = ResponseOf<'/dispensations/preview', 'post'>['data']
export type PreviewItem = DispensationPreview['items'][number]
export type DispensationRequest = BodyOf<'/dispensations', 'post'>
export type Dispensation = ResponseOf<'/dispensations', 'post'>['data']

// Tipado a mano: el documento publica `status` como `string`; los literales son los del enum
// `PrescriptionStatus` de la API. Un valor fuera de la lista se muestra con la etiqueta genérica.
export type PrescriptionStatus = 'vigente' | 'vencida' | 'agotada'

// Traslados entre bodegas (S4).
export type TransferPage = ResponseOf<'/transfers', 'get'>
export type TransferSummary = TransferPage['data'][number]
export type TransferQuery = QueryOf<'/transfers', 'get'>
export type TransferStatus = TransferSummary['status']
export type Transfer = ResponseOf<'/transfers/{transfer}', 'get'>['data']
export type TransferLine = Transfer['lines'][number]
export type TransferDiscrepancy = Transfer['discrepancies'][number]
export type NewTransfer = BodyOf<'/transfers', 'post'>
export type TransferReceipt = BodyOf<'/transfers/{transfer}/receive', 'post'>
export type TransferVoid = BodyOf<'/transfers/{transfer}/void', 'post'>

// Alertas de inventario (S5, RN-11): lotes por vencer o vencidos y productos bajo su mínimo.
export type Alerts = ResponseOf<'/alerts', 'get'>['data']
export type AlertsQuery = QueryOf<'/alerts', 'get'>
export type ExpiringLot = Alerts['expiring_lots'][number]
export type LowStock = Alerts['low_stock'][number]

// Asistente de inventario (S7, parte C): pregunta, respuesta decidida por el servidor y consultas hechas.
export type AskAssistantRequest = BodyOf<'/assistant/ask', 'post'>
export type AssistantAnswer = ResponseOf<'/assistant/ask', 'post'>['data']
export type AssistantOutcome = AssistantAnswer['outcome']
export type AssistantToolCall = AssistantAnswer['tool_calls'][number]
export type ToolCallStatus = AssistantToolCall['status']

// Usuarios (S13, solo users.manage). `UserResource` no trae contraseña.
export type User = ResponseOf<'/users', 'get'>['data'][number]
export type NewUser = BodyOf<'/users', 'post'>
