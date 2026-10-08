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
export type ApiErrorBody = components['schemas']['ApiError']

// Tipado a mano: Scramble no infiere `shortages` del 409 `insufficient_stock` (S3 design, tabla de
// contrato). Anotado en el journal por la tarea 0.2; se reemplaza si el documento lo publica.
export type Shortage = {
  prescription_item_id?: number
  product_id: number
  requested: number
  available: number
}
