#!/usr/bin/env bash
# Prueba de humo de dispensación (S3) sobre el stack real, a través del Nginx de web (mismo origen).
# Como auxiliar: búsqueda por prefijo de documento -> ficha con la prescripción sembrada -> vista previa FEFO
# (excluye el lote vencido) -> dispensación con Idempotency-Key (201, un movimiento nuevo en el kardex) ->
# repetición con la misma clave (misma respuesta, Idempotent-Replayed, sin movimiento nuevo) -> producto de control
# especial: sin autorizador 422, con el regente semilla como coautorizador 201.
# Como médico: crea una prescripción nueva por corrida, así el humo es repetible sin agotar las semillas.
#
# Uso (stack en marcha):  software/docker/smoke/dispensation-smoke.sh
# Variables: WEB_PORT (8090), SMOKE_BASE_URL (http://localhost:$WEB_PORT),
#            SEED_USER_PASSWORD (vacía = valor por defecto SOLO de desarrollo, el mismo de la API).
# Requiere: bash, curl, jq. Sale con 0 solo si todas las comprobaciones pasan. Nunca imprime la contraseña.
# Deja en la base de desarrollo una prescripción y dos dispensaciones (el kardex es de solo inserción).
set -euo pipefail

BASE_URL="${SMOKE_BASE_URL:-http://localhost:${WEB_PORT:-8090}}"
# Valor por defecto SOLO DE DESARROLLO LOCAL (config/dispensart.php); usuarios sintéticos.
PASSWORD="${SEED_USER_PASSWORD:-dispensart-dev-only}"
SEED_DOCUMENT='9999010001'
RUN_ID="$(date -u +%Y%m%d%H%M%S)$$"

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

# post <tarro> <ruta> <json> [cabeceras extra...] -> imprime el código HTTP.
post() {
    local jar="$1" path="$2" json="$3"
    shift 3
    request "$jar" POST "$path" -H "X-XSRF-TOKEN: $(xsrf_token "$jar")" -H 'Content-Type: application/json' \
        "$@" --data "$json"
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

# kardex_snapshot <tarro> <bodega> <lote> -> "total id_mas_reciente" del kardex filtrado.
kardex_snapshot() {
    request "$1" GET "/api/kardex?warehouse_id=$2&lot_id=$3&per_page=1" > /dev/null
    jq -r '"\(.meta.total) \(.data[0].id // 0)"' "$BODY"
}

future_date() { date -u -v+7d +%F 2> /dev/null || date -u -d '+7 days' +%F; }

printf 'Humo de dispensación contra %s\n' "$BASE_URL"

# 1. Auxiliar: búsqueda por prefijo de documento y prescripción sembrada en la ficha.
aux="$WORK_DIR/auxiliar.jar"
login auxiliar "$aux"
expect "[auxiliar] GET /api/patients?q=prefijo" 200 "$(request "$aux" GET "/api/patients?q=${SEED_DOCUMENT:0:8}")" \
    "[.data[].document_number] | index(\"$SEED_DOCUMENT\") != null"
patient_id="$(jq -r --arg d "$SEED_DOCUMENT" '.data[] | select(.document_number == $d) | .id' "$BODY")"
[ -n "$patient_id" ] || abort "paciente semilla $SEED_DOCUMENT no encontrado"

expect "[auxiliar] GET /api/patients/$patient_id con prescripción sembrada" 200 \
    "$(request "$aux" GET "/api/patients/$patient_id")" \
    '[.data.prescriptions[] | select(.prescriber.name == "Médico Demo") | .items[] | select(.product.code == "MED-001")] | length > 0'

# Identificadores del catálogo semilla.
expect "[auxiliar] GET /api/products" 200 "$(request "$aux" GET /api/products)"
med004="$(jq -r '.data[] | select(.code == "MED-004") | .id' "$BODY")"
med006="$(jq -r '.data[] | select(.code == "MED-006") | .id' "$BODY")"
check "MED-006 es de control especial" '.data[] | select(.code == "MED-006") | .is_controlled == true'
expect "[auxiliar] GET /api/warehouses" 200 "$(request "$aux" GET /api/warehouses)"
bh="$(jq -r '.data[] | select(.code == "BH") | .id' "$BODY")"
warehouses="$(jq -r '.data[].id' "$BODY")"
[ -n "$med004" ] && [ -n "$med006" ] && [ -n "$bh" ] || abort "catálogo semilla incompleto (MED-004, MED-006, BH)"

# 2. Médico: prescripción nueva por corrida (las semillas se agotarían tras pocas corridas).
med="$WORK_DIR/medico.jar"
login medico "$med"
expect "[medico] POST /api/prescriptions" 201 "$(post "$med" /api/prescriptions "$(jq -nc \
    --argjson p "$patient_id" --arg v "$(future_date)" --argjson a "$med004" --argjson b "$med006" \
    '{patient_id: $p, valid_until: $v, items: [{product_id: $a, quantity: 2}, {product_id: $b, quantity: 1}]}')")"
prescription_id="$(jq -r '.data.id' "$BODY")"
item004="$(jq -r --argjson a "$med004" '.data.items[] | select(.product.id == $a) | .id' "$BODY")"
item006="$(jq -r --argjson b "$med006" '.data.items[] | select(.product.id == $b) | .id' "$BODY")"
[ -n "$item004" ] && [ -n "$item006" ] || abort "prescripción sin los ítems esperados"

# 3. Vista previa FEFO en BH: el lote vencido L-LOS-2401 queda excluido; asigna por vencimiento ascendente.
plain="$(jq -nc --argjson p "$prescription_id" --argjson w "$bh" --argjson i "$item004" \
    '{prescription_id: $p, warehouse_id: $w, items: [{prescription_item_id: $i, quantity: 2}]}')"
expect "[auxiliar] POST /api/dispensations/preview (FEFO)" 200 "$(post "$aux" /api/dispensations/preview "$plain")" \
    '.data.fulfillable == true and .data.requires_authorization == false'
check "vista previa excluye el lote vencido" '.data.items[0].expired_excluded_quantity > 0'
check "vista previa en orden FEFO, sin lotes vencidos" \
    '.data.items[0].allocations | (map(.expires_on) == (map(.expires_on) | sort)) and all(.lot_code != "L-LOS-2401")'
lot_id="$(jq -r '.data.items[0].allocations[0].lot_id' "$BODY")"

# 4. Dispensación con clave: exactamente un movimiento nuevo en el kardex.
read -r total_before _ <<< "$(kardex_snapshot "$aux" "$bh" "$lot_id")"
key="smoke-s3-plain-$RUN_ID"
expect "[auxiliar] POST /api/dispensations con Idempotency-Key" 201 \
    "$(post "$aux" /api/dispensations "$plain" -H "Idempotency-Key: $key")" \
    '.data.authorized_by == null and (.data.lines | length) >= 1'
cp "$BODY" "$WORK_DIR/first"
movement_id="$(jq -r '.data.lines[0].kardex_movement_id' "$BODY")"
read -r total_after latest_after <<< "$(kardex_snapshot "$aux" "$bh" "$lot_id")"
if [ "$total_after" -eq $((total_before + 1)) ] && [ "$latest_after" = "$movement_id" ]; then
    pass "kardex: un movimiento nuevo ($movement_id) tras la dispensación"
else
    fail "kardex: total $total_before -> $total_after, más reciente $latest_after, se esperaba $movement_id"
fi
check "movimiento de tipo salida_dispensacion" '.data[0].type == "salida_dispensacion" and .data[0].quantity < 0'

# 5. Repetición con la misma clave y el mismo cuerpo: respuesta original, sin movimiento nuevo (RN-09).
expect "[auxiliar] repetición con la misma clave" 201 \
    "$(post "$aux" /api/dispensations "$plain" -H "Idempotency-Key: $key")"
if cmp -s "$BODY" "$WORK_DIR/first"; then pass "repetición: cuerpo idéntico al original"; else fail "repetición: cuerpo distinto"; fi
if grep -qi '^Idempotent-Replayed: true' "$HEADERS"; then pass "repetición: Idempotent-Replayed: true"; else fail "repetición sin Idempotent-Replayed"; fi
read -r total_replay latest_replay <<< "$(kardex_snapshot "$aux" "$bh" "$lot_id")"
if [ "$total_replay" -eq "$total_after" ] && [ "$latest_replay" = "$movement_id" ]; then
    pass "kardex: sin movimiento nuevo tras la repetición (total $total_replay)"
else
    fail "kardex: la repetición movió el kardex ($total_after -> $total_replay)"
fi

# 6. Control especial (RN-05): bodega con existencia de MED-006, sin autorizador 422, con el regente 201.
controlled_wh=''
for w in $warehouses; do
    controlled="$(jq -nc --argjson p "$prescription_id" --argjson w "$w" --argjson i "$item006" \
        '{prescription_id: $p, warehouse_id: $w, items: [{prescription_item_id: $i, quantity: 1}]}')"
    if [ "$(post "$aux" /api/dispensations/preview "$controlled")" = 200 ] \
        && jq -e '.data.fulfillable == true' "$BODY" > /dev/null; then
        controlled_wh="$w"
        break
    fi
done
[ -n "$controlled_wh" ] || abort "sin bodega con existencia de MED-006"
check "vista previa de control especial exige autorización" '.data.requires_authorization == true'

expect "[auxiliar] control especial sin autorizador" 422 \
    "$(post "$aux" /api/dispensations "$controlled" -H "Idempotency-Key: smoke-s3-noauth-$RUN_ID")" \
    '.code == "authorization_required"'

with_regente="$(jq -c --arg e 'regente@dispensart.test' --arg p "$PASSWORD" \
    '. + {authorizer_email: $e, authorizer_password: $p}' <<< "$controlled")"
expect "[auxiliar] control especial coautorizado por el regente" 201 \
    "$(post "$aux" /api/dispensations "$with_regente" -H "Idempotency-Key: smoke-s3-ctrl-$RUN_ID")" \
    '.data.authorized_by != null and .data.authorized_by != .data.dispensed_by'

printf 'Comprobaciones: %d, fallas: %d\n' "$checks" "$failures"
[ "$failures" -eq 0 ]
