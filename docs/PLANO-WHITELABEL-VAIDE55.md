# Plano White Label do AppChama — primeira marca: Vaide55

> Mantido pelo computador principal. Última atualização: 12/11/2026. Estado das tarefas: `vps-site tarefa lista`.

## Objetivo
Transformar o AppChama em plataforma multi-marca. A primeira marca é a **Vaide55**:
- App **passageiro** e app **motorista** nas lojas (Play Store e App Store).
- API em `api.vaide55.com.br` (mesmo backend, filtrado pela marca).
- Painel administrativo da marca em `adm.vaide55.com.br`, vendo só os dados dela.
- Site institucional em `vaide55.com.br` (política de privacidade, termos, links das lojas).

## Situação encontrada (auditoria somente leitura, 12/11/2026)
**Backend** (`/opt/projeto-web/sites/clients/appchama-agilizeerp`, Laravel 12, Filament 5, Sanctum, Reverb, Asaas, Mapbox):
- Multi-marca já iniciado: tabela `tenants` (name, slug, primary_color, logo_url, status, Asaas por marca, comissão, formas de pagamento). Só existe 1 marca: "Chama Demo". Base pequena: 14 usuários, 108 corridas, 4 cidades.
- A marca é resolvida por `X-Tenant-Slug` (cabeçalho), `tenant_slug` (parâmetro, usado no login por celular) ou `DEFAULT_TENANT_SLUG` (.env). **Não há resolução por domínio** e a tabela `tenants` **não tem coluna de domínio**.
- `users` tem índice único `(tenant_id, phone)`: o mesmo celular pode existir em marcas diferentes (bom).
- Tabelas COM `tenant_id`: cities, rides, ride_payments, driver_withdrawals, driver_wallet_recharges, otp_codes, saved_addresses, support_tickets, users.
- Tabelas SEM `tenant_id` (filhas de usuário/corrida; isolamento só pelo pai): drivers, driver_wallets, driver_wallet_transactions, ratings, ride_locations, vehicles, subscriptions e subscription_payments (conferir).
- Documentos da CNH ficam em disco local em `driver-cnh/{tenant_id}/` (separado por marca; precisa entrar no backup).
- **PROBLEMA CENTRAL:** o painel admin e seus gráficos usam `withoutGlobalScope(TenantScope::class)` (Cidades, Corridas, Pagamentos, Saques, StatsOverview, Finanças, Operações, Mapa de calor, Mapa ao vivo). Ou seja, o admin vê **todas as marcas misturadas**. E `canAccessPanel` só confere se o usuário é admin/support, sem checar a marca.
- Canal de websocket `city.{id}.drivers` autoriza qualquer admin, sem checar a marca.
- SMS: `SMS_PROVIDER=log` (não envia SMS de verdade). Login é por código no celular, então é bloqueante para produção.
- Painel se chama "Chama Admin" (fixo no código), sem nome, logo e cor por marca.

**Apps** (Expo / React Native): no servidor só há as exportações web (`appchama-motorista`, `appchama-passageiro`, servidas em motor.* e passa.agilizeerp.com.br). O código-fonte está no computador de casa (nitrinho, `C:\xampp\htdocs\appchama`). O white label local (nitrinho) já tem `branding/vaide55/config.ts` (cores provisórias), `clients/vaide55/build-config.json` e `.env.vaide55` com `DEFAULT_TENANT_SLUG`. Só há um identificador de app (`com.vaide55.app`): faltam dois (passageiro e motorista). O telefone do arquivo (+55 11 99999-9999) é fictício.


## MODELO DE ACESSO (definido pelo dono, 12/11/2026)
Dois niveis, sempre separados:
- **Plataforma AppChama (super-admin):** ve TODAS as marcas (com filtro por marca), cria/edita marcas (nome, logo, cor, dominios), define comissao e formas de pagamento liberadas, ve financas consolidadas. Painel atual: `appchama.agilizeerp.com.br/admin`. O admin atual do Chama Demo vira o super-admin.
- **Cada cliente (ex.: Vaide55):** entra em `adm.vaide55.com.br` e ve SOMENTE os dados da sua marca (corridas, motoristas, passageiros, pagamentos, saques, mapas e graficos). Papeis: admin da marca e suporte da marca.
Regras tecnicas: o dominio decide a marca; usuario de uma marca nao entra no painel de outra; nenhum widget/grafico pode ignorar o filtro da marca (hoje 15+ pontos usam withoutGlobalScope); canal de websocket `city.{id}.drivers` por marca; nome/logo/cor do painel vem da marca.
Propostas a confirmar: o dono da marca edita sozinho cidades, precos, cadastro de motoristas e a propria conta Asaas; SO a plataforma altera comissao e formas de pagamento.


## PRINCÍPIO (dono, 12/11/2026): O APPCHAMA É O APP MODELO
Todas as marcas (Vaide55 e as próximas) saem do MESMO código do AppChama. O Vaide55 não é um app separado: é o AppChama com a configuração do Vaide55.
Regras:
1. **Um só código-fonte** (backend e apps). Nada de cópia/fork por marca. O AppChama é a marca nº 1, gerada pelo mesmo processo das outras.
2. **Marca = configuração:** nome, ícone, splash, identificador do app, endereço da API e apelido da marca no build; cores, logo, contatos, formas de pagamento e recursos ligados/desligados vêm do servidor (por marca).
3. **Recurso novo entra no código comum** e vale para todas as marcas. Se uma marca precisar de algo exclusivo, vira "recurso ligado por marca" (desligado nas outras), nunca um desvio de código.
4. **Atualização igual para todos:** um comando compila todas as marcas; backend já é único. Cada app é publicado na sua loja (a loja exige uma versão por app).
5. **Fonte da verdade do código dos apps:** repositório central (a definir; proposta: repositório git no servidor), usado pelos dois computadores. O que está só no computador de casa é risco e deve ser enviado.
Backend já cumpre 1 e 2 (marca por endereço, tenant_id, painel por marca). Falta: endpoint de configuração da marca para os apps (cores/logo/contatos/recursos em tempo de execução) e o repositório central do app.

## Etapas
| # | Etapa | Quem | Entrega |
|---|---|---|---|
| 0 | Definições | dono | Respostas às decisões abaixo |
| 1 | Backend multi-marca firme | principal | Marca vaide55 criada; coluna de domínios (api/adm) e resolução por domínio; painel filtrado pela marca (super-admin vê tudo); nome/logo/cor por marca no painel; canal de websocket checando a marca; testes de isolamento entre marcas |
| 2 | Infraestrutura e domínios | principal | DNS do vaide55 (pendente da troca no Registro.br), Caddy/HTTPS para api, adm e site; websocket no endereço novo; backup dos documentos |
| 3 | Apps por marca | nitrinho | Um código-fonte que gera cada marca por configuração (nome, ícone, splash, cores, identificadores, API, marca); dois apps por marca |
| 4 | Serviços reais | principal + dono | SMS de verdade (ou WhatsApp), conta Asaas do Vaide55, Mapbox, notificações push (Firebase/APNs) |
| 5 | Testes | todos | Asaas em sandbox; teste interno nas lojas; roteiro de testes |
| 6 | Lojas | dono + nitrinho | Contas de desenvolvedor, fichas das lojas, política de privacidade, exclusão de conta, publicação |
| 7 | Modelo repetível | principal | Roteiro e script para criar a próxima marca em poucas horas |


## DECISÕES TOMADAS (dono, 12/11/2026)
1. **Um backend para todas as marcas** (multi-tenant no AppChama atual). Sem cópia separada.
2. **Contas das lojas:** por enquanto na conta de desenvolvedor do próprio dono (Aquafast/i9), **com cada marca como app separado** (ficha, ícone, nome e identificador próprios). No futuro cada cliente pode assumir a própria conta (a Apple exige isso para app de template: diretriz 4.2.6).
3. **Android primeiro** (Play Store). iOS depois.
4. **Nomes dos apps:** "Vaide55 Passageiro" e "Vaide55 Motorista". Identificadores propostos (CONFIRMAR, são permanentes depois de publicar): `com.vaide55.passageiro` e `com.vaide55.motorista`.
**Ainda abertas (5 a 8):** conta Asaas do Vaide55 e comissão; provedor de SMS; logo/cores/e-mail/telefone reais; cidades, preços e administradores do adm.

### Pontos de atenção da Play Store (conferir na conta do dono)
- Conta pessoal NOVA: a Google exige teste fechado com pelo menos 12 testadores por 14 dias antes de liberar produção. Conta de organização exige D-U-N-S.
- Vários apps do mesmo código na mesma conta: manter nome, ícone, textos e capturas de tela distintos por marca (evita bloqueio por conteúdo repetido).
- Política de privacidade e exclusão de conta são obrigatórias (vão no site `vaide55.com.br`).

## Decisões do dono (etapa 0)
1. Um backend para todas as marcas (recomendado) ou cópia separada por cliente.
2. Contas das lojas no nome do dono do Vaide55 (Google Play US$ 25; Apple US$ 99/ano; empresa exige D-U-N-S).
3. Android primeiro e iOS depois, ou os dois já.
4. Nomes e identificadores dos 2 apps (ex.: "Vaide55" e "Vaide55 Motorista").
5. Pagamento: conta Asaas própria? Comissão e formas de pagamento.
6. Provedor de SMS (ou código por WhatsApp).
7. Logo, cores, nome, e-mail e telefone de suporte reais.
8. Cidades e regras de preço do Vaide55; quem serão os administradores do `adm.vaide55.com.br`.

## Regras do trabalho
- Backend e infraestrutura: computador principal. App mobile: nitrinho. Registrar tudo com `vps-site tarefa` e `vps-site log`.
- Nenhuma alteração em produção do AppChama atual (Chama Demo) sem backup e sem testar o isolamento entre marcas.
- Nunca guardar senha em documento; segredos só nos `.env`.


## ETAPA 1 CONCLUÍDA (12/11/2026) — backend multi-marca no ar
**O que foi feito no AppChama (produção, `/opt/projeto-web/sites/clients/appchama-agilizeerp`):**
- Tabela `tenant_domains` (host -> marca; tipos api, admin, site) e coluna `users.is_platform_admin` (o admin atual virou administrador da plataforma).
- `tenant_id` adicionado e preenchido em: drivers, vehicles, driver_wallets, driver_wallet_transactions, ratings, ride_locations, subscriptions, subscription_payments. Todos os registros existentes foram marcados (0 sem marca). Modelos herdam a marca do "pai" ao criar.
- `ResolveTenantContext` (web, api e painel): a marca vem do ENDEREÇO. Em endereço de marca o cabeçalho `X-Tenant-Slug` não troca a marca; usuário de uma marca em endereço de outra recebe 403. Endereços da plataforma (`appchama/chama/apichama.agilizeerp.com.br`, config `tenancy.platform_hosts`) continuam como antes.
- Painel (Filament): removidos os 17 `withoutGlobalScope` que misturavam marcas; admin/suporte de marca só entra no endereço da própria marca; super-admin entra em qualquer um e tem seletor "Marca:" (todas ou uma); nome, logo e cor do painel vêm da marca.
- Canal `city.{id}.drivers` só autoriza admin da mesma marca (ou super-admin).
- Comando `php artisan tenant:create <slug> <nome> --color --api-host --admin-host --site-host` (reutilizável para a próxima marca).
- Marca **vaide55** criada em produção (cor #FF6B35, Asaas em sandbox, comissão 15%, cartão e pix ligados, pagamento direto ao motorista desligado) com api.vaide55.com.br, adm.vaide55.com.br e vaide55.com.br.
- DNS: adicionado `adm` na zona; blocos Caddy `vaide55-api` e `vaide55-adm` prontos e PENDENTES (ligam sozinhos quando o DNS apontar).
**Testes:** 27 verificações de isolamento (`scripts/verifica-isolamento.php`, rodam com rollback) + 21 testes unitários existentes + testes de tela por endereço. Todos passam em dev e em produção.
**Backups e volta atrás:** `/root/appchama-backups/etapa1-*` (banco + código + caddy; `ULTIMO` aponta o diretório; `appchama-db-pre-migracao.sql.gz` é do minuto antes da migração). Cópia de trabalho: `sites/clients/appchama-dev` (banco `appchama_dev`). Para reverter: restaurar o código do tar, `php artisan migrate:rollback --step=1` (a migração tem `down`) ou o dump.
**Pendente da etapa 1:** criar administradores da marca (comando), tela de gerenciar marcas para o super-admin, e conferir manualmente o painel no navegador.


## ETAPA 1b CONCLUÍDA (12/11/2026) — marcas e administradores pelo painel
No painel do AppChama (menu **Marcas e acessos**):
- **Marcas** (só super-admin): criar/editar marca (nome, cor, logo, situação, comissão, ambiente Asaas, Pix/cartão/pagamento direto) e os endereços (api/adm/site). Marca não se apaga: suspende-se.
- **Administradores**: o super-admin cria admin ou suporte de qualquer marca; o admin da marca cria/gerencia só o suporte da própria marca. A senha temporária é gerada pelo sistema e mostrada uma única vez (também em "Redefinir senha"). Desativar/reativar em vez de apagar. E-mail único (é o login). Telefone ficou opcional para quem usa o painel (migração `users.phone` NULL).
- Painel: cada usuário tem "Perfil" para trocar a própria senha.
- Comando equivalente: `php artisan tenant:admin <slug> <email> "<Nome>" --role=admin|support`.
**Correções de vazamento entre marcas achadas no caminho:** (1) a página "Pagamentos Asaas" sempre abria a marca padrão (chama-demo): agora usa a marca do contexto e, para o super-admin sem marca escolhida, some do menu até escolher uma no seletor "Marca:"; comissão e formas de pagamento só a plataforma altera; (2) "Nova cidade" sempre criava na marca padrão: agora cria na marca do contexto (super-admin precisa escolher a marca no seletor antes).
**Testes (rodam com rollback em dev e produção):** `scripts/verifica-isolamento.php` (52 verificações) e `scripts/verifica-telas.php` (10 telas). Backups: `/root/appchama-backups/etapa1-*/` (`*-pre-etapa1b*`).


## MAPA DE LANÇAMENTO — VAIDE55 NA PLAY STORE (Android primeiro) — 12/11/2026
Caminho crítico: **app compilado -> teste fechado na Play (14 dias, se a conta for pessoal nova) -> produção.** O que mais demora é o teste fechado, então o objetivo é ter um primeiro app instalável o quanto antes.
A. Base no ar: A1 backend multi-marca [FEITO]; A2 DNS do vaide55 (dono, Registro.br, desativar DNSSEC) [PENDENTE]; A3 admin do Vaide55 criado (dono, tela Administradores) [PENDENTE]; A4 cidades e preços do Vaide55 no painel (dono) [PENDENTE].
B. Serviços: B1 SMS real + fechar a falha do debug_code (precisa escolher o provedor) [PENDENTE, bloqueia o login real]; B2 conta Asaas do Vaide55, começando em sandbox (dono) [PENDENTE]; B3 notificações push: Firebase do Vaide55 + backend [PENDENTE]; B4 Mapbox (a chave atual serve para testes) [OK].
C. Apps: C1 nitrinho envia o código-fonte completo [PENDENTE, bloqueia C2-C5]; C2 branding (logo, paleta, nome, ícone, splash) [precisa do dono]; C3 identificadores com.vaide55.passageiro e com.vaide55.motorista (confirmar) ; C4 build por marca apontando para api.vaide55.com.br; C5 assinatura (keystore) e pacote AAB.
D. Play Store: D1 conta Play Console (dono); D2 ficha de cada app (descrição, capturas, ícone 512, classificação, formulário de segurança de dados); D3 site vaide55.com.br com política de privacidade, termos e exclusão de conta (Claude monta); D4 teste interno -> fechado (12 testadores, 14 dias) -> produção.
E. Operação: cadastro dos primeiros motoristas, suporte, atualização dos apps.
Independente de terceiros (Claude pode fazer já): D3 (site e textos legais, com dados provisórios), preparar SMS multi-provedor, push no backend.
Dono precisa decidir/enviar: provedor de SMS, logo e paleta, e-mail e telefone de suporte reais, confirmar os identificadores, abrir conta Play Console e Asaas, trocar o DNS.


## INÍCIO FORMAL DO PLANO (12/11/2026)
Decidido: um só código; `main` + pasta `marcas/` por configuração (sem branch longa por marca).
Feito: repositórios centrais criados no servidor (`/srv/git/appchama.git` com o backend atual; `/srv/git/appchama-app.git` com a estrutura `marcas/` e as regras).
Próximos passos imediatos: (1) nitrinho envia o código do app (snapshot via `vps-site receber appchama-mobile nitrinho`); (2) principal revisa (sem segredos) e importa para `src/` do repositório do app e levanta pacotes/assets do AppChama; (3) backend: endereço de configuração da marca (cores, logo, contatos, recursos) para os apps; (4) script de deploy do backend a partir do repositório; (5) SMS real; (6) site e textos legais; (7) DNS.
