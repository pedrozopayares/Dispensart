#!/usr/bin/env bash
# Guarda de cobertura: cada operación (MÉTODO ruta) de software/api/openapi.json tiene al menos una petición en la
# colección de Postman, y cada petición de la colección corresponde a una operación del contrato o a la lista
# explícita de rutas fuera del contrato. Sin stack, sin red, sin secretos.
#
# Uso (desde cualquier carpeta):  bash software/docs/postman/check-coverage.sh <colección.json>
# Sale con 0 si coinciden; con 1 nombrando cada operación sin petición y cada petición fuera del contrato;
# con 2 si falta un argumento o un archivo. Requiere: bash, jq.
set -euo pipefail
# Orden de bytes: sort y comm deben coincidir sin depender del idioma del sistema.
export LC_ALL=C

COLLECTION="${1:-}"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
OPENAPI="$REPO_ROOT/software/api/openapi.json"

# Rutas fuera del contrato que la colección puede usar (Sanctum emite la cookie CSRF; no es parte de la API).
ALLOWED_EXTRA=$'GET /sanctum/csrf-cookie'

[ -n "$COLLECTION" ] || { echo "Uso: $0 <colección.json>" >&2; exit 2; }
[ -f "$COLLECTION" ] || { echo "No existe la colección: $COLLECTION" >&2; exit 2; }
[ -f "$OPENAPI" ] || { echo "No existe el contrato: $OPENAPI" >&2; exit 2; }

# Operaciones del contrato. Prefijo = servers de la ruta (/health y /ready declaran "/") o el global ("/api").
# Parámetros de ruta normalizados a {}.
contract="$(jq -r '
    (.servers[0].url // "") as $global
    | .paths | to_entries[] | .key as $path
    | ((.value.servers[0].url // $global) | sub("/$"; "")) as $prefix
    | .value | keys[] | select(test("^(get|put|post|patch|delete|head|options)$"))
    | "\(ascii_upcase) \($prefix)\($path | gsub("\\{[^}/]+\\}"; "{}"))"' "$OPENAPI" | sort -u)"

# Peticiones de la colección, en carpetas anidadas a cualquier profundidad. URL como texto u objeto (raw).
# Sin {{baseUrl}}, sin query ni fragmento; segmentos {{var}} o :var normalizados a {}.
requests="$(jq -r '
    [.. | objects | select(has("request")) | .request] | .[]
    | (if (.url | type) == "string" then .url else (.url.raw // "") end) as $raw
    | ($raw | sub("^\\{\\{baseUrl\\}\\}"; "") | sub("[?#].*$"; "")
        | gsub("\\{\\{[^}]+\\}\\}"; "{}") | gsub("/:[A-Za-z_][A-Za-z0-9_]*"; "/{}")) as $path
    | "\(.method | ascii_upcase) \($path)"' "$COLLECTION" | sort -u)"

allowed="$(printf '%s\n%s\n' "$contract" "$ALLOWED_EXTRA" | sort -u)"
missing="$(comm -23 <(printf '%s\n' "$contract") <(printf '%s\n' "$requests") | sed '/^$/d')"
extra="$(comm -13 <(printf '%s\n' "$allowed") <(printf '%s\n' "$requests") | sed '/^$/d')"
covered="$(comm -12 <(printf '%s\n' "$contract") <(printf '%s\n' "$requests") | sed '/^$/d')"

count() { if [ -z "$1" ]; then echo 0; else printf '%s\n' "$1" | wc -l | tr -d ' '; fi; }

printf 'Operaciones cubiertas (%s de %s):\n' "$(count "$covered")" "$(count "$contract")"
[ -z "$covered" ] || printf '%s\n' "$covered" | sed 's/^/  /'

status=0
if [ -n "$missing" ]; then
    status=1
    printf '%s\n' "$missing" | sed 's/^/FALLA operación del contrato sin petición en la colección: /'
fi
if [ -n "$extra" ]; then
    status=1
    printf '%s\n' "$extra" | sed 's/^/FALLA petición fuera del contrato: /'
fi

if [ "$status" -eq 0 ]; then
    printf 'Cobertura del contrato COMPLETA: %s operaciones\n' "$(count "$covered")"
else
    printf 'Cobertura del contrato INCOMPLETA: %s sin petición, %s fuera del contrato\n' "$(count "$missing")" "$(count "$extra")"
fi
exit "$status"
