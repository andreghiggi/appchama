"""Empacota api/ e faz deploy na VPS App Chama."""
import os
import subprocess
import sys

sys.path.insert(0, os.path.dirname(__file__))
from vps_ssh import connect

sys.stdout.reconfigure(encoding='utf-8', errors='replace')

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
API_DIR = os.path.join(ROOT, 'api')
local = os.path.join(os.environ['TEMP'], 'appchama-api.tgz')

if not os.path.isdir(API_DIR):
    raise SystemExit(f'Missing API directory: {API_DIR}')

print(f'=== PACK {API_DIR} -> {local} ===')
subprocess.run(
    [
        'tar',
        '-czf',
        local,
        '--exclude=vendor',
        '--exclude=.env',
        '--exclude=.git',
        '.',
    ],
    cwd=API_DIR,
    check=True,
)
print(f'Tarball: {os.path.getsize(local)} bytes')

c = connect()

sftp = c.open_sftp()
sftp.put(local, '/tmp/appchama-api.tgz')
sftp.close()

deploy_cmd = (
    'tar -xzf /tmp/appchama-api.tgz -C /opt/projeto-web/sites/clients/appchama-agilizeerp && '
    'docker compose -f /opt/projeto-web/docker-compose.vps.yml exec -T php sh -c '
    '"cd /srv/sites/clients/appchama-agilizeerp && '
    'php composer.phar install --no-dev --optimize-autoloader --ignore-platform-req=ext-pcntl && '
    'php artisan migrate --force && '
    'php artisan config:cache && '
    'php artisan route:cache && '
    'php artisan view:cache && '
    'php artisan withdrawals:reconcile && '
    'php artisan payments:reconcile-pending && '
    'chown -R 82:82 /srv/sites/clients/appchama-agilizeerp" && '
    'bash /opt/projeto-web/sites/clients/appchama-agilizeerp/scripts/install-queue-watchdog.sh'
)

print('=== DEPLOY ===')
_, stdout, stderr = c.exec_command(deploy_cmd, timeout=300)
print(stdout.read().decode('utf-8', 'replace'))
err = stderr.read().decode('utf-8', 'replace')
if err.strip():
    print('ERR:', err[:3000])

print('=== CITIES API ===')
_, stdout, _ = c.exec_command(
    'curl -s https://apichama.agilizeerp.com.br/api/v1/cities -H X-Tenant-Slug:chama-demo',
    timeout=30,
)
print(stdout.read().decode('utf-8', 'replace'))

c.close()
