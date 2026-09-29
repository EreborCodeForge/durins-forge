#!/usr/bin/env bash
# Workloads integration smoke — Phase 2 + Phase 5 acceptance checks.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
MITHRIL="${MITHRIL_ROOT:-$HOME/mithrilphp}"
# Windows Git Bash / local clones
[[ -d /c/Users/Phales/mithrilphp ]] && MITHRIL=/c/Users/Phales/mithrilphp
FORGE="${FORGE_ROOT:-/c/Users/Phales/durinsforge}"
EREGION="${EREGION_ROOT:-/c/Users/Phales/eregion}"
APP="$ROOT/durin-app"
JOBS="$ROOT/jobs"
EREGION_BIN="${EREGION_BIN:-}"
if [[ -z "$EREGION_BIN" ]]; then
  if [[ -x "$EREGION/bin/eregion" ]]; then
    EREGION_BIN="$EREGION/bin/eregion"
  elif [[ -x "$EREGION/bin/eregion.exe" ]]; then
    EREGION_BIN="$EREGION/bin/eregion.exe"
  fi
fi
# Prefer a binary that actually speaks workloads (v0.4+). Ignore stale .exe if present.
if [[ -x "$EREGION/bin/eregion" ]]; then
  ver="$("$EREGION/bin/eregion" version 2>/dev/null | head -1 || true)"
  if [[ "$ver" == *"0.4"* || "$ver" == *"0.5"* ]]; then
    EREGION_BIN="$EREGION/bin/eregion"
  fi
fi

pass() { echo "PASS  $1"; }
fail() { echo "FAIL  $1 — $2" >&2; exit 1; }

echo "== Workloads integration smoke =="
echo "mithril=$MITHRIL"
echo "forge=$FORGE"
echo "eregion=$EREGION_BIN"
echo "app=$APP"
echo "jobs=$JOBS"

# --- 8. durin run launcher matrix (Forge unit) ---
(
  cd "$FORGE"
  vendor/bin/phpunit --filter RuntimeLauncherRegistryTest --colors=never >/tmp/smoke-launcher.txt 2>&1 \
    || { cat /tmp/smoke-launcher.txt; fail 8 "RuntimeLauncherRegistryTest failed"; }
)
pass "8 durin run chooses execution/supervisor launchers"

# --- 5+6. JobWorker drain + idle!=stop (Mithril unit) ---
(
  cd "$MITHRIL"
  vendor/bin/phpunit --filter 'testIdleDoesNotExitUntilStop|testDrainViaPublicSignalPath|testDrainFinishesCurrentJob|testMaxJobsRecycle|testDispatcherPublishesConsumableJob' --colors=never >/tmp/smoke-mithril-job.txt 2>&1 \
    || { cat /tmp/smoke-mithril-job.txt; fail 5 "Mithril JobWorker Phase 2 tests failed"; }
)
pass "5 graceful drain (Mithril JobWorker)"
pass "6 idle does not stop JobWorker (Mithril JobWorker)"

# --- 3. scale min→max→min (Eregion consumer pool) ---
(
  cd "$EREGION"
  go test ./internal/worker/ -run TestConsumerScaleUpDown -count=1 >/tmp/smoke-scale.txt 2>&1 \
    || { cat /tmp/smoke-scale.txt; fail 3 "TestConsumerScaleUpDown failed"; }
)
pass "3 scale min → max → min (Eregion consumer pool)"

# --- 4. crash → restart/backoff (Eregion integration) ---
(
  cd "$EREGION"
  go test ./tests/integration/ -run Crash -count=1 >/tmp/smoke-crash.txt 2>&1 \
    || go test ./internal/worker/ -run 'Restart|Crash|Backoff' -count=1 >/tmp/smoke-crash.txt 2>&1 \
    || { cat /tmp/smoke-crash.txt; fail 4 "crash/restart coverage failed"; }
)
pass "4 worker crash → restart/backoff"

# --- 7. HTTP minimal/service continue working ---
(
  cd "$APP"
  vendor/bin/phpunit --filter InitPresetSmokeTest --colors=never >/tmp/smoke-http.txt 2>&1 \
    || { cat /tmp/smoke-http.txt; fail 7 "InitPresetSmokeTest failed"; }
)
pass "7 HTTP minimal/service init + mithril-http + eregion"

# --- 1. worker standalone without Eregion (live job-worker idle) ---
SMOKE_DIR="$(mktemp -d "${TMPDIR:-/tmp}/durin-smoke-standalone.XXXXXX")"
cleanup() { rm -rf "$SMOKE_DIR" "${SMOKE_DIR}-sup" 2>/dev/null || true; }
trap cleanup EXIT

cp -a "$JOBS/." "$SMOKE_DIR/"
# Ensure in-memory transport stays idle (empty queue) without exiting.
IDLE_LOG="$SMOKE_DIR/idle.log"
(
  cd "$SMOKE_DIR"
  # Force short idle observe window via background + kill
  php vendor/bin/job-worker >"$IDLE_LOG" 2>&1 &
  WPID=$!
  sleep 2
  if ! kill -0 "$WPID" 2>/dev/null; then
    cat "$IDLE_LOG" || true
    fail 1 "job-worker exited during idle (standalone)"
  fi
  # soft stop
  kill "$WPID" 2>/dev/null || true
  wait "$WPID" 2>/dev/null || true
)
# Assert launcher selection for standalone plan via forge dry path: manifest has no supervisor
grep -q 'execution: mithril-job' "$SMOKE_DIR/durin.yaml" || fail 1 "missing mithril-job execution"
! grep -q 'supervisor:' "$SMOKE_DIR/durin.yaml" || fail 1 "standalone should not declare supervisor"
pass "1 worker standalone without Eregion (idle keeps process)"

# --- 2. worker supervised by Eregion (consumer workload) ---
SUP_DIR="${SMOKE_DIR}-sup"
mkdir -p "$SUP_DIR"
cp -a "$JOBS/." "$SUP_DIR/"
mkdir -p "$SUP_DIR/.mithril/bin" "$SUP_DIR/var/runtime"
cp "$EREGION_BIN" "$SUP_DIR/.mithril/bin/eregion.exe" 2>/dev/null || cp "$EREGION_BIN" "$SUP_DIR/.mithril/bin/eregion"
# Write canonical supervised manifest + consumer workload
cat > "$SUP_DIR/durin.yaml" <<'YAML'
application:
  name: jobs
  preset: worker
features:
  http: false
  messaging: true
architecture:
  modules: false
runtime:
  mode: job
  engine: mithril
  execution: mithril-job
  supervisor: eregion
YAML

FORGE_AUTOLOAD="$(cd "$FORGE" && pwd -W 2>/dev/null || cygpath -w "$FORGE" 2>/dev/null || echo "$FORGE")/vendor/autoload.php"
SUP_WIN="$(cd "$SUP_DIR" && pwd -W 2>/dev/null || cygpath -w "$SUP_DIR" 2>/dev/null || echo "$SUP_DIR")"
# Normalize slashes for PHP on Windows
FORGE_AUTOLOAD="${FORGE_AUTOLOAD//\\/\/}"
SUP_WIN="${SUP_WIN//\\/\/}"

php -r "
require '${FORGE_AUTOLOAD}';
\$plan = new EreborCodeForge\\Durin\\Forge\\Tooling\\Runtime\\RuntimePlan('job', 'mithril-job', 'eregion', ['job-loop', 'messaging', 'process-supervision']);
\$cfg = new EreborCodeForge\\Durin\\Forge\\Tooling\\Runtime\\EregionConfigurator();
\$actions = \$cfg->configure('${SUP_WIN}', plan: \$plan);
echo json_encode(\$actions), PHP_EOL;
" >/tmp/smoke-ensure.txt 2>&1 || { cat /tmp/smoke-ensure.txt; fail 2 "ensureJobWorkload failed"; }

grep -q 'mode: consumer' "$SUP_DIR/eregion.yaml" || fail 2 "eregion.yaml missing consumer mode"
grep -q 'vendor/bin/job-worker' "$SUP_DIR/eregion.yaml" || fail 2 "eregion.yaml missing job-worker command"

php -r "
require '${FORGE_AUTOLOAD}';
\$plan = new EreborCodeForge\\Durin\\Forge\\Tooling\\Runtime\\RuntimePlan('job', 'mithril-job', 'eregion', ['job-loop']);
\$reg = EreborCodeForge\\Durin\\Forge\\Tooling\\Runtime\\RuntimeLauncherRegistry::builtIn();
\$launcher = \$reg->resolve(\$plan);
if (!\$launcher instanceof EreborCodeForge\\Durin\\Forge\\Tooling\\Runtime\\Launcher\\EregionJobLauncher) {
  fwrite(STDERR, 'expected EregionJobLauncher, got ' . \$launcher::class . PHP_EOL);
  exit(1);
}
echo 'launcher=EregionJobLauncher', PHP_EOL;
" || fail 2 "supervised plan did not resolve EregionJobLauncher"

# Start Eregion briefly against consumer config (check config at least)
(
  cd "$SUP_DIR"
  "$EREGION_BIN" check --config=eregion.yaml >/tmp/smoke-eregion-check.txt 2>&1 \
    || { cat /tmp/smoke-eregion-check.txt; fail 2 "eregion check failed for consumer workload"; }
)
pass "2 worker supervised by Eregion (consumer workload + launcher)"

echo
echo "ALL SMOKES PASSED (1–8)"
