#!/usr/bin/env bash
# Prueba de humo del asistente de inventario (S7) sobre el stack real, a través del Nginx de web (mismo origen).
# Sin sesión -> 401. Regente: pregunta de la parte C (find_expiring_lots con 60 días, acetaminofén, farmacia
# central), conteo de traslados en tránsito, estado de un traslado existente por id y pregunta sobre una paciente
# (out_of_scope, sin herramientas). Médico: pregunta de inventario -> not_permitted, herramienta denied, sin datos.
# Imprime una tabla rol | pregunta | HTTP | outcome | herramientas para el registro.
#
# Uso (stack en marcha):  software/docker/smoke/assistant-smoke.sh
# Variables: WEB_PORT (8090), SMOKE_BASE_URL (http://localhost:$WEB_PORT),
#            SEED_USER_PASSWORD (vacía = valor por defecto SOLO de desarrollo, el mismo de la API).
# Requiere: bash, curl, jq. Sale con 0 solo si todas las comprobaciones pasan. Nunca imprime la contraseña.
# Repetible: el asistente solo lee (transacción de solo lectura revertida). 5 preguntas por corrida, bajo el
# límite de 20 por minuto. Sobre una base sin traslados crea uno en BORRADOR (única escritura del humo).
set -euo pipefail

BASE_URL="${SMOKE_BASE_URL:-http://localhost:${WEB_PORT:-8090}}"
# Valor por defecto SOLO DE DESARROLLO LOCAL (config/dispensart.php); usuarios sintéticos.
PASSWORD="${SEED_USER_PASSWORD:-dispensart-dev-only}"

WORK_DIR="$(mktemp -d)"
trap 'rm -rf "$WORK_DIR"' EXIT
BODY="$WORK_DIR/body"
HEADERS="$WORK_DIR/headers"
TABLE="$WORK_DIR/table"
failures=0
checks=0

pass() { checks=$((checks + 1)); printf 'PASA  %s\n' "$1"; }
fail() { checks=$((checks + 1)); failures=$((failures + 1)); printf 'FALLA %s\n' "$1"; }
abort() { fail "$1"; printf 'Comprobaciones: %d, fallas: %d\n' "$checks" "$failures"; exit 1; }

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

# post <tarro> <ruta> <json> -> imprime el código HTTP.
post() {
    request "$1" POST "$2" -H "X-XSRF-TOKEN: $(xsrf_token "$1")" -H 'Content-Type: application/json' --data "$3"
}

expect() {
    local label="$1" want="$2" got="$3" filter="${4:-}"
    if [ "$got" != "$want" ]; then
        fail "$label: HTTP $got, se esperaba $want"
        return
    fi
    if [ -n "$filter" ] && ! jq -e "$filter" "$BODY" > /dev/null 2>&1; then
        fail "$label: HTTP $got sin cumplir $filter ($(jq -c '.data // .' "$BODY"))"
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

# ask <usuario> <tarro> <pregunta> <filtro jq sobre .data>: comprueba HTTP 200 + filtro y anota la fila.
ask() {
    local user="$1" jar="$2" question="$3" filter="$4" code
    code="$(post "$jar" /api/assistant/ask "$(jq -nc --arg q "$question" '{question: $q}')")"
    expect "[$user] $question" 200 "$code" ".data | $filter"
    printf '| %s | %s | %s | %s | %s |\n' "$user" "$question" "$code" \
        "$(jq -r '.data.outcome // .code' "$BODY")" \
        "$(jq -r '[.data.tool_calls[]? | "\(.tool) \(.status) \(.arguments | tojson)"] | join("; ") | if . == "" then "ninguna" else . end' "$BODY")" \
        >> "$TABLE"
}

printf 'Humo del asistente contra %s\n' "$BASE_URL"

# 1. Sin sesión: la ruta exige autenticación.
anon="$WORK_DIR/anon.jar"
: > "$anon"
expect "[anónimo] GET /sanctum/csrf-cookie" 204 "$(request "$anon" GET /sanctum/csrf-cookie)"
expect "[anónimo] POST /api/assistant/ask" 401 \
    "$(post "$anon" /api/assistant/ask '{"question":"¿Qué lotes están por vencer?"}')"

reg="$WORK_DIR/regente.jar"
med="$WORK_DIR/medico.jar"
login regente "$reg"
login medico "$med"

# 2. Pregunta de ejemplo de la parte C. El resultado depende de los datos sembrados (answered o no_results);
#    lo fijo es la herramienta, su estado, sus argumentos (nombres resueltos del catálogo) y que todo lote
#    listado sea de Farmacia Central.
ask regente "$reg" "¿Qué lotes de acetaminofén vencen en los próximos 60 días en la farmacia central?" \
    '(.outcome == "answered" or .outcome == "no_results")
     and (.tool_calls | length) == 1 and .tool_calls[0].tool == "find_expiring_lots" and .tool_calls[0].status == "ok"
     and .tool_calls[0].arguments.days == 60
     and (.tool_calls[0].arguments.product | ascii_downcase | startswith("acetamin"))
     and (.tool_calls[0].arguments.warehouse | ascii_downcase) == "farmacia central"
     and ([.answer | split("\n")[] | select(startswith("- "))] | all(test("Farmacia Central")))'

# 3. Traslados: conteo por estado (0 en tránsito es válido según los datos) y detalle de un traslado existente.
ask regente "$reg" "¿Cuántos traslados hay en tránsito?" \
    '(.outcome == "answered" or .outcome == "no_results")
     and .tool_calls[0].tool == "get_transfer_status" and .tool_calls[0].status == "ok"
     and .tool_calls[0].arguments.status == "EN_TRANSITO"'
expect "[regente] GET /api/transfers" 200 "$(request "$reg" GET "/api/transfers?per_page=1")" '(.data | type) == "array"'
transfer="$(jq -r '.data[0].id // empty' "$BODY")"
# Base recién sembrada (staging del CI): la semilla no trae traslados. El regente crea un BORRADOR (sin efecto
# en existencias) con un lote vigente de una bodega hacia otra, así la pregunta por id siempre tiene sujeto.
if [ -z "$transfer" ]; then
    expect "[regente] GET /api/stock" 200 "$(request "$reg" GET /api/stock)"
    read -r origin lot <<< "$(jq -r '[.data[] | select(.lot.is_expired == false and .quantity > 0)][0]
        | "\(.warehouse.id // "") \(.lot.id // "")"' "$BODY")"
    expect "[regente] GET /api/warehouses" 200 "$(request "$reg" GET /api/warehouses)"
    destination="$(jq -r --argjson o "${origin:-0}" '[.data[] | select(.id != $o)][0].id // empty' "$BODY")"
    [ -n "${lot:-}" ] && [ -n "$destination" ] || abort "sin lote vigente ni bodega destino para crear un traslado"
    expect "[regente] POST /api/transfers (BORRADOR para la pregunta por id)" 201 "$(post "$reg" /api/transfers \
        "$(jq -nc --argjson o "$origin" --argjson d "$destination" --argjson l "$lot" \
            '{origin_warehouse_id: $o, destination_warehouse_id: $d, notes: "Humo S7: traslado de referencia",
              lines: [{lot_id: $l, quantity: 1}]}')")" '.data.status == "BORRADOR"'
    transfer="$(jq -r '.data.id // empty' "$BODY")"
fi
[ -n "$transfer" ] || abort "sin traslado para la pregunta por id"
ask regente "$reg" "¿En qué estado está el traslado $transfer?" \
    ".outcome == \"answered\" and .tool_calls[0].tool == \"get_transfer_status\" and .tool_calls[0].status == \"ok\"
     and .tool_calls[0].arguments.transfer_id == $transfer"

# 4. Datos de pacientes fuera del modelo: el filtro previo responde sin herramientas ni proveedor.
ask regente "$reg" "¿Qué medicamentos le dispensaron a la paciente Ana Sintética Pérez?" \
    '.outcome == "out_of_scope" and (.tool_calls | length) == 0'

# 5. Médico sin inventory.view: herramienta denegada, respuesta fija, ningún código de lote.
ask medico "$med" "¿Qué lotes vencen en los próximos 30 días?" \
    '.outcome == "not_permitted" and .answer == "Tu rol no tiene permiso para consultar esa información."
     and .tool_calls[0].tool == "find_expiring_lots" and .tool_calls[0].status == "denied"
     and (.answer | test("L-") | not)'

printf '\n| Rol | Pregunta | HTTP | outcome | Herramientas (estado, argumentos) |\n|---|---|---|---|---|\n'
cat "$TABLE"
printf '\nComprobaciones: %d, fallas: %d\n' "$checks" "$failures"
[ "$failures" -eq 0 ]
