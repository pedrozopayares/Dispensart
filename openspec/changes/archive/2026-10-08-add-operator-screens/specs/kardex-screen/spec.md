# Spec Delta — kardex-screen

## Purpose

Permite revisar el historial inmutable de movimientos de inventario, filtrado por bodega, producto y lote, para
rastrear de dónde salió y a dónde llegó cada unidad.

## ADDED Requirements

### Requirement: Historial de movimientos filtrable
La pantalla Kardex (`/kardex`) SHALL listar movimientos con fecha y hora en `America/Bogota`, tipo en español,
bodega, producto, lote, cantidad con signo, saldo resultante, usuario ("Sistema" si no hay) y motivo,
filtrables por bodega, producto y lote. El filtro de lote SHALL habilitarse al elegir producto y listar sus
lotes (RN-06).

#### Scenario: Tipos en español
- **WHEN** un `regente_farmacia` abre `/kardex` con movimientos de los cinco tipos
- **THEN** ve "Entrada", "Salida por dispensación", "Salida por traslado", "Entrada por traslado" y "Ajuste", nunca los literales de la API

#### Scenario: Cantidad con signo y usuario del sistema
- **WHEN** la lista incluye una salida de 3 unidades y una entrada de siembra sin usuario
- **THEN** la salida muestra "-3", la entrada muestra "+{n}" y su usuario "Sistema"

#### Scenario: Filtro por producto y lote
- **WHEN** el usuario elige un producto y luego uno de sus lotes
- **THEN** la lista muestra solo movimientos de ese lote y vuelve a la página 1

#### Scenario: Lote deshabilitado sin producto
- **WHEN** no hay producto elegido
- **THEN** el filtro "Lote" está deshabilitado con el texto "Elige un producto primero"

#### Scenario: Sin movimientos
- **WHEN** la consulta no devuelve movimientos para los filtros elegidos
- **THEN** ve "No hay movimientos para los filtros elegidos."

#### Scenario: Fallo de la consulta
- **WHEN** la consulta del kardex falla por red
- **THEN** ve "No pudimos conectar con el servidor. Intenta de nuevo." con "Reintentar"

### Requirement: Paginación y filtros en la URL
La lista SHALL paginar con "Anterior", "Siguiente" y "Página {n} de {total}". Bodega, producto, lote y página
SHALL reflejarse en la URL para volver o compartir la vista; un parámetro no numérico SHALL ignorarse sin
error (RN-06).

#### Scenario: Siguiente página
- **WHEN** hay más de una página y el usuario pulsa "Siguiente"
- **THEN** ve la página 2, "Página 2 de {total}" y la URL incluye la página

#### Scenario: Recarga conserva los filtros
- **WHEN** el usuario recarga la pantalla con bodega y producto elegidos
- **THEN** la pantalla vuelve con los mismos filtros aplicados

#### Scenario: Parámetro inválido en la URL
- **WHEN** el usuario abre `/kardex?lot_id=abc`
- **THEN** la pantalla muestra el kardex sin filtro de lote y la consulta no lleva `lot_id`

#### Scenario: Primera y última página
- **WHEN** el usuario está en la primera página, o en la última
- **THEN** "Anterior", o respectivamente "Siguiente", está deshabilitado

### Requirement: Movimientos inmutables en la interfaz
La pantalla SHALL NOT ofrecer editar ni borrar movimientos, para ningún rol (RN-06).

#### Scenario: Regente sin edición
- **WHEN** un `regente_farmacia` revisa el kardex
- **THEN** ninguna fila ni la pantalla ofrecen editar, borrar ni corregir un movimiento
