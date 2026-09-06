#!/usr/bin/env bash
# Compara Durin vs Laravel (versus) nos endpoints cold + hot.
# Ambos os servidores devem estar no ar antes de rodar.
#
#   Durin:   vendor/bin/forge serve --host=0.0.0.0 --port=8080
#   Laravel: cd versus && php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8081
#
# Uso:
#   ./bench/versus-compare.sh
#   THREADS=8 CONNECTIONS=64 DURATION=30s ./bench/versus-compare.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DURIN_URL="${DURIN_URL:-http://127.0.0.1:8080}"
LARAVEL_URL="${LARAVEL_URL:-http://127.0.0.1:8081}"
THREADS="${THREADS:-2}"
CONNECTIONS="${CONNECTIONS:-16}"
DURATION="${DURATION:-30s}"
LUA="${ROOT}/bench/wrk/benchmark_data.lua"
WORKDIR="${TMPDIR:-/tmp}/durin-versus-$$"
mkdir -p "$WORKDIR"
trap 'rm -rf "$WORKDIR"' EXIT

need() {
  command -v "$1" >/dev/null 2>&1 || {
    echo "missing: $1" >&2
    exit 1
  }
}

need wrk
need curl

check_endpoint() {
  local url="$1"
  local label="$2"
  local code
  code="$(curl -s -o /dev/null -w '%{http_code}' -m 15 "${url}" || true)"
  if [[ "$code" != "200" ]]; then
    echo "${label} not ready (HTTP ${code}): ${url}" >&2
    exit 1
  fi
}

# Extrai métricas do stdout do wrk (--latency).
parse_wrk() {
  local file="$1"
  local req_s p50 p75 p90 p99 avg_lat timeouts http200
  req_s="$(grep -E 'Requests/sec:' "$file" | awk '{print $2}')"
  p50="$(grep -E '^\s+50%' "$file" | awk '{print $2}')"
  p75="$(grep -E '^\s+75%' "$file" | awk '{print $2}')"
  p90="$(grep -E '^\s+90%' "$file" | awk '{print $2}')"
  p99="$(grep -E '^\s+99%' "$file" | awk '{print $2}')"
  avg_lat="$(awk '/Thread Stats/{getline; if ($1=="Latency") print $2}' "$file")"
  timeouts="$(grep -E 'Socket errors:' "$file" | sed -n 's/.*timeout \([0-9]*\).*/\1/p')"
  timeouts="${timeouts:-0}"
  http200="$(grep -E 'HTTP 200:' "$file" | awk '{print $3}')"
  http200="${http200:-0}"
  printf '%s|%s|%s|%s|%s|%s|%s|%s\n' \
    "${req_s:-?}" "${avg_lat:-?}" "${p50:-?}" "${p75:-?}" "${p90:-?}" "${p99:-?}" \
    "${timeouts}" "${http200}"
}

run_one() {
  local label="$1"
  local url="$2"
  local out="$3"
  echo ""
  echo "========== ${label} =========="
  echo "wrk -t${THREADS} -c${CONNECTIONS} -d${DURATION} --latency ${url}"
  wrk -t"${THREADS}" -c"${CONNECTIONS}" -d"${DURATION}" --latency \
    -s "${LUA}" \
    "${url}" | tee "${out}"
}

ratio() {
  local a="$1" b="$2"
  if [[ "$a" == "?" || "$b" == "?" || "$b" == "0" || -z "$b" ]]; then
    echo "?"
    return
  fi
  awk -v a="$a" -v b="$b" 'BEGIN { if (b+0==0) print "?"; else printf "%.2fx", a/b }'
}

print_row() {
  local name="$1" metrics="$2"
  IFS='|' read -r rps avg p50 p75 p90 p99 to http200 <<<"$metrics"
  printf '%-22s %10s %10s %10s %10s %10s %8s %8s\n' \
    "$name" "$rps" "$avg" "$p50" "$p90" "$p99" "$to" "$http200"
}

echo "==> preflight"
check_endpoint "${DURIN_URL}/api/benchmark/data" "Durin cold"
check_endpoint "${DURIN_URL}/api/benchmark/data-hot" "Durin hot"
check_endpoint "${LARAVEL_URL}/api/benchmark/data" "Laravel cold"
check_endpoint "${LARAVEL_URL}/api/benchmark/data-hot" "Laravel hot"
echo "OK  Durin=${DURIN_URL}  Laravel=${LARAVEL_URL}"
echo "    -t${THREADS} -c${CONNECTIONS} -d${DURATION}"

run_one "Durin cold (prepare)" \
  "${DURIN_URL}/api/benchmark/data" \
  "${WORKDIR}/durin-cold.txt"

run_one "Durin hot (stmt cache)" \
  "${DURIN_URL}/api/benchmark/data-hot" \
  "${WORKDIR}/durin-hot.txt"

run_one "Laravel cold (prepare)" \
  "${LARAVEL_URL}/api/benchmark/data" \
  "${WORKDIR}/laravel-cold.txt"

run_one "Laravel hot (stmt cache)" \
  "${LARAVEL_URL}/api/benchmark/data-hot" \
  "${WORKDIR}/laravel-hot.txt"

D_COLD="$(parse_wrk "${WORKDIR}/durin-cold.txt")"
D_HOT="$(parse_wrk "${WORKDIR}/durin-hot.txt")"
L_COLD="$(parse_wrk "${WORKDIR}/laravel-cold.txt")"
L_HOT="$(parse_wrk "${WORKDIR}/laravel-hot.txt")"

IFS='|' read -r drps _ _ _ _ _ _ _ <<<"$D_COLD"
IFS='|' read -r dhrps _ _ _ _ _ _ _ <<<"$D_HOT"
IFS='|' read -r lrps _ _ _ _ _ _ _ <<<"$L_COLD"
IFS='|' read -r lhrps _ _ _ _ _ _ _ <<<"$L_HOT"

echo ""
echo "######################################################################"
echo "# RESUMO  versus  (-t${THREADS} -c${CONNECTIONS} -d${DURATION})"
echo "######################################################################"
printf '%-22s %10s %10s %10s %10s %10s %8s %8s\n' \
  "cenário" "req/s" "avg" "p50" "p90" "p99" "timeout" "HTTP200"
printf '%-22s %10s %10s %10s %10s %10s %8s %8s\n' \
  "----------------------" "----------" "----------" "----------" "----------" "----------" "--------" "--------"
print_row "Durin cold" "$D_COLD"
print_row "Durin hot" "$D_HOT"
print_row "Laravel cold" "$L_COLD"
print_row "Laravel hot" "$L_HOT"
echo ""
echo "Ganhos hot vs cold (mesmo framework):"
echo "  Durin hot/cold:   $(ratio "$dhrps" "$drps")"
echo "  Laravel hot/cold: $(ratio "$lhrps" "$lrps")"
echo ""
echo "Durin vs Laravel (mesmo modo):"
echo "  cold Durin/Laravel: $(ratio "$drps" "$lrps")"
echo "  hot  Durin/Laravel: $(ratio "$dhrps" "$lhrps")"
echo "######################################################################"
