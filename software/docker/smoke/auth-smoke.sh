#!/usr/bin/env bash
# Prueba de humo de la sesión Sanctum SPA sobre el stack real, a través del Nginx de web (mismo origen).
# Por cada usuario semilla: cookie CSRF -> login -> me -> escritura sin X-XSRF-TOKEN (419) -> logout -> me (401).
# Además: /health y /ready por el proxy, y login sin Origin (curl puro) rechazado con 403 (design D1).
#
# Uso (stack en marcha):  software/docker/smoke/auth-smoke.sh
# Variables: WEB_PORT (8090), SMOKE_BASE_URL (http://localhost:$WEB_PORT),
#            SEED_USER_PASSWORD (vacía = valor por defecto SOLO de desarrollo, el mismo de la API).
# Requiere: bash, curl. Sale con 0 solo si todas las comprobaciones pasan. Nunca imprime la contraseña.
set -euo pipefail

BASE_URL="${SMOKE_BASE_URL:-http://localhost:${WEB_PORT:-8090}}"
# Valor por defecto SOLO DE DESARROLLO LOCAL (config/dispensart.php); usuarios sintéticos.
PASSWORD="${SEED_USER_PASSWORD:-dispensart-dev-only}"

WORK_DIR="$(mktemp -d)"
trap 'rm -rf "$WORK_DIR"' EXIT
BODY="$WORK_DIR/body"
failures=0
checks=0

pass() { checks=$((checks + 1)); printf 'PASA  %s\n' "$1"; }
fail() { checks=$((checks + 1)); failures=$((failures + 1)); printf 'FALLA %s\n' "$1"; }

# Valor decodificado (URL) de la cookie XSRF-TOKEN del tarro; vacío si no existe.
xsrf_token() {
    local raw
    raw="$(awk -F '\t' '$6 == "XSRF-TOKEN" { print $7 }' "$1" | tail -n 1)"
    printf '%b' "${raw//%/\\x}"
}

# request <tarro> <método> <ruta> [args de curl...] -> imprime el código HTTP; cuerpo en $BODY.
request() {
    local jar="$1" method="$2" path="$3"
    shift 3
    curl -sS -o "$BODY" -w '%{http_code}' -X "$method" \
        -b "$jar" -c "$jar" \
        -H 'Accept: application/json' \
        "$@" "$BASE_URL$path"
}

# expect <descripción> <esperado> <obtenido> [texto que debe contener el cuerpo]
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

printf 'Humo de autenticación contra %s\n' "$BASE_URL"
warehouse_body='{"code":"SMOKE","name":"Humo"}'

ops_jar="$WORK_DIR/ops.jar"
: > "$ops_jar"
expect "GET /health por el proxy" 200 "$(request "$ops_jar" GET /health)"
expect "GET /ready por el proxy" 200 "$(request "$ops_jar" GET /ready)"

# Cuerpo JSON de login armado con printf: evita la expansión de llaves dentro de $(...).
login_body() { printf '{"email":"%s","password":"%s"}' "$1" "$PASSWORD"; }

# Sin Origin ni Referer la petición no es de la SPA: sin sesión -> 403 sin cookie de sesión (design D1).
payload="$(login_body admin@dispensart.test)"
expect "login sin Origin (cliente ajeno)" 403 "$(request "$ops_jar" POST /api/auth/login \
    -H 'Content-Type: application/json' --data "$payload")" '"code":"forbidden"'

for pair in \
    auxiliar:auxiliar_farmacia \
    regente:regente_farmacia \
    medico:medico \
    auditor:auditor \
    admin:admin; do
    user="${pair%%:*}"
    role="${pair#*:}"
    email="$user@dispensart.test"
    jar="$WORK_DIR/$user.jar"
    : > "$jar"
    spa=(-H "Origin: $BASE_URL" -H "Referer: $BASE_URL/")

    expect "[$user] GET /sanctum/csrf-cookie" 204 "$(request "$jar" GET /sanctum/csrf-cookie "${spa[@]}")"
    token="$(xsrf_token "$jar")"
    if [ -n "$token" ]; then pass "[$user] cookie XSRF-TOKEN emitida"; else fail "[$user] cookie XSRF-TOKEN ausente"; fi

    payload="$(login_body "$email")"
    expect "[$user] POST /api/auth/login" 200 "$(request "$jar" POST /api/auth/login "${spa[@]}" \
        -H "X-XSRF-TOKEN: $token" -H 'Content-Type: application/json' --data "$payload")" "\"role\":\"$role\""

    expect "[$user] GET /api/auth/me" 200 "$(request "$jar" GET /api/auth/me "${spa[@]}")" "\"email\":\"$email\""

    # Escritura con sesión válida pero sin X-XSRF-TOKEN: 419 real (CSRF antes que permisos, design D2).
    expect "[$user] POST /api/warehouses sin X-XSRF-TOKEN" 419 "$(request "$jar" POST /api/warehouses "${spa[@]}" \
        -H 'Content-Type: application/json' --data "$warehouse_body")"

    token="$(xsrf_token "$jar")"
    expect "[$user] POST /api/auth/logout" 204 "$(request "$jar" POST /api/auth/logout "${spa[@]}" \
        -H "X-XSRF-TOKEN: $token")"

    expect "[$user] GET /api/auth/me tras logout" 401 "$(request "$jar" GET /api/auth/me "${spa[@]}")"
done

printf 'Comprobaciones: %d, fallas: %d\n' "$checks" "$failures"
[ "$failures" -eq 0 ]
