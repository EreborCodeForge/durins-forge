#!/usr/bin/env bash
# Stress DX: auth + rate-limit endpoints (requires forge serve + wrk)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BASE_URL="${BASE_URL:-http://127.0.0.1:8080}"
THREADS="${THREADS:-2}"
CONNECTIONS="${CONNECTIONS:-20}"
DURATION="${DURATION:-10s}"

need() {
  command -v "$1" >/dev/null 2>&1 || {
    echo "missing: $1" >&2
    exit 1
  }
}

need wrk
need curl

echo "==> health check ${BASE_URL}/api/health"
code="$(curl -s -o /dev/null -w '%{http_code}' "${BASE_URL}/api/health" || true)"
if [[ "$code" != "200" ]]; then
  echo "server not ready (HTTP ${code}). Start: vendor/bin/forge serve --host=0.0.0.0 --port=8080" >&2
  exit 1
fi

echo ""
echo "========== 1) PUBLIC products (baseline) =========="
wrk -t"${THREADS}" -c"${CONNECTIONS}" -d"${DURATION}" --latency \
  "${BASE_URL}/api/products"

echo ""
echo "========== 2) SECURE + Bearer (expect mostly 200) =========="
wrk -t"${THREADS}" -c"${CONNECTIONS}" -d"${DURATION}" --latency \
  -s "${ROOT}/bench/wrk/secure.lua" \
  "${BASE_URL}/api/products/secure"

echo ""
echo "========== 3) SECURE anon (expect 401) =========="
wrk -t"${THREADS}" -c"${CONNECTIONS}" -d"${DURATION}" --latency \
  -s "${ROOT}/bench/wrk/secure_anon.lua" \
  "${BASE_URL}/api/products/secure"

echo ""
echo "========== 4) RATELIMIT sequential (5x200 then 429) =========="
# Clear window tip: wait if you just hammered; or restart workers / wait 60s
for i in $(seq 1 8); do
  c="$(curl -s -o /dev/null -w '%{http_code}' "${BASE_URL}/api/products/limited")"
  echo "  #${i} → HTTP ${c}"
done

echo ""
echo "========== 5) RATELIMIT wrk burst (many 429) =========="
echo "(if previous curl already spent the 5 tokens, expect almost all 429)"
wrk -t"${THREADS}" -c"${CONNECTIONS}" -d"${DURATION}" --latency \
  -s "${ROOT}/bench/wrk/ratelimit.lua" \
  "${BASE_URL}/api/products/limited"

echo ""
echo "Done. Tip: wait 60s (or clear storage/framework/cache throttle keys) before re-running step 4/5."
