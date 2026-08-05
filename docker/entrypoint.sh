#!/bin/sh
set -eu

cd /app

if [ ! -f .env ]; then
  cp .env.example .env
fi

sync_env() {
  key="$1"
  val="$2"
  if grep -q "^${key}=" .env 2>/dev/null; then
    sed -i "s|^${key}=.*|${key}=${val}|" .env
  else
    echo "${key}=${val}" >> .env
  fi
}

sync_env APP_ENV "${APP_ENV:-production}"
sync_env APP_DEBUG "${APP_DEBUG:-false}"
sync_env APP_URL "${APP_URL:-http://localhost}"
sync_env DB_CONNECTION "${DB_CONNECTION:-sqlite}"
sync_env DB_FILE "${DB_FILE:-database.sqlite}"

mkdir -p var/cache var/runtime storage/framework/cache storage/logs logs public/storage
touch "${DB_FILE:-database.sqlite}"

php bin/durin migrate >/dev/null 2>&1 || true
php bin/durin config:cache >/dev/null 2>&1 || true

MODE="${CONTAINER_MODE:-compiled}"

case "$MODE" in
  compiled|compile|optimize)
    echo "[entrypoint] CONTAINER_MODE=compiled → durin optimize"
    php bin/durin optimize
    ;;
  live|runtime|*)
    echo "[entrypoint] CONTAINER_MODE=live → container:clear"
    php bin/durin container:clear >/dev/null 2>&1 || true
    php bin/durin routes:clear >/dev/null 2>&1 || true
    php -r '
      require "vendor/autoload.php";
      \Erebor\Mithril\Environment::load(__DIR__."/.env");
      $n = count(\App\Core\DiscoveryServiceProvider::discover("provider"));
      echo "[entrypoint] discovery warm: {$n} providers\n";
    ' || true
    ;;
esac

cmd="${1:-serve}"
shift || true

case "$cmd" in
  serve)
    # Prefer Eregion; serve:php is incompatible with public/index.php (EregionBridge/UDS)
    if [ -x vendor/bin/forge ] && vendor/bin/forge server:check >/dev/null 2>&1; then
      echo "[entrypoint] vendor/bin/forge serve --host=0.0.0.0 --port=${PORT:-8080}"
      exec vendor/bin/forge serve --host=0.0.0.0 --port="${PORT:-8080}"
    fi
    echo "[entrypoint] Eregion check failed — cannot fall back to serve:php (index.php is EregionBridge)"
    exit 1
    ;;
  serve:php)
    echo "[entrypoint] serve:php unsupported: public/index.php uses EregionBridge (UDS)"
    exit 1
    ;;
  durin|cli)
    exec php bin/durin "$@"
    ;;
  *)
    exec "$cmd" "$@"
    ;;
esac
