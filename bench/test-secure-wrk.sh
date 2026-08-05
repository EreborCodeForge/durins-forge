#!/usr/bin/env bash
# Stress /api/products/secure with wrk + Bearer auth
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BASE_URL="${BASE_URL:-http://127.0.0.1:8080}"
TOKEN="${TOKEN:-demo-token}"
THREADS="${THREADS:-2}"
CONNECTIONS="${CONNECTIONS:-20}"
DURATION="${DURATION:-10s}"

command -v wrk >/dev/null || { echo "instale wrk (WSL: sudo apt install wrk)"; exit 1; }

# Inject token into a temp lua (secure.lua uses hardcoded demo-token by default)
LUA="${ROOT}/bench/wrk/secure.lua"
if [[ "$TOKEN" != "demo-token" ]]; then
  LUA="$(mktemp --suffix=.lua)"
  sed "s/Bearer demo-token/Bearer ${TOKEN}/" "${ROOT}/bench/wrk/secure.lua" > "$LUA"
  trap 'rm -f "$LUA"' EXIT
fi

echo "==> wrk SECURE + Bearer ${TOKEN}"
echo "    ${BASE_URL}/api/products/secure  t=${THREADS} c=${CONNECTIONS} d=${DURATION}"
wrk -t"${THREADS}" -c"${CONNECTIONS}" -d"${DURATION}" --latency \
  -s "${LUA}" \
  "${BASE_URL}/api/products/secure"

echo ""
echo "==> wrk SECURE anon (expect 401)"
wrk -t"${THREADS}" -c"${CONNECTIONS}" -d"${DURATION}" --latency \
  -s "${ROOT}/bench/wrk/secure_anon.lua" \
  "${BASE_URL}/api/products/secure"
