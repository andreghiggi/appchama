# Deploy AppChama na VPS (projeto web / agilizeerp)

Produção pública. Stack: Caddy + PHP 8.3-FPM + MariaDB + Redis na VPS `web-i9tecinfo` (`69.169.97.213`).

## URLs

| Serviço | URL |
|---------|-----|
| Admin | https://appchama.agilizeerp.com.br/admin |
| API | https://apichama.agilizeerp.com.br/api/v1 |
| Health | https://apichama.agilizeerp.com.br/up |

Login: `admin@chama.app` / `password` · Tenant: `chama-demo`

## Onde ficam as configs

| Item | Caminho |
|------|---------|
| Código Laravel | `/opt/projeto-web/sites/clients/appchama-agilizeerp` |
| Caddy | `/opt/projeto-web/clients/appchama-agilizeerp.vps.caddy` |
| Manifest | `/opt/projeto-web/clients/appchama-agilizeerp.json` |
| Zona DNS (repo local) | `servidoresVPS/projeto web/dns-i9/zones/db.agilizeerp.com.br` |
| Zona DNS (VPS) | `/etc/bind/zones/db.agilizeerp.com.br` |

Registros DNS:

```
appchama IN A 69.169.97.213
apichama IN A 69.169.97.213
```

## Atualizar código

```powershell
# No PC, a partir de appchama/api (sem vendor):
tar -czf $env:TEMP\appchama-api.tgz --exclude=vendor --exclude=.env --exclude=.git .
scp $env:TEMP\appchama-api.tgz web-i9tecinfo:/tmp/
ssh web-i9tecinfo "tar -xzf /tmp/appchama-api.tgz -C /opt/projeto-web/sites/clients/appchama-agilizeerp && docker compose -f /opt/projeto-web/docker-compose.vps.yml exec -T php sh -c 'cd /srv/sites/clients/appchama-agilizeerp && php composer.phar install --no-dev --optimize-autoloader --ignore-platform-req=ext-pcntl && php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache && chown -R 82:82 /srv/sites/clients/appchama-agilizeerp'"
```

## Filas

Worker em loop no container `php` (já iniciado no deploy). Reiniciar se cair:

```bash
docker compose -f /opt/projeto-web/docker-compose.vps.yml exec -T -d php \
  sh -c 'cd /srv/sites/clients/appchama-agilizeerp && while true; do php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600; sleep 2; done'
```

## Mobile

```env
EXPO_PUBLIC_API_URL=https://apichama.agilizeerp.com.br/api/v1
EXPO_PUBLIC_TENANT_SLUG=chama-demo
```
