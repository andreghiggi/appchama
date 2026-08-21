# AppChama — Mobilidade Urbana (White-label)

Plataforma de despacho local estilo Rota77: passageiro, motorista parceiro e painel admin.

## Estrutura

```
appchama/
├── api/                 # Laravel 12 + Filament + Sanctum + Reverb + Horizon
├── mobile/
│   ├── passenger/       # Expo — app passageiro
│   ├── driver/          # Expo — app motorista
│   └── shared/          # API client, theme, types
├── infra/               # Docker Compose (MySQL, Redis, Nginx, Reverb, Horizon)
└── docs/mockup/         # Mockup visual de referência
```

## Pré-requisitos

- PHP 8.2+, Composer
- Node.js 20+ (apps mobile)
- Docker Desktop (produção / Redis + MySQL)
- Extensões PHP recomendadas: `intl`, `redis` ou Predis (já incluído)

## Backend (API + Admin)

### Desenvolvimento local (SQLite)

```powershell
cd api
copy .env.example .env
php artisan key:generate
New-Item -ItemType File database\database.sqlite -Force
php artisan migrate:fresh --seed
php artisan filament:assets
php artisan serve --port=8088
```

- **Admin Filament:** http://localhost:8088/admin  
  - Email: `admin@chama.app`  
  - Senha: `password`

- **API:** http://localhost:8088/api/v1

### Docker (MySQL + Redis + Nginx)

```powershell
cd infra
copy .env.example .env
docker compose up -d
```

No `api/.env` para Docker:

```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=appchama
DB_USERNAME=appchama
DB_PASSWORD=secret

REDIS_CLIENT=predis
REDIS_HOST=redis
QUEUE_CONNECTION=redis
CACHE_STORE=redis
BROADCAST_CONNECTION=reverb
```

Depois:

```powershell
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan horizon
```

A API fica em http://localhost:8088

### Filas e WebSocket

```powershell
# Worker de matching de corridas
php artisan queue:work

# Ou Horizon (Linux/Docker)
php artisan horizon

# WebSocket Reverb
php artisan reverb:start
```

### OTP em desenvolvimento

Com `SMS_PROVIDER=log`, o código OTP aparece em `api/storage/logs/laravel.log`.

### Usuários seed

| Papel      | Telefone        |
|-----------|-----------------|
| Passageiro | 5511999990002  |
| Motorista  | 5511999990003  |
| Admin web  | admin@chama.app |

Tenant slug: `chama-demo`

## Apps Mobile (Expo)

```powershell
cd mobile/passenger
copy .env.example .env
npm install
npx expo start
```

```powershell
cd mobile/driver
copy .env.example .env
npm install
npx expo start
```

Ajuste `EXPO_PUBLIC_API_URL` para o IP da máquina (ex.: `http://192.168.0.10:8088/api/v1`) ao testar no celular físico.

## Deploy em servidor (Nginx + SSL)

1. Suba `infra/docker-compose.yml` no servidor.
2. Configure domínio apontando para o servidor.
3. Nginx host (exemplo):

```nginx
server {
    listen 443 ssl http2;
    server_name api.seudominio.com.br;

    ssl_certificate /etc/letsencrypt/live/api.seudominio.com.br/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.seudominio.com.br/privkey.pem;

    location / {
        proxy_pass http://127.0.0.1:8088;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

4. Certbot: `certbot --nginx -d api.seudominio.com.br`
5. Processos persistentes: Horizon, Reverb e queue worker via Supervisor ou containers Docker.

## URLs públicas (VPS hospedagem i9)

O domínio `agilizeerp.com.br` é gerido no **projeto web** (BIND + Caddy na VPS `69.169.97.213`).
O AppChama roda lá (não neste PC Windows — a porta 80 do XAMPP não é alcançável da internet).

| Serviço | URL |
|---------|-----|
| Admin Filament | https://appchama.agilizeerp.com.br/admin |
| API REST | https://apichama.agilizeerp.com.br/api/v1 |
| Health | https://apichama.agilizeerp.com.br/up |

**Login admin:** `admin@chama.app` / `password`

Arquivos na VPS:

- Site: `/opt/projeto-web/sites/clients/appchama-agilizeerp`
- Caddy: `/opt/projeto-web/clients/appchama-agilizeerp.vps.caddy`
- DNS: `/etc/bind/zones/db.agilizeerp.com.br` (`appchama` / `apichama` → A)

### XAMPP Windows (sem Docker)

Deploy nativo com Apache + MariaDB 3307 + Redis compartilhado.  
Guia completo: [docs/DEPLOY-XAMPP.md](docs/DEPLOY-XAMPP.md)

Útil para desenvolvimento local. Produção pública usa a VPS acima.

## Funcionalidades Fase 1

- Auth telefone + OTP (Sanctum)
- Solicitação, matching, tracking e conclusão de corrida
- Tarifa estimada e final (cobrança a partir do embarque)
- Localização motorista via Redis GEO
- Painel admin: motoristas, cidades, corridas, mapa ao vivo, aprovação
- Apps Expo passageiro e motorista

## Fora de escopo (Fase 2+)

- Gateway PIX/cartão para mensalidade
- Multi-tenant UI / super admin
- Chat in-app, FCM push completo

## Referência visual

Mockup original em [docs/mockup/index.html](docs/mockup/index.html).
