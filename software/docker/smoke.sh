#!/usr/bin/env bash
# Humo del stack completo (staging del CI y local): db, api y web en healthy; GET /health y GET /ready
# por el proxy de web con HTTP 200; GET / con el documento de la SPA; api y web sin usuario root.
# Ante cualquier fallo imprime `docker compose logs` y sale con código distinto de 0.
#
# Uso (stack en marcha):  bash software/docker/smoke.sh
# Variables: SMOKE_BASE_URL (http://localhost:$WEB_PORT, WEB_PORT=8090), SMOKE_TIMEOUT (segundos, 180).
# Respeta COMPOSE_PROJECT_NAME, API_IMAGE y WEB_IMAGE del entorno. Requiere: bash, curl, docker.
set -euo pipefail

BASE_URL="${SMOKE_BASE_URL:-http://localhost:${WEB_PORT:-8090}}"
TIMEOUT="${SMOKE_TIMEOUT:-180}"
COMPOSE_FILE_PATH="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/compose.yaml"

WORK_DIR="$(mktemp -d)"
BODY="$WORK_DIR/body"
failures=0

compose() { docker compose -f "$COMPOSE_FILE_PATH" "$@"; }
pass() { printf 'PASA  %s\n' "$1"; }
fail() { failures=$((failures + 1)); printf 'FALLA %s\n' "$1"; }

# Al salir: si algo falló (o el script abortó), logs del stack para diagnosticar.
on_exit() {
    local code=$?
    if [ "$code" -ne 0 ] || [ "$failures" -ne 0 ]; then
        printf '\n== docker compose logs (humo fallido) ==\n'
        compose logs --no-color --tail 200 || true
        rm -rf "$WORK_DIR"
        exit 1
    fi
    rm -rf "$WORK_DIR"
}
trap on_exit EXIT

# Estado de salud de un servicio de compose: healthy, unhealthy, starting o ausente.
health_of() {
    local id
    id="$(compose ps -q "$1" 2>/dev/null || true)"
    [ -n "$id" ] || { echo ausente; return; }
    docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}sin-healthcheck{{end}}' "$id" 2>/dev/null || echo ausente
}

printf 'Humo del stack contra %s\n' "$BASE_URL"

# 1. Los tres servicios en healthy dentro del plazo.
deadline=$((SECONDS + TIMEOUT))
for svc in db api web; do
    status="$(health_of "$svc")"
    while [ "$status" != healthy ] && [ "$SECONDS" -lt "$deadline" ]; do
        sleep 2
        status="$(health_of "$svc")"
    done
    if [ "$status" = healthy ]; then pass "$svc healthy"; else fail "$svc en estado '$status' tras ${TIMEOUT}s"; fi
done

# 2. Endpoints por el proxy de web (mismo origen que la SPA).
# check <ruta> <texto que debe contener el cuerpo, opcional>
check() {
    local path="$1" needle="${2:-}" code
    code="$(curl -sS -o "$BODY" -w '%{http_code}' --max-time 10 "$BASE_URL$path" || echo 000)"
    if [ "$code" != 200 ]; then
        fail "GET $path: HTTP $code, se esperaba 200"
    elif [ -n "$needle" ] && ! grep -qF -- "$needle" "$BODY"; then
        fail "GET $path: HTTP 200 sin '$needle' en el cuerpo"
    else
        pass "GET $path: HTTP 200"
    fi
}
check /health
check /ready
check / 'id="root"'

# 3. Ningún proceso principal como root.
for svc in api web; do
    uid="$(compose exec -T "$svc" id -u 2>/dev/null || echo error)"
    if [ "$uid" != error ] && [ "$uid" != 0 ]; then pass "$svc corre como uid $uid"; else fail "$svc: id -u = $uid"; fi
done

if [ "$failures" -ne 0 ]; then
    printf '\nHumo FALLIDO: %d comprobaciones fallaron\n' "$failures"
    exit 1
fi
printf '\nHumo VERDE\n'
# Evidencia S8 4.3: rama de trabajo sin entrega.
