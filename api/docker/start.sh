#!/usr/bin/env bash
set -e

echo "==> AppChama starting..."

# Railway / Render injetam PORT
PORT="${PORT:-8080}"

# Aguarda MySQL ficar pronto (Railway)
if [ -n "${MYSQLHOST:-}" ]; then
  echo "==> Waiting for MySQL..."
  for i in $(seq 1 30); do
    if php artisan db:show 2>/dev/null; then
      break
    fi
    sleep 2
  done
fi

php artisan migrate --force --no-interaction

if [ "${RUN_SEED:-false}" = "true" ]; then
  echo "==> Seeding database (RUN_SEED=true)..."
  php artisan db:seed --force --no-interaction
fi
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Worker de filas em background (matching de corridas)
php artisan queue:work --tries=3 --timeout=120 &

echo "==> Server on 0.0.0.0:${PORT}"
exec php artisan serve --host=0.0.0.0 --port="${PORT}"
