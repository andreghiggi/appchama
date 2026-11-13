# Configurar um computador para trabalhar no AppChama / Vai de 55

Use este checklist quando o repositório for baixado num computador novo (ou para conferir um que já existe). O Claude deve verificar cada item e ajudar a resolver o que faltar. Marque o que já está pronto.

## 1. Repositório
- [ ] `git clone https://github.com/andreghiggi/appchama.git` (se o repositório for privado, o computador precisa de acesso: login do GitHub no Git Credential Manager ou chave SSH).
- [ ] `git config user.name` e `git config user.email` definidos (use o e-mail do projeto, **nunca** o e-mail pessoal do dono).
- [ ] Em Windows: `git config core.autocrlf input` (para não gerar diferenças de quebra de linha).
- [ ] Trabalhar sempre em branch própria; `main` só por merge do dono.

## 2. Acesso ao servidor (VPS 69.169.97.213)
- [ ] `ssh root@69.169.97.213 "echo ok"` responde `ok` **sem pedir senha**. Se pedir, o dono autoriza a chave deste PC uma vez:
  `type %USERPROFILE%\.ssh\id_ed25519.pub | ssh root@69.169.97.213 "cat >> ~/.ssh/authorized_keys"` (digita a senha uma vez; a senha não fica gravada).
- [ ] Skill `vps` instalada (diz "acionar agente vps"); `vps-site inbox --quem NOME` funciona.

## 3. Para mexer no app (mobile)
- [ ] Node 20 ou mais novo (`node -v`) e npm.
- [ ] `cd mobile/passenger && npm install` (e `mobile/driver`). Não commitar `node_modules`.
- [ ] Testar uma marca: `MARCA=vaide55 npx expo start`.
- [ ] Conta Expo logada para gerar builds: `npx eas-cli login`, conferir com `npx eas-cli whoami`. (Só o computador que gera builds precisa disso.)
- [ ] Builds locais (opcional): JDK 17 e Android SDK. O caminho normal é o build na nuvem do Expo (EAS): `docs/PUBLICAR-APPS-LOJAS.md`.

## 4. Para mexer no backend (api)
- [ ] O backend roda no servidor, não no PC. Desenvolvimento e testes são feitos na cópia `appchama-dev` do servidor (via SSH), seguindo o `CLAUDE.md`.
- [ ] Se for rodar local: PHP 8.3+, Composer, MariaDB/MySQL; copiar `api/.env.example` para `api/.env` e preencher (segredos pedem ao dono).

## 5. O que NÃO vem pelo Git (peça ao dono quando precisar)
- Contas de teste do código SMS (`OTP_TEST_ACCOUNTS`, no `.env` do servidor).
- Chaves: Google Maps, Asaas, Mapbox, SMS; chaves de assinatura do app.
- Senhas e tokens em geral.

## 6. Ler antes de começar
- [ ] `docs/ACESSOS-E-URLS.md`: endereços, logins, apps, Expo, servidor, testes e o que falta fazer.

## 7. Conferência final
- [ ] `git status` limpo e `git log -1` igual ao da `main` do GitHub.
- [ ] `vps-site tarefa lista` mostra as tarefas `VAIDE55 ...`.
- [ ] Registrar no servidor: `vps-site log --quem NOME "computador configurado"`.
