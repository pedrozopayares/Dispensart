#!/bin/bash
# Statusline for the Dispensart harness.
#
# Renders two badges on one line:
#   1. The caveman plugin badge (delegated to the plugin's own script, so its
#      behaviour and hardening stay the single source of truth).
#   2. A harness-mode badge: [NITRO] or [AUTOPILOT], driven by the flag file
#      that harness-mode-tracker.sh writes on UserPromptSubmit.
#
# Wired from .claude/settings.json -> statusLine.

INPUT=$(cat)

# --- 1. caveman badge -------------------------------------------------------
CAVEMAN_SCRIPT=$(ls -d "${CLAUDE_CONFIG_DIR:-$HOME/.claude}"/plugins/cache/caveman/caveman/*/src/hooks/caveman-statusline.sh 2>/dev/null | tail -1)
[ -n "$CAVEMAN_SCRIPT" ] && [ -f "$CAVEMAN_SCRIPT" ] && bash "$CAVEMAN_SCRIPT" </dev/null

# --- 2. harness-mode badge --------------------------------------------------
# The session id keys the flag, so two sessions on the same repo never show
# each other's mode.
SESSION=$(printf '%s' "$INPUT" | sed -n 's/.*"session_id"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p')
SESSION=$(printf '%s' "$SESSION" | tr -cd 'a-zA-Z0-9-')
[ -z "$SESSION" ] && exit 0

FLAG="$HOME/.claude-harness-mode/$SESSION"

# Refuse symlinks: a planted link could make the statusline render arbitrary
# bytes (including ANSI escapes) on every keystroke. Same rule the caveman
# script applies to its own flag.
[ -L "$FLAG" ] && exit 0
[ ! -f "$FLAG" ] && exit 0

MODE=$(head -c 32 "$FLAG" 2>/dev/null | tr -d '\n\r' | tr '[:upper:]' '[:lower:]')
MODE=$(printf '%s' "$MODE" | tr -cd 'a-z')

case "$MODE" in
  nitro)     printf ' \033[38;5;46m[NITRO]\033[0m' ;;
  autopilot) printf ' \033[38;5;141m[AUTOPILOT]\033[0m' ;;
  *) ;;
esac

# Claude Code hides the whole status bar when the script exits non-zero.
exit 0
