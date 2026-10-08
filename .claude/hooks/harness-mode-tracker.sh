#!/bin/bash
# UserPromptSubmit hook: track whether the session is in Nitro or Autopilot
# mode, so harness-statusline.sh can render a badge for it.
#
# Writes $HOME/.claude-harness-mode/<session_id> containing "nitro" or
# "autopilot"; removes it when the user turns the mode off. The flag is keyed
# by session id so concurrent sessions on this repo stay independent.
#
# The hook only writes state. It never blocks a prompt and always exits 0.

INPUT=$(cat)
JQ=/usr/bin/jq
[ -x "$JQ" ] || JQ=$(command -v jq)
[ -x "$JQ" ] || exit 0

SESSION=$(printf '%s' "$INPUT" | "$JQ" -r '.session_id // ""' 2>/dev/null | tr -cd 'a-zA-Z0-9-')
PROMPT=$(printf '%s' "$INPUT" | "$JQ" -r '.prompt // ""' 2>/dev/null)
[ -z "$SESSION" ] && exit 0

DIR="$HOME/.claude-harness-mode"
mkdir -p "$DIR" 2>/dev/null || exit 0
FLAG="$DIR/$SESSION"
[ -L "$FLAG" ] && exit 0

# Garbage-collect flags from sessions that ended long ago.
find "$DIR" -maxdepth 1 -type f -mtime +7 -delete 2>/dev/null

# Only the first 400 characters are inspected: the activation phrase is an
# instruction, not something buried in a pasted log.
HEAD=$(printf '%s' "$PROMPT" | head -c 400)

OFF_RE='(^|[[:space:]])/?(nitro|autopilot|autopiloto)[[:space:]]+(off|stop)|(stop|end|exit|disable|desactiva|desactivar|detener|termina|terminar|sal|salir)([[:space:]]+(de|del|the))?[[:space:]]+(modo[[:space:]]+)?(nitro|autopilot|autopiloto)'
NITRO_RE='^[[:space:]]*/nitro([[:space:]]|$)|(activa|activar|inicia|iniciar|modo|start|enable|enter)([[:space:]]+(el|the))?[[:space:]]+nitro'
AUTO_RE='^[[:space:]]*/?autopilot(o)?([[:space:]]|$)|(activa|activar|inicia|iniciar|modo|start|enable|enter)([[:space:]]+(el|the))?[[:space:]]+autopilot(o)?'

if printf '%s' "$HEAD" | /usr/bin/grep -Eiq "$OFF_RE"; then
  rm -f "$FLAG" 2>/dev/null
elif printf '%s' "$HEAD" | /usr/bin/grep -Eiq "$NITRO_RE"; then
  printf 'nitro' > "$FLAG"
elif printf '%s' "$HEAD" | /usr/bin/grep -Eiq "$AUTO_RE"; then
  printf 'autopilot' > "$FLAG"
fi

exit 0
