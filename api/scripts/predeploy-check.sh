#!/bin/sh
# Recusa cachear config se o .env tiver valores de dev local (XAMPP) vazados.
# Motivo: em 2026-08/09 um .env com DB_HOST=127.0.0.1 / DB_PORT=3307 (XAMPP local)
# foi cacheado em produção, escondendo erros reais em diretorios com nome de path do Windows.
cd "$(dirname "$0")/.." || exit 1

if grep -qE '^DB_HOST=127\.0\.0\.1$|^DB_PORT=3307$|xampp' .env; then
  echo "REFUSING config:cache: .env contém valores de ambiente local/XAMPP." >&2
  exit 1
fi

php artisan config:cache
php artisan route:cache
