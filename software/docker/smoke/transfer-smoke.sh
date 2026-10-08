#!/usr/bin/env bash
# Prueba de humo de traslados (S4) sobre el stack real, a través del Nginx de web (mismo origen).
# Auxiliar crea un traslado (origen -> destino, lote explícito) y lo solicita -> el regente aprueba ->
# segregación de funciones: el regente crea y solicita otro traslado y su propia aprobación da 403
# `segregation_of_duties` (luego lo anula) -> el auxiliar despacha (la existencia de origen baja, `salida_traslado`
# en el kardex) -> recepción parcial en destino (RECIBIDO_PARCIAL, discrepancia pendiente, `entrada_traslado`) ->
# transición prohibida (despachar de nuevo) 409 `invalid_transfer_transition` -> el regente resuelve la
# discrepancia devolviendo el faltante a origen (`ajuste` +faltante) -> detalle con la discrepancia resuelta.
#
# Uso (stack en marcha):  software/docker/smoke/transfer-smoke.sh
# Variables: WEB_PORT (8090), SMOKE_BASE_URL (http://localhost:$WEB_PORT),
#            SEED_USER_PASSWORD (vacía = valor por defecto SOLO de desarrollo, el mismo de la API).
# Requiere: bash, curl, jq. Sale con 0 solo si todas las comprobaciones pasan. Nunca imprime la contraseña.
# Repetible: cada corrida deja un neto de -1 unidad en origen y +1 en destino (sin filas de existencia nuevas),
# dos traslados nuevos y sus movimientos (el kardex es de solo inserción).
set -euo pipefail

BASE_URL="${SMOKE_BASE_URL:-http://localhost:${WEB_PORT:-8090}}"
# Valor por defecto SOLO DE DESARROLLO LOCAL (config/dispensart.php); usuarios sintéticos.
PASSWORD="${SEED_USER_PASSWORD:-dispensart-dev-only}"
RUN_ID="$(date -u +%Y%m%d%H%M%S)$$"
SENT=2
RECEIVED=1
SHORTAGE=$((SENT - RECEIVED))

WORK_DIR="$(mktemp -d)"
trap 'rm -rf "$WORK_DIR"' EXIT
BODY="$WORK_DIR/body"
HEADERS="$WORK_DIR/headers"
failures=0
checks=0

pass() { checks=$((checks + 1)); printf 'PASA  %s\n' "$1"; }
fail() { checks=$((checks + 1)); failures=$((failures + 1)); printf 'FALLA %s\n' "$1"; }
abort() { fail "$1"; printf 'Comprobaciones: %d, fallas: %d\n' "$checks" "$failures"; exit 1; }

# check <etiqueta> <filtro jq que debe dar true sobre el cuerpo>
check() { if jq -e "$2" "$BODY" > /dev/null 2>&1; then pass "$1"; else fail "$1"; fi; }

# equals <etiqueta> <obtenido> <esperado>
equals() { if [ "$2" = "$3" ]; then pass "$1 ($2)"; else fail "$1: $2, se esperaba $3"; fi; }

xsrf_token() {
    local raw
    raw="$(awk -F '\t' '$6 == "XSRF-TOKEN" { print $7 }' "$1" | tail -n 1)"
    printf '%b' "${raw//%/\\x}"
}

request() {
    local jar="$1" method="$2" path="$3"
    shift 3
    curl -sS -o "$BODY" -D "$HEADERS" -w '%{http_code}' -X "$method" \
        -b "$jar" -c "$jar" \
        -H 'Accept: application/json' \
        -H "Origin: $BASE_URL" -H "Referer: $BASE_URL/" \
        "$@" "$BASE_URL$path"
}

# post <tarro> <ruta> [json] -> imprime el código HTTP.
post() {
    local jar="$1" path="$2" json="${3:-}"
    if [ -n "$json" ]; then
        request "$jar" POST "$path" -H "X-XSRF-TOKEN: $(xsrf_token "$jar")" -H 'Content-Type: application/json' \
            --data "$json"
    else
        request "$jar" POST "$path" -H "X-XSRF-TOKEN: $(xsrf_token "$jar")"
    fi
}

expect() {
    local label="$1" want="$2" got="$3" filter="${4:-}"
    if [ "$got" != "$want" ]; then
        fail "$label: HTTP $got, se esperaba $want"
        return
    fi
    if [ -n "$filter" ] && ! jq -e "$filter" "$BODY" > /dev/null 2>&1; then
        fail "$label: HTTP $got sin cumplir $filter"
        return
    fi
    pass "$label: HTTP $got"
}

# login <usuario> <tarro>: cookie CSRF + login de la SPA. Aborta si falla: sin sesión nada más tiene sentido.
login() {
    local user="$1" jar="$2" code
    : > "$jar"
    expect "[$user] GET /sanctum/csrf-cookie" 204 "$(request "$jar" GET /sanctum/csrf-cookie)"
    code="$(post "$jar" /api/auth/login \
        "$(jq -nc --arg e "$user@dispensart.test" --arg p "$PASSWORD" '{email: $e, password: $p}')")"
    [ "$code" = 200 ] || abort "[$user] POST /api/auth/login: HTTP $code, se esperaba 200"
    pass "[$user] POST /api/auth/login: HTTP 200"
}

# stock_qty <tarro> <bodega> <lote> -> cantidad (0 si no hay fila: /api/stock solo lista cantidades > 0).
stock_qty() {
    request "$1" GET "/api/stock?warehouse_id=$2&lot_id=$3" > /dev/null
    jq -r '[.data[].quantity] | add // 0' "$BODY"
}

# kardex_snapshot <tarro> <bodega> <lote> -> "total id_mas_reciente"; deja el cuerpo con el más reciente.
kardex_snapshot() {
    request "$1" GET "/api/kardex?warehouse_id=$2&lot_id=$3&per_page=1" > /dev/null
    jq -r '"\(.meta.total) \(.data[0].id // 0)"' "$BODY"
}

# kardex_delta <etiqueta> <tarro> <bodega> <lote> <total_antes> <tipo> <cantidad>: exactamente un movimiento nuevo.
kardex_delta() {
    local label="$1" jar="$2" wh="$3" lot="$4" before="$5" type="$6" qty="$7" total latest
    read -r total latest <<< "$(kardex_snapshot "$jar" "$wh" "$lot")"
    if [ "$total" -eq $((before + 1)) ] \
        && jq -e --arg t "$type" --argjson q "$qty" '.data[0].type == $t and .data[0].quantity == $q' "$BODY" > /dev/null; then
        pass "$label: un movimiento $type de $qty (#$latest)"
    else
        fail "$label: total $before -> $total, último $(jq -c '.data[0] | {type, quantity}' "$BODY"), se esperaba $type de $qty"
    fi
}

printf 'Humo de traslados contra %s\n' "$BASE_URL"

aux="$WORK_DIR/auxiliar.jar"
reg="$WORK_DIR/regente.jar"
login auxiliar "$aux"
login regente "$reg"

# 1. Lote vigente con existencia en dos bodegas (semilla: L-ACE-2402 en FC y FU): origen = la de más unidades,
#    destino = otra que ya lo tiene. Así el humo no crea filas de existencia nuevas (no altera el orden de
#    GET /api/stock del que depende stock-smoke.sh) y es repetible: neto por corrida -1 en origen, +1 en destino.
expect "[auxiliar] GET /api/stock" 200 "$(request "$aux" GET /api/stock)"
read -r origin destination lot origin_code destination_code lot_code <<< "$(jq -r --argjson n "$SENT" '
    [.data[] | select(.lot.is_expired == false)] | group_by(.lot.id) | map(select(length >= 2) | sort_by(-.quantity))
    | map(select(.[0].quantity >= ($n * 2))) | max_by(.[0].quantity) // empty
    | "\(.[0].warehouse.id) \(.[1].warehouse.id) \(.[0].lot.id) \(.[0].warehouse.code) \(.[1].warehouse.code) \(.[0].lot.lot_code)"' \
    "$BODY")"
[ -n "${lot:-}" ] || abort "sin lote vigente con existencia en dos bodegas y al menos $((SENT * 2)) unidades en origen"
printf 'Lote %s de %s hacia %s\n' "$lot_code" "$origin_code" "$destination_code"

origin_before="$(stock_qty "$aux" "$origin" "$lot")"
dest_before="$(stock_qty "$aux" "$destination" "$lot")"
read -r origin_kardex _ <<< "$(kardex_snapshot "$aux" "$origin" "$lot")"
read -r dest_kardex _ <<< "$(kardex_snapshot "$aux" "$destination" "$lot")"

# 2. Auxiliar crea (BORRADOR) y solicita (SOLICITADO).
transfer_body="$(jq -nc --argjson o "$origin" --argjson d "$destination" --argjson l "$lot" --argjson q "$SENT" \
    --arg n "Humo S4 $RUN_ID" \
    '{origin_warehouse_id: $o, destination_warehouse_id: $d, notes: $n, lines: [{lot_id: $l, quantity: $q}]}')"
expect "[auxiliar] POST /api/transfers" 201 "$(post "$aux" /api/transfers "$transfer_body")" \
    '.data.status == "BORRADOR" and (.data.lines | length) == 1'
transfer="$(jq -r '.data.id' "$BODY")"
line="$(jq -r '.data.lines[0].id' "$BODY")"
expect "[auxiliar] POST /api/transfers/$transfer/request" 200 "$(post "$aux" "/api/transfers/$transfer/request")" \
    '.data.status == "SOLICITADO" and .data.requested_by != null'

# 3. Regente aprueba (APROBADO, aprobador distinto del solicitante).
expect "[regente] POST /api/transfers/$transfer/approve" 200 "$(post "$reg" "/api/transfers/$transfer/approve")" \
    '.data.status == "APROBADO" and .data.approved_by.id != .data.requested_by.id'

# 4. Segregación de funciones (RN-08): el solicitante no aprueba su propia solicitud.
seg_body="$(jq -c --arg n "Humo S4 segregación $RUN_ID" '.notes = $n | .lines[0].quantity = 1' <<< "$transfer_body")"
expect "[regente] POST /api/transfers (para segregación)" 201 "$(post "$reg" /api/transfers "$seg_body")"
own="$(jq -r '.data.id' "$BODY")"
expect "[regente] POST /api/transfers/$own/request" 200 "$(post "$reg" "/api/transfers/$own/request")"
expect "[regente] aprueba su propia solicitud" 403 "$(post "$reg" "/api/transfers/$own/approve")" \
    '.code == "segregation_of_duties"'
expect "[regente] GET /api/transfers/$own sigue SOLICITADO" 200 "$(request "$reg" GET "/api/transfers/$own")" \
    '.data.status == "SOLICITADO" and .data.approved_by == null'
expect "[regente] POST /api/transfers/$own/void" 200 \
    "$(post "$reg" "/api/transfers/$own/void" "$(jq -nc '{reason: "Humo: prueba de segregación"}')")" \
    '.data.status == "ANULADO"'

# 5. Despacho: la existencia de origen baja y aparece `salida_traslado` en el kardex de origen.
expect "[auxiliar] POST /api/transfers/$transfer/dispatch" 200 "$(post "$aux" "/api/transfers/$transfer/dispatch")" \
    '.data.status == "EN_TRANSITO" and .data.dispatched_by != null'
equals "existencia de origen tras el despacho" "$(stock_qty "$aux" "$origin" "$lot")" "$((origin_before - SENT))"
kardex_delta "kardex de origen tras el despacho" "$aux" "$origin" "$lot" "$origin_kardex" salida_traslado "-$SENT"

# 6. Recepción parcial en destino: RECIBIDO_PARCIAL, discrepancia pendiente, `entrada_traslado` por lo recibido.
expect "[auxiliar] POST /api/transfers/$transfer/receive parcial" 200 \
    "$(post "$aux" "/api/transfers/$transfer/receive" \
        "$(jq -nc --argjson l "$line" --argjson r "$RECEIVED" '{lines: [{line_id: $l, received_quantity: $r}]}')")" \
    ".data.status == \"RECIBIDO_PARCIAL\" and .data.lines[0].received_quantity == $RECEIVED"
check "discrepancia pendiente por el faltante" \
    "[.data.discrepancies[] | select(.line_id == $line and .status == \"pending\" and .shortage == $SHORTAGE)] | length == 1"
discrepancy="$(jq -r '.data.discrepancies[0].id' "$BODY")"
equals "existencia de destino tras la recepción" "$(stock_qty "$aux" "$destination" "$lot")" "$((dest_before + RECEIVED))"
kardex_delta "kardex de destino tras la recepción" "$aux" "$destination" "$lot" "$dest_kardex" entrada_traslado "$RECEIVED"

# 7. Transición prohibida: despachar un traslado ya recibido.
expect "[auxiliar] despacha de nuevo un RECIBIDO_PARCIAL" 409 "$(post "$aux" "/api/transfers/$transfer/dispatch")" \
    '.code == "invalid_transfer_transition"'
equals "existencia de origen sin cambio tras el 409" "$(stock_qty "$aux" "$origin" "$lot")" "$((origin_before - SENT))"

# 8. Resolución: el faltante vuelve a origen como `ajuste`; una segunda resolución es 409.
resolve_body="$(jq -nc '{resolution: "returned_to_origin", reason: "Humo: faltante devuelto a origen"}')"
expect "[regente] POST .../discrepancies/$discrepancy/resolve" 200 \
    "$(post "$reg" "/api/transfers/$transfer/discrepancies/$discrepancy/resolve" "$resolve_body")" \
    '.data.status == "resolved" and .data.resolution == "returned_to_origin" and .data.resolved_by != null'
kardex_delta "kardex de origen tras la resolución" "$reg" "$origin" "$lot" "$((origin_kardex + 1))" ajuste "$SHORTAGE"
equals "existencia de origen tras la resolución" "$(stock_qty "$aux" "$origin" "$lot")" "$((origin_before - RECEIVED))"
expect "[regente] resuelve de nuevo la misma discrepancia" 409 \
    "$(post "$reg" "/api/transfers/$transfer/discrepancies/$discrepancy/resolve" "$resolve_body")" \
    '.code == "discrepancy_already_resolved"'

# 9. Detalle y listado: estado final, discrepancia resuelta, trazabilidad de actores.
expect "[auxiliar] GET /api/transfers/$transfer" 200 "$(request "$aux" GET "/api/transfers/$transfer")" \
    '.data.status == "RECIBIDO_PARCIAL" and .data.discrepancies[0].status == "resolved"
     and .data.received_by != null and .data.dispatched_at != null'
expect "[auxiliar] GET /api/transfers?status=RECIBIDO_PARCIAL" 200 \
    "$(request "$aux" GET "/api/transfers?status=RECIBIDO_PARCIAL&per_page=100")" \
    "[.data[].id] | index($transfer) != null"

printf 'Comprobaciones: %d, fallas: %d\n' "$checks" "$failures"
[ "$failures" -eq 0 ]
