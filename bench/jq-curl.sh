#!/usr/bin/env bash
# Pretty-print JSON from Durin bench endpoints (requires jq on PATH).
# Usage:
#   ./bench/jq-curl.sh http://127.0.0.1:8080/api/benchmark/compare
#   ./bench/jq-curl.sh http://127.0.0.1:8080/api/benchmark/compare '.order_pdo_then_mazarbul'
set -euo pipefail

URL="${1:?usage: $0 <url> [jq-filter]}"
FILTER="${2:-.}"

if ! command -v jq >/dev/null 2>&1; then
  if [[ -x "${HOME}/.local/bin/jq" ]]; then
    export PATH="${HOME}/.local/bin:${PATH}"
  else
    echo "jq not found. Install: curl -fsSL -o ~/.local/bin/jq https://github.com/jqlang/jq/releases/download/jq-1.7.1/jq-linux-amd64 && chmod +x ~/.local/bin/jq" >&2
    exit 1
  fi
fi

curl -sS "$URL" | jq "$FILTER"
