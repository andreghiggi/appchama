#!/bin/sh
# Worker persistente da fila AppChama (matching de motoristas).
APP_DIR=/srv/sites/clients/appchama-agilizeerp
PIDFILE=/tmp/appchama-queue-worker.pid
LOGFILE=/tmp/appchama-queue.log

cd "$APP_DIR" || exit 1

echo $$ > "$PIDFILE"
trap 'rm -f "$PIDFILE"' EXIT INT TERM

while true; do
  echo "$(date -Iseconds 2>/dev/null || date) worker starting" >> "$LOGFILE"
  php artisan queue:work redis --queue=appchama,default --sleep=3 --tries=3 --max-time=3600 >> "$LOGFILE" 2>&1
  EXIT=$?
  echo "$(date -Iseconds 2>/dev/null || date) worker exited code=$EXIT, restart in 2s" >> "$LOGFILE"
  sleep 2
done
