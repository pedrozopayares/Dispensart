#!/usr/bin/env bash
# Prueba de humo de alertas de inventario (S5) sobre el stack real, a través del Nginx de web (mismo origen).
# Como regente: GET /api/alerts 200 con ambas listas no vacías; vencimiento incluye L-ACE-2401 vencido
# (is_expired true, days_to_expiry < 0); stock bajo incluye FC/MED-006, BH/MED-004 y BH/MED-006 y excluye
# FC/MED-001 (mínimos semilla, design D7). Como auditor: el mismo cuerpo. Como médico: 403 `forbidden`.
#
# Uso (stack en marcha):  software/docker/smoke/alerts-smoke.sh
# Variables: WEB_PORT (8090), SMOKE_BASE_URL (http://localhost:$WEB_PORT),
#            SEED_USER_PASSWORD (vacía = valor por defecto SOLO de desarrollo, el mismo de la API).
# Requiere: bash, curl, jq. Sale con 0 solo si todas las comprobaciones pasan. Nunca imprime la contraseña.
# Solo lectura: no deja rastro en la base.
set -euo pipefail

BASE_URL="${SMOKE_BASE_URL:-http://localhost:${WEB_PORT:-8090}}"
# Valor por defecto SOLO DE DESARROLLO LOCAL (config/dispensart.php); usuarios sintéticos.
PASSWORD="${SEED_USER_PASSWORD:-dispensart-dev-only}"
# Lote semilla vencido con existencia (LotSeeder: hoy - 10; StockSeeder: FC, 5 unidades).
EXPIRED_LOT='L-ACE-2401'

WORK_DIR="$(mktemp -d)"
trap 'rm -rf "$WORK_DIR"' EXIT
BODY="$WORK_DIR/body"
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
    curl -sS -o "$BODY" -w '%{http_code}' -X "$method" \
        -b "$jar" -c "$jar" \
        -H 'Accept: application/json' \
        -H "Origin: $BASE_URL" -H "Referer: $BASE_URL/" \
        "$@" "$BASE_URL$path"
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
# La contraseña viaja solo en el cuerpo de curl; nunca se imprime.
login() {
    local user="$1" jar="$2" code
    : > "$jar"
    expect "[$user] GET /sanctum/csrf-cookie" 204 "$(request "$jar" GET /sanctum/csrf-cookie)"
    code="$(request "$jar" POST /api/auth/login -H "X-XSRF-TOKEN: $(xsrf_token "$jar")" \
        -H 'Content-Type: application/json' \
        --data "$(jq -nc --arg e "$user@dispensart.test" --arg p "$PASSWORD" '{email: $e, password: $p}')")"
    [ "$code" = 200 ] || abort "[$user] POST /api/auth/login: HTTP $code, se esperaba 200"
    pass "[$user] POST /api/auth/login: HTTP 200"
}

# Par bodega/producto presente en low_stock (por código, no por id).
low() { printf '[.data.low_stock[] | select(.warehouse.code == "%s" and .product.code == "%s")] | length' "$1" "$2"; }

printf 'Humo de alertas de inventario contra %s\n' "$BASE_URL"

# 1. Regente: contenido de ambas alertas.
jar="$WORK_DIR/regente.jar"
login regente "$jar"
expect "[regente] GET /api/alerts" 200 "$(request "$jar" GET /api/alerts)" \
    '(.data.expiring_lots | type == "array") and (.data.low_stock | type == "array")'
cp "$BODY" "$WORK_DIR/regente.json"

check "expiring_lots no vacía" '.data.expiring_lots | length > 0'
check "low_stock no vacía" '.data.low_stock | length > 0'
check "$EXPIRED_LOT listado con is_expired true y days_to_expiry < 0" \
    "[.data.expiring_lots[] | select(.lot.lot_code == \"$EXPIRED_LOT\")]
     | length > 0 and all(.lot.is_expired == true and .days_to_expiry < 0 and .quantity > 0)"
check "low_stock contiene FC/MED-006" "$(low FC MED-006) > 0"
check "low_stock contiene BH/MED-004" "$(low BH MED-004) > 0"
check "low_stock contiene BH/MED-006" "$(low BH MED-006) > 0"
check "low_stock no contiene FC/MED-001 (con mínimo, sin alerta)" "$(low FC MED-001) == 0"
check "cada fila de low_stock con available_quantity < minimum_quantity" \
    '.data.low_stock | all(.available_quantity < .minimum_quantity)'

# 2. Auditor: lectura de inventario, el mismo cuerpo que el regente.
aud_jar="$WORK_DIR/auditor.jar"
login auditor "$aud_jar"
expect "[auditor] GET /api/alerts" 200 "$(request "$aud_jar" GET /api/alerts)"
if [ "$(jq -S . "$BODY")" = "$(jq -S . "$WORK_DIR/regente.json")" ]; then
    pass "[auditor] cuerpo idéntico al del regente"
else
    fail "[auditor] cuerpo distinto al del regente"
fi

# 3. Médico: sin lectura de inventario.
med_jar="$WORK_DIR/medico.jar"
login medico "$med_jar"
expect "[medico] GET /api/alerts" 403 "$(request "$med_jar" GET /api/alerts)" '.code == "forbidden"'

printf 'Comprobaciones: %d, fallas: %d\n' "$checks" "$failures"
[ "$failures" -eq 0 ]
