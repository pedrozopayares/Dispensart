#!/bin/bash
# SessionStart hook: make the project-local OpenSpec CLI available.
#
# OpenSpec is pinned in the root package.json, not installed globally, so
# every machine uses the same version. On a fresh clone the hook installs it
# once (npm ci), then exports node_modules/.bin through $CLAUDE_ENV_FILE so
# agents and the opsx skills can call plain `openspec`.
#
# The hook never blocks the session and always exits 0.

ROOT="${CLAUDE_PROJECT_DIR:-$(pwd)}"
BIN="$ROOT/node_modules/.bin"

if [ ! -x "$BIN/openspec" ]; then
  if command -v npm >/dev/null 2>&1; then
    npm ci --prefix "$ROOT" --silent --no-audit --no-fund >/dev/null 2>&1
  fi
  if [ ! -x "$BIN/openspec" ]; then
    echo "OpenSpec CLI missing and auto-install failed. Install Node.js >= 20.19, then run: npm ci" >&2
    exit 0
  fi
fi

[ -n "$CLAUDE_ENV_FILE" ] && printf 'export PATH="%s:$PATH"\n' "$BIN" >> "$CLAUDE_ENV_FILE"

exit 0
