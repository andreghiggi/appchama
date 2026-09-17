import os
import sys
import tarfile
import tempfile

sys.path.insert(0, os.path.dirname(__file__))
from vps_ssh import connect

sys.stdout.reconfigure(encoding='utf-8', errors='replace')

APPS = [
    (
        r'C:\xampp\htdocs\appchama\mobile\passenger\dist-build',
        '/tmp/appchama-passenger-web.tgz',
        '/opt/projeto-web/sites/clients/appchama-passageiro',
    ),
    (
        r'C:\xampp\htdocs\appchama\mobile\driver\dist-build',
        '/tmp/appchama-driver-web.tgz',
        '/opt/projeto-web/sites/clients/appchama-motorista',
    ),
]

def make_tgz(source_dir: str) -> str:
    fd, path = tempfile.mkstemp(suffix='.tgz')
    os.close(fd)
    with tarfile.open(path, 'w:gz') as tar:
        for root, _, files in os.walk(source_dir):
            for name in files:
                full = os.path.join(root, name)
                arc = os.path.relpath(full, source_dir)
                tar.add(full, arcname=arc)
    return path

c = connect()
sftp = c.open_sftp()

for local_dir, remote_tgz, remote_path in APPS:
    print(f'=== Deploy {os.path.basename(local_dir)} -> {remote_path} ===')
    local_tgz = make_tgz(local_dir)
    sftp.put(local_tgz, remote_tgz)
    os.remove(local_tgz)
    cmd = f'mkdir -p {remote_path} && rm -rf {remote_path}/* && tar -xzf {remote_tgz} -C {remote_path}'
    _, stdout, stderr = c.exec_command(cmd, timeout=120)
    print(stdout.read().decode('utf-8', 'replace'))
    err = stderr.read().decode('utf-8', 'replace')
    if err.strip():
        print('ERR:', err[:1000])

sftp.close()

print('=== Caddy cache headers ===')
caddy_passenger = """passa.agilizeerp.com.br, passageiro.agilizeerp.com.br {
    root * /srv/sites/clients/appchama-passageiro
    encode gzip
    @html path / /index.html
    header @html Cache-Control "no-cache, no-store, must-revalidate"
    @js path_regexp \\.js$
    header @js Cache-Control "public, max-age=31536000, immutable"
    try_files {path} /index.html
    file_server
}
"""
caddy_driver = """motor.agilizeerp.com.br, motorista.agilizeerp.com.br {
    root * /srv/sites/clients/appchama-motorista
    encode gzip
    @html path / /index.html
    header @html Cache-Control "no-cache, no-store, must-revalidate"
    @js path_regexp \\.js$
    header @js Cache-Control "public, max-age=31536000, immutable"
    try_files {path} /index.html
    file_server
}
"""
for path, content in [
    ('/opt/projeto-web/clients/appchama-passageiro.vps.caddy', caddy_passenger),
    ('/opt/projeto-web/clients/appchama-motorista.vps.caddy', caddy_driver),
]:
    sftp2 = c.open_sftp()
    with sftp2.open(path, 'w') as f:
        f.write(content)
    sftp2.close()
    print('updated', path)

_, stdout, stderr = c.exec_command('caddy reload --config /etc/caddy/Caddyfile 2>&1 || caddy reload 2>&1', timeout=60)
out = stdout.read().decode('utf-8', 'replace')
err = stderr.read().decode('utf-8', 'replace')
if out.strip():
    print(out[:500])
if err.strip():
    print('caddy:', err[:500])

print('=== HTTP checks ===')
for url in ['https://passa.agilizeerp.com.br/', 'https://motor.agilizeerp.com.br/']:
    _, stdout, _ = c.exec_command(f'curl -s -o /dev/null -w "%{{http_code}}" {url}', timeout=30)
    print(url, stdout.read().decode('utf-8', 'replace'))

c.close()
