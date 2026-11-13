#!/bin/bash
# Instala cron no host VPS para manter o worker AppChama sempre ativo.
set -euo pipefail

CONTAINER="${APPCHAMA_PHP_CONTAINER:-projeto-web-php-1}"
MARKER_QUEUE="# appchama-queue-watchdog"
MARKER_SCHEDULE="# appchama-schedule"
CRON_ENSURE="* * * * * docker exec $CONTAINER sh /srv/sites/clients/appchama-agilizeerp/scripts/ensure-queue-worker.sh >> /var/log/appchama-queue-watchdog.log 2>&1 $MARKER_QUEUE"
CRON_BOOT="@reboot sleep 45 && docker exec -d $CONTAINER sh /srv/sites/clients/appchama-agilizeerp/scripts/ensure-queue-worker.sh $MARKER_QUEUE"
CRON_SCHEDULE="* * * * * docker exec $CONTAINER sh -c 'cd /srv/sites/clients/appchama-agilizeerp && php artisan schedule:run >> /var/log/appchama-schedule.log 2>&1' $MARKER_SCHEDULE"

TMP=$(mktemp)
crontab -l 2>/dev/null | grep -v "$MARKER_QUEUE" | grep -v "$MARKER_SCHEDULE" > "$TMP" || true
echo "$CRON_ENSURE" >> "$TMP"
echo "$CRON_BOOT" >> "$TMP"
echo "$CRON_SCHEDULE" >> "$TMP"
crontab "$TMP"
rm -f "$TMP"

chmod +x /opt/projeto-web/sites/clients/appchama-agilizeerp/scripts/*.sh 2>/dev/null || true
docker exec "$CONTAINER" sh /srv/sites/clients/appchama-agilizeerp/scripts/ensure-queue-worker.sh

echo "Watchdog + schedule instalados. Cron:"
crontab -l | grep -E "appchama-queue-watchdog|appchama-schedule"
