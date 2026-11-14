# Acessos, endereços e tudo para continuar de outro computador

Atualizado em 13/11/2026. Este documento **não contém senhas, códigos nem chaves** (o repositório pode ser público). Para cada segredo abaixo está dito **onde ele fica** e **qual comando o busca**. Quem for continuar o trabalho deve ler também `CLAUDE.md` e `docs/CONFIGURAR-COMPUTADOR.md`.

## 1. Endereços no ar

| O quê | Endereço | Observação |
|---|---|---|
| Painel do Vai de 55 | https://adm.vaide55.com.br/admin/login | cada marca só vê os próprios dados |
| API do Vai de 55 | https://api.vaide55.com.br/api/v1 | usada pelos apps; `/brand-config` entrega a identidade da marca |
| Site do Vai de 55 | https://vaide55.com.br | hoje é uma página provisória ("em preparação") |
| Painel da plataforma (AppChama) | https://appchama.agilizeerp.com.br/admin | vê todas as marcas; seletor de marca no topo |
| API do AppChama | https://apichama.agilizeerp.com.br/api/v1 | marca modelo (`chama-demo`) |
| Logo servida pelo backend | https://api.vaide55.com.br/brands/vaide55/logo.png | arquivo em `api/public/brands/vaide55/` |

DNS do `vaide55.com.br`: delegado no Registro.br para `ns1.i9tecinfo.com.br` e `ns2.i9tecinfo.com.br` (zona no servidor, `/etc/bind/zones/db.vaide55.com.br`). Certificados HTTPS: Let's Encrypt automático (Caddy).

## 2. Logins (quem entra onde)

| Painel | Login (e-mail) | Papel | Senha |
|---|---|---|---|
| Vai de 55 | `admin@vaide55.com.br` | administrador da marca | senha temporária entregue ao dono, trocada no 1º acesso. Esqueceu? Entre como plataforma → menu **Administradores** → redefinir senha (gera outra temporária). |
| Plataforma | `admin@chama.app` | administrador da plataforma | com o dono |

- O administrador da marca só cria usuários de **suporte** da própria marca. Novos administradores de marca são criados pela plataforma (menu **Administradores**) ou por `php artisan tenant:admin <marca> <email> "<nome>" --role=admin`.
- Esse e-mail serve só de login: a caixa de correio `admin@vaide55.com.br` não existe.

### Contas de teste dos apps (telefone + código)
- Passageiro de teste: telefone `51999990001`. Motorista de teste: telefone `51999990002`.
- Os **códigos fixos ficam só no `.env` do servidor** (variável `OTP_TEST_ACCOUNTS`). Para ler:
  `ssh root@69.169.97.213 "grep '^OTP_TEST_ACCOUNTS=' /opt/projeto-web/sites/clients/appchama-agilizeerp/.env"`
- Só esses telefones têm código fixo. Qualquer outro telefone depende do SMS real, que **ainda não existe** (tarefa B1).
- O telefone de motorista de teste ainda **não tem cadastro de motorista** no Vai de 55: para testar o lado do motorista, cadastre-se pelo app em Encantado ou Roca Sales e aprove no painel (**Motoristas**).

## 3. Apps Android (Vai de 55)

| App | Pacote | APK de teste |
|---|---|---|
| Vai de 55 (passageiro) | `com.vaide55.passageiro` | https://expo.dev/artifacts/eas/TLkWqUL5TFrIXuiyS2eRDkUc2fpYp18IYHxNSEj3NRY.apk (13/11, versão 2: mapa com carrinhos, push, aviso de nova busca) |
| Vai de 55 Motorista | `com.vaide55.motorista` | https://expo.dev/artifacts/eas/nvlXg5Qs1HhXW0VAknp_3SDT29q7XHt-bbZv3GMoMtc.apk (13/11, versão 2: carteira com recarga Pix, localização em segundo plano, push; os links antigos não usar) |

- Instalar: abrir o link no celular Android e permitir "instalar de fontes desconhecidas".
- **Os pacotes `com.vaide55.*` são permanentes depois de publicados nas lojas.**
- Assinatura (SHA-1, para restringir a chave do Google): passageiro `70:50:BB:9F:7D:5C:8E:AC:D2:FB:F7:3C:9C:BA:A7:AD:6D:39:3C:AC`; motorista `6B:50:1A:14:B4:95:E2:A0:05:54:19:29:4F:C1:D7:8B:E5:F1:A9:C1`. Na Play Store o Google assina com chave própria: adicionar também o SHA-1 que a Play Console mostrar.

### Expo / EAS (onde os apps são compilados)
- Conta dona: equipe `andrehiggis-team` (login do dono; a senha fica com ele). Projetos: `vaide55-passenger` e `vaide55-driver` (ids públicos em `marcas/vaide55/marca.json`).
- Painel: https://expo.dev/accounts/andrehiggis-team/projects
- Entrar no computador: `npx eas-cli login`; conferir: `npx eas-cli whoami`.
- **Chave do Google Maps**: guardada como variável secreta `GOOGLE_MAPS_API_KEY` nos dois projetos (ambientes `preview` e `production`). Para trocar: `npx eas-cli env:create --name GOOGLE_MAPS_API_KEY --value ... --environment production --visibility secret --force`. Não existe no Git. O console do Google fica no projeto "Vai de 55" (https://console.cloud.google.com/apis/credentials).
- Gerar APK de teste: `cd mobile/passenger` (ou `driver`) e `MARCA=vaide55 npx eas-cli build --profile vaide55-teste --platform android`. AAB para a loja: `--profile vaide55`.
- **Nunca rode `eas init` neste projeto**: ele reescreve o `app.json` com a configuração da marca. O id do projeto vem de `marca.json`.
- Detalhes completos: `docs/PUBLICAR-APPS-LOJAS.md`.

## 4. Código (GitHub) e como trabalhar
- Repositório: https://github.com/andreghiggi/appchama (fonte da verdade). Estrutura: `api/` (backend Laravel + Filament), `mobile/` (apps Expo), `marcas/<marca>/` (identidade e configuração de cada marca), `docs/`.
- Cada marca é **configuração**, nunca cópia de código: `marcas/<marca>/marca.json` + `assets/` + `IDENTIDADE.md`.
- Ciclo: `git fetch` e `git pull`; trabalhar em branch própria; commit e push; o dono faz o merge pelo GitHub. Nunca `push --force`.
- O servidor tem uma chave de deploy só para este repositório (`/root/.ssh/appchama_github_deploy`, alias `github-appchama`); é por ela que o Claude envia as branches a partir da VPS.

## 5. Servidor (VPS 69.169.97.213)
- Acesso: `ssh root@69.169.97.213` (sem senha, pela chave do computador). Skill `vps` lê o HANDOFF e as tarefas; `vps-site log` e `vps-site tarefa ...` registram o andamento. Reservar antes de mexer em recurso compartilhado: `vps-site claim caddy|dns|emit-deploy ...`.
- Backend em produção: `/opt/projeto-web/sites/clients/appchama-agilizeerp` (container PHP `projeto-web-php-1`, banco `appchama` no `projeto-web-db-1`). Cópia de desenvolvimento/testes: `/opt/projeto-web/sites/clients/appchama-dev` (banco `appchama_dev`).
- Caddy dos endereços: `/opt/projeto-web/clients/vaide55-*.vps.caddy` (com cabeçalhos de segurança). Validar e recarregar: `docker exec projeto-web-web-1 caddy validate --config /etc/caddy/Caddyfile` e `caddy reload`.
- Backups antes de cada publicação: `/root/appchama-backups/<etapa>-<data>/` (banco `.sql.gz` e código `.tgz`); o ponteiro `ULTIMO` aponta o mais recente.
- Publicar o backend (ainda manual; script `appchama-deploy` é a tarefa 14): backup → `rsync` de `api/` para a pasta de produção → `php artisan migrate --force` → `optimize:clear`, `config:cache`, `route:cache` → `queue:restart` → conferir os endereços.

## 6. Testes automáticos (rodar antes de publicar)
Rodam na cópia de desenvolvimento, dentro de transação (não deixam rastro). Na VPS: `bash /root/chk/scripts/rodar-verificacoes.sh` (sincroniza `api/` para o desenvolvimento, migra e roda todos), ou um por vez:
`docker exec -w /srv/sites/clients/appchama-dev projeto-web-php-1 php artisan tinker scripts/<nome>.php`

| Script | O que confere |
|---|---|
| `verifica-isolamento` | cada marca só enxerga os próprios dados; regras de administradores |
| `verifica-telas` | telas Marcas e Administradores |
| `verifica-otp` | código de acesso: contas de teste, sem vazamento, limites |
| `verifica-cidades` | motorista só se cadastra onde está aberto; passageiro em todas |
| `verifica-paineis` | todas as telas do painel, isolamento, permissões e formulários |
| `verifica-corrida` | corrida completa pela API (cadastro, login, pedido, aceite, fim) |
| `verifica-textos-marca` | nenhum texto fixo "Chama" visível ao cliente final |

## 7. Pendências abertas (o que falta para publicar)
1. **Preços reais** das cidades (hoje provisórios, copiados do demo): bandeirada R$ 12, R$ 0,90/km, R$ 0,50/min, mínima R$ 8. Editar em Cidades.
2. **Restringir a chave do Google** aos dois apps (pacote + SHA-1 acima).
3. **SMS real** (tarefa B1): sem ele só as contas de teste entram. A API hoje responde "OTP enviado" mesmo sem enviar.
4. **Asaas** do Vai de 55 (conta própria ou da plataforma): sem ele o motorista não recarrega a carteira pré-paga (mínimo R$ 20 para aceitar corrida) e o passageiro não paga pelo app. Comissão atual: 15%.
5. **Contatos de suporte e links legais** (privacidade, termos, exclusão de conta) na tela Marcas (só a plataforma edita) e **site** do Vai de 55.
6. **Conta Google Play** (US$ 25): se pessoal, exige teste fechado com 12 testadores por 14 dias.
7. Notificação push do motorista; localização em segundo plano; iOS.
8. Merge do PR do app do motorista (CNH) se ainda estiver aberto; tornar o repositório **privado**.
9. Trocar senhas que apareceram em conversas (tarefa 4 do servidor).
