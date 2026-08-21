# Deploy AppChama no XAMPP (sem Docker)

Deploy nativo no Windows + XAMPP, **sem conflitar** com serviços existentes.

## Isolamento (nada para)

| Recurso | AppChama usa | Outros projetos |
|---------|--------------|-----------------|
| MySQL | Schema **`appchama`** na porta **3307** (mesmo MariaDB XAMPP) | `emissorfiscal_laravel`, `seucontrole`, etc. |
| Redis | Prefixo **`appchama-database-`** (DB 2/3) | Compartilhado na 6379 |
| HTTP | Apache **:80** vhost `agilizeerp.com.br` | `emitsmart.local`, `localhost` |
| WebSocket | **Desligado** (`BROADCAST_CONNECTION=log`) | Evolution API mantém **8080** |
| Filas | `queue:work` (sem porta TCP) | — |

## URLs (após DNS/Caddy apontar para este servidor)

| Serviço | URL |
|---------|-----|
| Health | https://agilizeerp.com.br/up |
| API REST | https://agilizeerp.com.br/api/v1 |
| Admin Filament | https://agilizeerp.com.br/admin |
| Mockup (GitHub Pages) | https://andreghiggi.github.io/appchama/ |

**Login admin:** `admin@chama.app` / `password`  
**Tenant:** `chama-demo`

## Passo a passo (já aplicado neste servidor)

### 1. Banco MySQL (porta 3307)

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -P 3307 -e "CREATE DATABASE IF NOT EXISTS appchama CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS 'appchama'@'localhost' IDENTIFIED BY 'SUA_SENHA'; GRANT ALL ON appchama.* TO 'appchama'@'localhost'; FLUSH PRIVILEGES;"
```

### 2. `.env` da API

```powershell
cd C:\xampp\htdocs\appchama\api
copy .env.xampp.example .env
php artisan key:generate
# Edite DB_PASSWORD e APP_URL
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

Copie o bloco de `infra/xampp/httpd-vhosts-appchama.conf` para  
`C:\xampp\apache\conf\extra\httpd-vhosts.conf` e reinicie o Apache.

Habilite `extension=intl` em `C:\xampp\php\php.ini`.

### 5. Worker de filas

```powershell
# Manual ou Agendador de Tarefas do Windows
C:\xampp\htdocs\appchama\infra\xampp\queue-worker.bat
```

### 6. Domínio público (Caddy → Apache)

O domínio `agilizeerp.com.br` hoje responde via **Caddy** (HTTPS).  
Para servir o AppChama, substitua o site estático por reverse proxy:

```caddyfile
agilizeerp.com.br, www.agilizeerp.com.br {
    reverse_proxy 127.0.0.1:80
}
```

Se Caddy estiver em outra máquina, aponte para o IP interno deste XAMPP na porta 80.

### 7. Apps mobile

`mobile/passenger/.env` e `mobile/driver/.env`:

```env
EXPO_PUBLIC_API_URL=https://agilizeerp.com.br/api/v1
EXPO_PUBLIC_TENANT_SLUG=chama-demo
```

## Verificação local

```powershell
curl -H "Host: agilizeerp.com.br" http://127.0.0.1/up
curl -H "Host: agilizeerp.com.br" http://127.0.0.1/api/v1/cities
```

## Atualizar deploy

```powershell
cd C:\xampp\htdocs\appchama
git pull
cd api
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan filament:assets
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Reinicie o worker de filas após deploy.
