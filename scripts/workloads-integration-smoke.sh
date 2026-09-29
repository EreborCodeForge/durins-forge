#!/usr/bin/env bash
# Workloads integration smoke — portable Phase 5 acceptance checks.
# Roots come from env or sibling checkouts; no machine-local hardcoded paths.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
FORGE="${FORGE_ROOT:-$ROOT}"
MITHRIL="${MITHRIL_ROOT:-}"
EREGION="${EREGION_ROOT:-}"
APP="${DURIN_APP_ROOT:-$ROOT/durin-app}"
JOBS="${DURIN_JOBS_ROOT:-$ROOT/jobs}"
FIXTURE_WORKER="$FORGE/tests/Fixtures/generated-worker"

php_path() {
  local p
  p="$(cd "$1" && pwd)"
  if command -v cygpath >/dev/null 2>&1; then
    cygpath -m "$p"
  else
    echo "$p"
  fi
}

resolve_sibling() {
  local name="$1"
  local base
  base="$(cd "$FORGE/.." && pwd)"
  if [[ -d "$base/$name" ]]; then
    echo "$base/$name"
    return 0
  fi
  return 1
}

if [[ -z "$MITHRIL" ]]; then
  MITHRIL="$(resolve_sibling mithrilphp || true)"
fi
if [[ -z "$EREGION" ]]; then
  EREGION="$(resolve_sibling eregion || true)"
fi

EREGION_BIN="${EREGION_BIN:-}"
if [[ -z "$EREGION_BIN" && -n "${EREGION:-}" ]]; then
  if [[ -x "$EREGION/bin/eregion" ]]; then
    EREGION_BIN="$EREGION/bin/eregion"
  elif [[ -x "$EREGION/bin/eregion.exe" ]]; then
    EREGION_BIN="$EREGION/bin/eregion.exe"
  fi
  if [[ -x "$EREGION/bin/eregion" ]]; then
    ver="$("$EREGION/bin/eregion" version 2>/dev/null | head -1 || true)"
    if [[ "$ver" == *"0.4"* || "$ver" == *"0.5"* || "$ver" == *"0.6"* ]]; then
      EREGION_BIN="$EREGION/bin/eregion"
    fi
  fi
fi

JOB_WORKER_BIN="${JOB_WORKER_BIN:-$FORGE/vendor/ereborcodeforge/mithrilphp/bin/job-worker}"

pass() { echo "PASS  $1"; }
fail() { echo "FAIL  $1 — $2" >&2; exit 1; }
skip() { echo "SKIP  $1 — $2"; }

require_bin() {
  local label="$1" path="$2"
  [[ -n "$path" && -x "$path" ]] || fail "$label" "missing executable: ${path:-<empty>} (set EREGION_BIN / EREGION_ROOT)"
}

write_job_autoload() {
  local app_root="$1"
  local forge_autoload
  forge_autoload="$(php_path "$FORGE")/vendor/autoload.php"
  mkdir -p "$app_root/vendor"
  php -r '
$forge = $argv[1];
$target = $argv[2];
$code = "<?php\n"
    . "require " . var_export($forge, true) . ";\n"
    . "spl_autoload_register(static function (string \$class): void {\n"
    . "    \$prefix = \"App\\\\\";\n"
    . "    if (!str_starts_with(\$class, \$prefix)) {\n"
    . "        return;\n"
    . "    }\n"
    . "    \$rel = str_replace(\"\\\\\", \"/\", substr(\$class, strlen(\$prefix))) . \".php\";\n"
    . "    \$file = dirname(__DIR__) . \"/src/\" . \$rel;\n"
    . "    if (is_file(\$file)) {\n"
    . "        require \$file;\n"
    . "    }\n"
    . "});\n";
if (file_put_contents($target, $code) === false) {
    fwrite(STDERR, "unable to write {$target}\n");
    exit(1);
}
' "$forge_autoload" "$app_root/vendor/autoload.php"
}

write_job_worker_bin() {
  local app_root="$1"
  mkdir -p "$app_root/vendor/bin"
  cat > "$app_root/vendor/bin/job-worker" <<'PHP'
#!/usr/bin/env php
<?php
declare(strict_types=1);

use Erebor\Mithril\Runtime\JobWorkerLauncher;
use Erebor\Mithril\Runtime\WorkerExitCode;

require dirname(__DIR__) . '/autoload.php';

$result = (new JobWorkerLauncher())->runFromArgv($argv);
exit($result->exitCode()->value);
PHP
  chmod +x "$app_root/vendor/bin/job-worker" 2>/dev/null || true
}

prepare_job_app() {
  local dest="$1"
  mkdir -p "$dest/var/runtime"
  write_job_autoload "$dest"
  write_job_worker_bin "$dest"
}

TMP_BASE="${TMPDIR:-/tmp}"
SMOKE_LOG_DIR="$(mktemp -d "$TMP_BASE/durin-smoke-logs.XXXXXX")"
SMOKE_DIR=""
SUP_DIR=""
cleanup() {
  if [[ -n "${EREGION_PID:-}" ]] && kill -0 "$EREGION_PID" 2>/dev/null; then
    kill "$EREGION_PID" 2>/dev/null || true
    wait "$EREGION_PID" 2>/dev/null || true
  fi
  if [[ -n "${WPID:-}" ]] && kill -0 "$WPID" 2>/dev/null; then
    kill "$WPID" 2>/dev/null || true
    wait "$WPID" 2>/dev/null || true
  fi
  rm -rf "$SMOKE_DIR" "$SUP_DIR" "$SMOKE_LOG_DIR" 2>/dev/null || true
}
trap cleanup EXIT

echo "== Workloads integration smoke =="
echo "forge=$FORGE"
echo "mithril=${MITHRIL:-<unset>}"
echo "eregion_bin=${EREGION_BIN:-<unset>}"
echo "app=$APP"
echo "jobs=$JOBS"
echo "job_worker=$JOB_WORKER_BIN"

[[ -d "$FORGE" ]] || fail forge "missing directory: $FORGE"
[[ -f "$JOB_WORKER_BIN" ]] || fail 1 "job-worker missing at $JOB_WORKER_BIN (composer install)"

# --- 5. durin run launcher matrix (Forge unit) ---
(
  cd "$FORGE"
  vendor/bin/phpunit --filter RuntimeLauncherRegistryTest --colors=never \
    >"$SMOKE_LOG_DIR/launcher.txt" 2>&1 \
    || { cat "$SMOKE_LOG_DIR/launcher.txt"; fail 5 "RuntimeLauncherRegistryTest failed"; }
)
pass "5 durin run launcher matrix (http/job/supervised)"

# --- 8. composer/runtime residual cleanup (Forge unit) ---
(
  cd "$FORGE"
  vendor/bin/phpunit --filter 'ResidualRuntimeCleanerTest|ComposerManifestMergerTest|EregionConfiguratorTest|RuntimeResolverTest' --colors=never \
    >"$SMOKE_LOG_DIR/residual.txt" 2>&1 \
    || { cat "$SMOKE_LOG_DIR/residual.txt"; fail 8 "runtime residual/resolution tests failed"; }
)
pass "8 composer/runtime residual cleanup + resolution"

# --- optional Mithril graceful drain / idle ---
if [[ -n "${MITHRIL:-}" && -d "$MITHRIL" && -x "$MITHRIL/vendor/bin/phpunit" ]]; then
  (
    cd "$MITHRIL"
    vendor/bin/phpunit --filter 'testIdleDoesNotExitUntilStop|testDrainViaPublicSignalPath|testDrainFinishesCurrentJob|testMaxJobsRecycle|testDispatcherPublishesConsumableJob' --colors=never \
      >"$SMOKE_LOG_DIR/mithril-job.txt" 2>&1 \
      || { cat "$SMOKE_LOG_DIR/mithril-job.txt"; fail 6 "Mithril JobWorker Phase 2 tests failed"; }
  )
  pass "6 graceful drain / idle!=stop (Mithril JobWorker)"
else
  skip 6 "MITHRIL_ROOT unset or phpunit missing"
fi

# --- optional Eregion crash/restart + scale ---
if [[ -n "${EREGION:-}" && -d "$EREGION" ]] && command -v go >/dev/null 2>&1; then
  (
    cd "$EREGION"
    go test ./internal/worker/ -run TestConsumerScaleUpDown -count=1 \
      >"$SMOKE_LOG_DIR/scale.txt" 2>&1 \
      || { cat "$SMOKE_LOG_DIR/scale.txt"; fail 7a "TestConsumerScaleUpDown failed"; }
  )
  pass "7a scale min → max → min (Eregion)"

  (
    cd "$EREGION"
    go test ./tests/integration/ -run Crash -count=1 \
      >"$SMOKE_LOG_DIR/crash.txt" 2>&1 \
      || go test ./internal/worker/ -run 'Restart|Crash|Backoff' -count=1 \
      >"$SMOKE_LOG_DIR/crash.txt" 2>&1 \
      || { cat "$SMOKE_LOG_DIR/crash.txt"; fail 7b "crash/restart coverage failed"; }
  )
  pass "7b worker crash → restart/backoff"
else
  skip 7 "EREGION_ROOT unset or go missing"
fi

# --- 3+4. HTTP minimal/service ---
if [[ -d "$APP" && -x "$APP/vendor/bin/phpunit" ]]; then
  (
    cd "$APP"
    vendor/bin/phpunit --filter InitPresetSmokeTest --colors=never \
      >"$SMOKE_LOG_DIR/http.txt" 2>&1 \
      || { cat "$SMOKE_LOG_DIR/http.txt"; fail 3 "InitPresetSmokeTest failed"; }
  )
  pass "3+4 HTTP minimal/service init + mithril-http + eregion"
else
  (
    cd "$FORGE"
    vendor/bin/phpunit --filter 'test_minimal_resolves_mithril_http_with_eregion|test_service_resolves_mithril_http_with_eregion|NeutralRootInitTest' --colors=never \
      >"$SMOKE_LOG_DIR/http-forge.txt" 2>&1 \
      || { cat "$SMOKE_LOG_DIR/http-forge.txt"; fail 3 "HTTP resolution/init tests failed"; }
  )
  pass "3+4 HTTP minimal/service (Forge resolution + neutral init)"
fi

# --- 1. worker standalone without Eregion ---
if [[ ! -d "$JOBS" ]]; then
  [[ -d "$FIXTURE_WORKER" ]] || fail 1 "missing jobs app and fixture at $FIXTURE_WORKER"
  JOBS="$FIXTURE_WORKER"
  echo "jobs-fixture=$JOBS (Forge generated-worker)"
fi

SMOKE_DIR="$(mktemp -d "$TMP_BASE/durin-smoke-standalone.XXXXXX")"
cp -a "$JOBS/." "$SMOKE_DIR/"
prepare_job_app "$SMOKE_DIR"
cat > "$SMOKE_DIR/durin.yaml" <<'YAML'
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
YAML

IDLE_LOG="$SMOKE_DIR/idle.log"
(
  cd "$SMOKE_DIR"
  php vendor/bin/job-worker >"$IDLE_LOG" 2>&1 &
  WPID=$!
  sleep 2
  if ! kill -0 "$WPID" 2>/dev/null; then
    cat "$IDLE_LOG" || true
    fail 1 "job-worker exited during idle (standalone)"
  fi
  kill "$WPID" 2>/dev/null || true
  wait "$WPID" 2>/dev/null || true
  WPID=""
)
grep -q 'execution: mithril-job' "$SMOKE_DIR/durin.yaml" || fail 1 "missing mithril-job execution"
! grep -q 'supervisor:' "$SMOKE_DIR/durin.yaml" || fail 1 "standalone should not declare supervisor"
pass "1 worker standalone without Eregion (idle keeps process)"

# --- 2. worker supervised by Eregion (real process) ---
require_bin eregion "$EREGION_BIN"
SUP_DIR="$(mktemp -d "$TMP_BASE/durin-smoke-supervised.XXXXXX")"
cp -a "$JOBS/." "$SUP_DIR/"
prepare_job_app "$SUP_DIR"
mkdir -p "$SUP_DIR/.mithril/bin" "$SUP_DIR/var/runtime"
cp "$EREGION_BIN" "$SUP_DIR/.mithril/bin/eregion" 2>/dev/null \
  || cp "$EREGION_BIN" "$SUP_DIR/.mithril/bin/eregion.exe"

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

FORGE_AUTOLOAD="$(php_path "$FORGE")/vendor/autoload.php"
SUP_PHP="$(php_path "$SUP_DIR")"

php -r "
require '${FORGE_AUTOLOAD}';
\$plan = new EreborCodeForge\\Durin\\Forge\\Tooling\\Runtime\\RuntimePlan('job', 'mithril-job', 'eregion', ['job-loop', 'messaging', 'process-supervision']);
\$cfg = new EreborCodeForge\\Durin\\Forge\\Tooling\\Runtime\\EregionConfigurator();
\$actions = \$cfg->configure('${SUP_PHP}', plan: \$plan);
echo json_encode(\$actions), PHP_EOL;
" >"$SMOKE_LOG_DIR/ensure.txt" 2>&1 || { cat "$SMOKE_LOG_DIR/ensure.txt"; fail 2 "ensureJobWorkload failed"; }

grep -q 'mode: consumer' "$SUP_DIR/eregion.yaml" || fail 2 "eregion.yaml missing consumer mode"
grep -q 'vendor/bin/job-worker' "$SUP_DIR/eregion.yaml" || fail 2 "eregion.yaml missing job-worker command"
grep -q 'max: 1' "$SUP_DIR/eregion.yaml" || fail 2 "consumer default must be max: 1"

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

(
  cd "$SUP_DIR"
  "$EREGION_BIN" check --config=eregion.yaml >"$SMOKE_LOG_DIR/eregion-check.txt" 2>&1 \
    || { cat "$SMOKE_LOG_DIR/eregion-check.txt"; fail 2 "eregion check failed for consumer workload"; }
)

EREGION_LOG="$SUP_DIR/eregion-run.log"
(
  cd "$SUP_DIR"
  "$EREGION_BIN" serve --config=eregion.yaml >"$EREGION_LOG" 2>&1 &
  EREGION_PID=$!
  sleep 3
  if ! kill -0 "$EREGION_PID" 2>/dev/null; then
    cat "$EREGION_LOG" || true
    fail 2 "eregion exited while supervising consumer"
  fi
  kill "$EREGION_PID" 2>/dev/null || true
  wait "$EREGION_PID" 2>/dev/null || true
  EREGION_PID=""
)
pass "2 worker supervised by Eregion (live process + consumer workload + launcher)"

echo
echo "ALL REQUIRED SMOKES PASSED"
