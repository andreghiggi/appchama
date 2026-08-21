# Deploy AppChama no XAMPP (sem Docker)

Deploy nativo no Windows + XAMPP, **sem conflitar** com serviços existentes.

## Domínios

| Papel | Subdomínio |
|-------|------------|
| **Admin + painel** | `appchama.agilizeerp.com.br` |
| **API REST** | `apichama.agilizeerp.com.br` |

## Isolamento (nada para)

| Recurso | AppChama usa | Outros projetos |
|---------|--------------|-----------------|
| MySQL | Schema **`appchama`** na porta **3307** | demais bancos no mesmo MariaDB |
| Redis | Prefixo **`appchama-database-`** | Compartilhado na 6379 |
| HTTP | Apache **:80** (dois vhosts) | `emitsmart.local`, `localhost` |
| WebSocket | **Desligado** | Evolution API mantém **8080** |
| Filas | `queue:work` (sem porta TCP) | — |

## URLs (após DNS/Caddy apontar para este servidor)

| Serviço | URL |
|---------|-----|
| Admin Filament | https://appchama.agilizeerp.com.br/admin |
| Health (API) | https://apichama.agilizeerp.com.br/up |
| API REST | https://apichama.agilizeerp.com.br/api/v1 |
| Mockup | https://andreghiggi.github.io/appchama/ |

**Login admin:** `admin@chama.app` / `password`  
**Tenant:** `chama-demo`

## Passo a passo

### 1. Banco MySQL (porta 3307)

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -P 3307 -e "CREATE DATABASE IF NOT EXISTS appchama CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS 'appchama'@'localhost' IDENTIFIED BY 'SUA_SENHA'; GRANT ALL ON appchama.* TO 'appchama'@'localhost'; FLUSH PRIVILEGES;"
```

### 2. `.env` da API

```powershell
cd C:\xampp\htdocs\appchama\api
copy .env.xampp.example .env
php artisan key:generate
```

Variáveis principais:

```env
APP_URL=https://appchama.agilizeerp.com.br
API_URL=https://apichama.agilizeerp.com.br
SANCTUM_STATEFUL_DOMAINS=appchama.agilizeerp.com.br,apichama.agilizeerp.com.br
```

### 3. Migrations + assets

```powershell
php artisan migrate --force --seed
php artisan filament:assets
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

### 4. Apache vhost

Copie `infra/xampp/httpd-vhosts-appchama.conf` para  
`C:\xampp\apache\conf\extra\httpd-vhosts.conf` e reinicie o Apache.

### 5. Caddy (HTTPS)

Veja `infra/xampp/Caddyfile.example` — dois blocos `reverse_proxy` para `:80`.

Registros DNS necessários (tipo A ou CNAME para este servidor):

- `appchama.agilizeerp.com.br`
- `apichama.agilizeerp.com.br`

### 6. Apps mobile

```env
EXPO_PUBLIC_API_URL=https://apichama.agilizeerp.com.br/api/v1
EXPO_PUBLIC_TENANT_SLUG=chama-demo
```

## Verificação local

```powershell
curl -H "Host: appchama.agilizeerp.com.br" http://127.0.0.1/admin/login
curl -H "Host: apichama.agilizeerp.com.br" http://127.0.0.1/up
curl -H "Host: apichama.agilizeerp.com.br" http://127.0.0.1/api/v1/cities
```

## Atualizar deploy

```powershell
cd C:\xampp\htdocs\appchama
git pull
cd api
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Reinicie o worker de filas após deploy.
