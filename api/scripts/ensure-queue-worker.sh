#!/bin/sh
# Garante que o worker da fila AppChama está rodando (chamado pelo cron a cada minuto).
APP_DIR=/srv/sites/clients/appchama-agilizeerp
PIDFILE=/tmp/appchama-queue-worker.pid
LOGFILE=/tmp/appchama-queue.log
WORKER_SCRIPT="$APP_DIR/scripts/queue-worker.sh"

if [ -f "$PIDFILE" ]; then
  PID=$(cat "$PIDFILE" 2>/dev/null)
  if [ -n "$PID" ] && kill -0 "$PID" 2>/dev/null; then
    exit 0
  fi
fi

if pgrep -f "appchama-agilizeerp/scripts/queue-worker.sh" >/dev/null 2>&1; then
  exit 0
fi

if pgrep -f "appchama-agilizeerp.*queue:work redis --queue=appchama" >/dev/null 2>&1; then
  exit 0
fi

nohup sh "$WORKER_SCRIPT" >> "$LOGFILE" 2>&1 &
echo "$(date -Iseconds 2>/dev/null || date) watchdog started worker (nohup pid=$!)" >> "$LOGFILE"
