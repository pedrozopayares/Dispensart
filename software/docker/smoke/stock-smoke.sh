#!/usr/bin/env bash
# Prueba de humo de existencias y kardex (S2) sobre el stack real, a través del Nginx de web (mismo origen).
# Como regente: login -> GET /api/stock -> ajuste de -1 (201) -> la existencia baja en 1 -> GET /api/kardex
# muestra el movimiento `ajuste` con su motivo y saldo -> ajuste mayor que la existencia (409) sin efecto.
# Como auxiliar: el mismo ajuste se rechaza con 403.
#
# Uso (stack en marcha):  software/docker/smoke/stock-smoke.sh
# Variables: WEB_PORT (8090), SMOKE_BASE_URL (http://localhost:$WEB_PORT),
#            SEED_USER_PASSWORD (vacía = valor por defecto SOLO de desarrollo, el mismo de la API).
# Requiere: bash, curl, grep, jq. Sale con 0 solo si todas las comprobaciones pasan. Nunca imprime la contraseña.
# Deja un movimiento `ajuste` de -1 en la base de desarrollo (el kardex es de solo inserción).
set -euo pipefail

BASE_URL="${SMOKE_BASE_URL:-http://localhost:${WEB_PORT:-8090}}"
# Valor por defecto SOLO DE DESARROLLO LOCAL (config/dispensart.php); usuarios sintéticos.
PASSWORD="${SEED_USER_PASSWORD:-dispensart-dev-only}"
# Existencia semilla que ningún otro humo toca (LotSeeder/StockSeeder): Farmacia Central, ibuprofeno L-IBU-2402.
SEED_WAREHOUSE='FC'
SEED_LOT='L-IBU-2402'

WORK_DIR="$(mktemp -d)"
trap 'rm -rf "$WORK_DIR"' EXIT
BODY="$WORK_DIR/body"
failures=0
checks=0

pass() { checks=$((checks + 1)); printf 'PASA  %s\n' "$1"; }
fail() { checks=$((checks + 1)); failures=$((failures + 1)); printf 'FALLA %s\n' "$1"; }

xsrf_token() {
    local raw
    raw="$(awk -F '\t' '$6 == "XSRF-TOKEN" { print $7 }' "$1" | tail -n 1)"
    printf '%b' "${raw//%/\\x}"
}

request() {
    local jar="$1" method="$2" path="$3"
    shift 3
    curl -sS -o "$BODY" -w '%{http_code}' -X "$method" \
        -b "$jar" -c "$jar" \
        -H 'Accept: application/json' \
        -H "Origin: $BASE_URL" -H "Referer: $BASE_URL/" \
        "$@" "$BASE_URL$path"
}

expect() {
    local label="$1" want="$2" got="$3" needle="${4:-}"
    if [ "$got" != "$want" ]; then
        fail "$label: HTTP $got, se esperaba $want"
        return
    fi
    if [ -n "$needle" ] && ! grep -qF -- "$needle" "$BODY"; then
        fail "$label: HTTP $got sin '$needle' en el cuerpo"
        return
    fi
    pass "$label: HTTP $got"
}

# login <usuario> <tarro>: cookie CSRF + login de la SPA.
login() {
    local user="$1" jar="$2"
    : > "$jar"
    expect "[$user] GET /sanctum/csrf-cookie" 204 "$(request "$jar" GET /sanctum/csrf-cookie)"
    local payload
    payload="$(printf '{"email":"%s@dispensart.test","password":"%s"}' "$user" "$PASSWORD")"
    expect "[$user] POST /api/auth/login" 200 "$(request "$jar" POST /api/auth/login \
        -H "X-XSRF-TOKEN: $(xsrf_token "$jar")" -H 'Content-Type: application/json' --data "$payload")"
}

# adjust <tarro> <bodega> <lote> <cantidad> <motivo> -> imprime el código HTTP.
adjust() {
    local body
    body="$(printf '{"warehouse_id":%s,"lot_id":%s,"quantity":%s,"reason":"%s"}' "$2" "$3" "$4" "$5")"
    request "$1" POST /api/stock-adjustments -H "X-XSRF-TOKEN: $(xsrf_token "$1")" \
        -H 'Content-Type: application/json' --data "$body"
}

printf 'Humo de existencias y kardex contra %s\n' "$BASE_URL"

jar="$WORK_DIR/regente.jar"
login regente "$jar"

# Existencia objetivo por clave estable de la semilla (bodega + lote), no por posición en la lista: otros humos
# (traslados, dispensación) crean o vacían filas y cambian el orden de GET /api/stock.
expect "[regente] GET /api/warehouses" 200 "$(request "$jar" GET /api/warehouses)"
warehouse_id="$(jq -r --arg c "$SEED_WAREHOUSE" '.data[]? | select(.code == $c) | .id' "$BODY")"
expect "[regente] GET /api/stock?warehouse_id=$warehouse_id" 200 \
    "$(request "$jar" GET "/api/stock?warehouse_id=${warehouse_id:-0}")" '"quantity":'
lot_id="$(jq -r --arg l "$SEED_LOT" '.data[]? | select(.lot.lot_code == $l) | .lot.id' "$BODY")"
expect "[regente] GET /api/stock por bodega y lote" 200 \
    "$(request "$jar" GET "/api/stock?warehouse_id=${warehouse_id:-0}&lot_id=${lot_id:-0}")" '"quantity":'
quantity="$(jq -r '[.data[]?.quantity] | add // empty' "$BODY")"
if [ -n "$warehouse_id" ] && [ -n "$lot_id" ] && [ -n "${quantity:-}" ] && [ "$quantity" -gt 0 ]; then
    pass "existencia semilla $SEED_WAREHOUSE/$SEED_LOT: bodega $warehouse_id, lote $lot_id, cantidad $quantity"
else
    fail "sin existencia semilla $SEED_WAREHOUSE/$SEED_LOT en GET /api/stock"
    printf 'Comprobaciones: %d, fallas: %d\n' "$checks" "$failures"
    exit 1
fi

reason="humo S2 $(date -u +%Y%m%dT%H%M%SZ)"
expected=$((quantity - 1))
expect "[regente] POST /api/stock-adjustments -1" 201 "$(adjust "$jar" "$warehouse_id" "$lot_id" -1 "$reason")" \
    "\"balance_after\":$expected"

expect "[regente] GET /api/stock tras el ajuste" 200 \
    "$(request "$jar" GET "/api/stock?warehouse_id=$warehouse_id&lot_id=$lot_id")" "\"quantity\":$expected"

expect "[regente] GET /api/kardex con el movimiento" 200 \
    "$(request "$jar" GET "/api/kardex?warehouse_id=$warehouse_id&lot_id=$lot_id")" "\"reason\":\"$reason\""
if grep -qF '"type":"ajuste"' "$BODY" && grep -qF '"type":"entrada"' "$BODY"; then
    pass "kardex con la entrada semilla y el ajuste"
else
    fail "kardex sin entrada semilla o sin ajuste"
fi

expect "[regente] ajuste mayor que la existencia" 409 \
    "$(adjust "$jar" "$warehouse_id" "$lot_id" "-$((expected + 1))" "humo S2 exceso")" '"code":"insufficient_stock"'
expect "[regente] existencia intacta tras el 409" 200 \
    "$(request "$jar" GET "/api/stock?warehouse_id=$warehouse_id&lot_id=$lot_id")" "\"quantity\":$expected"

aux_jar="$WORK_DIR/auxiliar.jar"
login auxiliar "$aux_jar"
expect "[auxiliar] POST /api/stock-adjustments" 403 "$(adjust "$aux_jar" "$warehouse_id" "$lot_id" -1 "humo S2 rol")" \
    '"code":"forbidden"'

printf 'Comprobaciones: %d, fallas: %d\n' "$checks" "$failures"
[ "$failures" -eq 0 ]
