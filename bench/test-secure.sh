#!/usr/bin/env bash
# Quick check: GET /api/products/secure with Bearer auth
set -euo pipefail
BASE_URL="${BASE_URL:-http://127.0.0.1:8080}"
TOKEN="${TOKEN:-demo-token}"

echo "==> WITHOUT auth (expect 401)"
curl -s -i "${BASE_URL}/api/products/secure" | head -n 15
echo ""
echo "==> WITH Authorization: Bearer ${TOKEN} (expect 200 + products)"
curl -s -i "${BASE_URL}/api/products/secure" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Accept: application/json" | head -n 40
