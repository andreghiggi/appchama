# Colocar AppChama online — só conexões

## O que vai onde

| O quê | Onde | URL final |
|-------|------|-----------|
| **API + Admin Filament** | Railway | `https://xxx.up.railway.app` |
| **Mockup visual** | GitHub Pages | `https://andreghiggi.github.io/appchama/` |
| **Apps mobile (teste)** | Expo Go no celular | apontam para a URL da Railway |

**Vercel** → não serve Laravel/PHP.  
**GitHub Pages** → só HTML estático (mockup). A API **não** roda lá.

---

## Parte 1 — GitHub Pages (mockup) ~2 min

1. Abra: https://github.com/andreghiggi/appchama/settings/pages  
2. Em **Build and deployment** → Source: **GitHub Actions**  
3. Faça push na `main` (ou rode o workflow manualmente em **Actions**)  
4. Em ~1 min: **https://andreghiggi.github.io/appchama/**

---

## Parte 2 — Railway (API + Admin) ~10 min

### 2.1 Criar conta e projeto

1. Acesse https://railway.app e entre com **GitHub**  
2. **New Project** → **Deploy from GitHub repo** → escolha `andreghiggi/appchama`  
3. Clique no serviço → **Settings** → **Root Directory** → digite `api`  
4. Railway usa o `Dockerfile` dentro de `api/`

### 2.2 MySQL

1. No projeto Railway: **+ New** → **Database** → **MySQL**  
2. Clique no MySQL → aba **Variables** → copie ou use **Reference** nas variáveis do serviço web

No serviço **appchama-api**, adicione (ou referencie do MySQL):

```
DB_CONNECTION=mysql
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
```

### 2.3 Redis

1. **+ New** → **Database** → **Redis**  
2. No serviço web, adicione:

```
REDIS_CLIENT=predis
REDIS_URL=${{Redis.REDIS_URL}}
QUEUE_CONNECTION=redis
CACHE_STORE=redis
```

> Sem Redis, matching de motoristas não funciona. Redis no Railway é obrigatório para Fase 1.

### 2.4 Variáveis do Laravel

No serviço web, **Variables**:

```
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:COLE_AQUI_32_BYTES
APP_URL=https://SUA-URL.up.railway.app
SESSION_DRIVER=database
BROADCAST_CONNECTION=log
SMS_PROVIDER=log
DEFAULT_TENANT_SLUG=chama-demo
LOG_CHANNEL=stderr
```

Gerar `APP_KEY` no PC:

```powershell
cd C:\xampp\htdocs\appchama\api
php artisan key:generate --show
```

Cole o valor em `APP_KEY` no Railway.

### 2.5 Domínio público

1. Serviço web → **Settings** → **Networking** → **Generate Domain**  
2. Copie a URL (ex: `appchama-production.up.railway.app`)  
3. Atualize `APP_URL` com essa URL exata  
4. Aguarde o deploy (Build ~3–5 min)

### 2.7 Primeiro deploy — popular banco

No serviço web → **Variables**, adicione temporariamente:

```
RUN_SEED=true
```

Faça **Redeploy**. Depois que subir, **remova** `RUN_SEED` (senão recria dados a cada restart).

### 2.8 Testar

| Teste | URL |
|-------|-----|
| Health | `https://SUA-URL.up.railway.app/up` |
| Admin | `https://SUA-URL.up.railway.app/admin` |
| API | `https://SUA-URL.up.railway.app/api/v1/cities` |

Login admin: `admin@chama.app` / `password`

---

## Parte 3 — Apps mobile (Expo Go)

No celular, edite antes de abrir o Expo (ou crie `.env` local):

**`mobile/passenger/.env`** e **`mobile/driver/.env`:**

```
EXPO_PUBLIC_API_URL=https://SUA-URL.up.railway.app/api/v1
EXPO_PUBLIC_TENANT_SLUG=chama-demo
```

Depois:

```powershell
cd mobile/passenger
npx expo start
```

Escaneie o QR no **Expo Go**. OTP continua em log (`SMS_PROVIDER=log`) — veja logs no Railway: serviço → **Deployments** → **View logs**.

---

## Parte 4 — Fluxo de teste online

1. Admin Railway: aprovar motorista se necessário  
2. App motorista: login `5511999990003` → online  
3. App passageiro: login `5511999990002` → pedir corrida  
4. Motorista aceita → completa corrida  

---

## Custos

- **GitHub Pages:** grátis  
- **Railway:** ~US$ 5/mês de crédito grátis (suficiente para testes leves)  
- **Expo Go:** grátis  

---

## Problemas comuns

| Sintoma | Solução |
|---------|---------|
| Admin sem CSS | Redeploy; `filament:assets` roda no Docker build |
| 500 no login | Verificar `APP_KEY` e variáveis MySQL |
| Corrida não acha motorista | Redis conectado? Motorista online? |
| Mobile não conecta | `EXPO_PUBLIC_API_URL` com `https://` e URL correta |

---

## Alternativa: seu servidor XAMPP

Se preferir não usar Railway, aponte um domínio para seu servidor, use `infra/docker-compose.yml` e Nginx + Certbot. O README principal tem mais detalhes.
