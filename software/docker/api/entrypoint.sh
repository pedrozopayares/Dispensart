#!/bin/sh
# Arranque del contenedor api (design D2). SIN `set -x`: la APP_KEY nunca se imprime.
# 1. APP_KEY: la del entorno si viene; si no, la persistida en el volumen api_state; si no, se genera y persiste.
# 2. Caché de configuración, migraciones y siembra ANTES de levantar FPM + Nginx: el healthcheck (/ready) no pasa antes.
set -eu

KEY_FILE="${APP_KEY_FILE:-/var/lib/dispensart/app_key}"

# Línea de log JSON, mismo esquema que la API (correlation_id null: fuera de una petición).
log() {
    printf '{"timestamp":"%s","level":"%s","message":"%s","correlation_id":null,"context":{%s}}\n' \
        "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$1" "$2" "${3:-}" >&2
}

if [ -n "${APP_KEY:-}" ]; then
    log info "APP_KEY tomada del entorno" '"source":"env"'
elif [ -s "$KEY_FILE" ]; then
    APP_KEY="$(cat "$KEY_FILE")"
    export APP_KEY
    log info "APP_KEY tomada del volumen de estado" '"source":"volume"'
else
    APP_KEY="$(php artisan key:generate --show --no-ansi)"
    export APP_KEY
    (umask 077 && printf '%s' "$APP_KEY" > "$KEY_FILE")
    log info "APP_KEY generada y persistida en el volumen de estado" '"source":"generated"'
fi

if ! out="$(php artisan optimize --no-ansi 2>&1)"; then
    log error "fallo al cachear la configuracion" '"step":"optimize"'
    printf '%s\n' "$out" >&2
    exit 1
fi

if ! out="$(php artisan migrate --force --no-interaction --no-ansi 2>&1)"; then
    log error "fallo al aplicar migraciones" '"step":"migrate"'
    printf '%s\n' "$out" >&2
    exit 1
fi
applied="$(printf '%s\n' "$out" | grep -cE '[0-9]{4}_[0-9]{2}_[0-9]{2}_[0-9]{6}_.* DONE' || true)"
log info "migraciones al dia" "\"applied\":${applied}"

# 3. Siembra idempotente (design D11): solo crea lo que falta por clave natural, nunca pisa cambios del admin.
#    Con APP_ENV=production y sin SEED_USER_PASSWORD siembra el catálogo y omite los usuarios (D10).
#    Solo se captura stdout (texto de Artisan); stderr pasa tal cual: ahí van los avisos JSON del seeder.
if ! out="$(php artisan db:seed --force --no-interaction --no-ansi)"; then
    log error "fallo al sembrar datos sinteticos" '"step":"seed"'
    printf '%s\n' "$out" >&2
    exit 1
fi
log info "siembra al dia" '"step":"seed"'

exec "$@"
