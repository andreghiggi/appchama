# AppChama / Vai de 55 — instruções para o Claude (leia ao abrir este repositório)

Este repositório é a **fonte da verdade** do AppChama (marca modelo) e das marcas white label (hoje: Vai de 55). O dono trabalha em dois computadores; o GitHub é o ponto de encontro.

## 1. Ao começar, SEMPRE
1. `git fetch origin` e `git status`. Se a `main` mudou, `git pull`. Trabalhe em **branch própria** (`feat/...`), nunca direto na `main`.
2. Confira se este computador está configurado: leia `docs/CONFIGURAR-COMPUTADOR.md` e verifique cada item do checklist. **Se algo faltar, avise o dono e ajude a configurar antes de seguir.**
3. Servidor (VPS 69.169.97.213): leia o HANDOFF (`ssh root@69.169.97.213 "cat /opt/projeto-web/docs/HANDOFF-SERVIDOR.md"`) e as tarefas `VAIDE55 ...` (`vps-site tarefa lista`). Registre o que fizer (`vps-site log --quem NOME`, `vps-site tarefa nota ...`).

## 2. Ao parar
- Commit + push da sua branch; o dono faz o merge pelo GitHub. Nunca `push --force`.
- Atualize `vps-site tarefa nota/proximo` e `vps-site log` para o outro computador continuar.

## 3. Estrutura
- `api/` backend Laravel 12 + Filament (multi-marca). `mobile/` apps Expo (passenger, driver, shared). `marcas/<marca>/` identidade e configuração de cada marca. `docs/` guias.
- Cada marca = configuração (`marcas/<marca>/marca.json` + `assets/`), **nunca** uma cópia do código. Recurso novo vale para todas as marcas.
- Guias: `docs/REGRAS-MARCAS.md`, `docs/PUBLICAR-APPS-LOJAS.md`, `docs/PLANO-WHITELABEL-VAIDE55.md`, `marcas/vaide55/IDENTIDADE.md`.

## 4. Regras que não se quebram
- **Nunca** commitar senha, token, chave de API, `.env`, keystore (`*.jks`, `*.keystore`), `google-services.json`. Segredos ficam no `.env` do servidor e nos segredos do EAS (`eas secret:create`).
- Contas de teste do código SMS (telefones e códigos fixos) ficam no `.env` do servidor (`OTP_TEST_ACCOUNTS`); peça ao dono, não coloque no Git.
- Mudar o backend em produção: backup do banco e do código antes; rodar `api/scripts/verifica-isolamento.php`, `verifica-telas.php` e `verifica-otp.php` (via `php artisan tinker scripts/...` na cópia de desenvolvimento `appchama-dev`) antes de publicar.
- Não alterar DNS de domínio de cliente nem emitir nota fiscal sem confirmação do dono. Este projeto não tem relação com o Emit Smart; não misturar.
- Arquivos novos ou editados em Windows: manter quebra de linha **LF** (o repositório usa LF).
- Pacotes Android (`com.vaide55.*`) são permanentes depois de publicados; não mudar.
- **Responder ao dono SEMPRE em português do Brasil**, inclusive avisos curtos entre etapas e descrições de comandos; linguagem simples, sem jargão; explicar o que foi feito e o que falta.
- Antes de publicar o backend: `bash scripts/rodar-verificacoes.sh` (na VPS) tem de terminar com "TUDO OK".
- Endereços, logins, apps, Expo, servidor, testes e pendências: `docs/ACESSOS-E-URLS.md` (sem senhas; diz onde cada segredo fica).
