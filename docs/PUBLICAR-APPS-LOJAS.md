# Publicar os apps de uma marca nas lojas (Android primeiro)

Vale para qualquer marca (`marcas/<marca>/`). Exemplo: `vaide55`. Atualizado em 13/11/2026.

## Como o build funciona
- O código dos apps é um só (`mobile/passenger` e `mobile/driver`, Expo/React Native).
- A marca é escolhida pela variável `MARCA`. O arquivo `mobile/shared/marca-config.js` lê `marcas/<marca>/marca.json` e `assets/` e monta nome, pacote, ícone, abertura, cores, API e identificador da marca.
- O build é feito na nuvem do Expo (**EAS Build**), que também cuida da chave de assinatura. A VPS não compila (1 CPU, 1 GB, sem Node/Java).
- Perfis em `mobile/passenger/eas.json` e `mobile/driver/eas.json` (os dois arquivos são iguais; o Expo só lê o `eas.json` da pasta do app): `<marca>-teste` gera um **APK** para instalar direto no celular; `<marca>` gera o **AAB** para a Play Store.

## Comandos (rodar em `mobile/passenger` ou `mobile/driver`)
```
npm install
MARCA=vaide55 npx expo start                       # testar no celular (Expo Go) apontando para a API da marca
npx eas-cli login                                  # uma vez
# (projetos Expo ja criados; para uma marca nova: eas init --account <conta> e copiar o id para marca.json, sem deixar o app.json mudar)
npx eas-cli build --profile vaide55-teste --platform android   # APK de teste
npx eas-cli build --profile vaide55 --platform android         # AAB para a loja
```
- O id do projeto Expo de cada app vai em `marcas/<marca>/marca.json` → `apps.<passageiro|motorista>.eas_project_id` (não é segredo).
- Conta Expo dona dos projetos: `expo_owner` em `marca.json` (hoje `andrehiggis-team`). Projetos do Vai de 55: `vaide55-passenger` e `vaide55-driver`. NÃO use `eas init` para gravar o id: ele sobrescreve o `app.json` com a configuração da marca. O id vem de `marca.json`.
- Chave do Google Maps: já cadastrada como variável secreta `GOOGLE_MAPS_API_KEY` nos dois projetos (ambientes `preview` e `production`). Trocar: `npx eas-cli env:create --name GOOGLE_MAPS_API_KEY --value ... --environment production --visibility secret --force`.
- Cada nova versão na loja precisa de `versao_codigo` maior (campo `apps.<app>.versao_codigo` ou variável `APP_VERSION_CODE`).
- Segredos (chave do Google Maps etc.) NUNCA entram no repositório: use `eas env:create ... --visibility secret`.

## O que a loja (Google Play) exige
1. **Conta de desenvolvedor Google Play** (taxa única de US$ 25) no nome do dono. Verificação de identidade.
   - Conta **pessoal** nova: antes de publicar em produção é obrigatório um **teste fechado com pelo menos 12 testadores por 14 dias seguidos**. Conta de **empresa** (com D-U-N-S) não tem essa regra, mas a verificação demora mais.
2. **Chave do Google Maps (Maps SDK for Android)**: o mapa nativo do app usa o Google Maps. Sem a chave, o mapa fica em branco. Criar no Google Cloud, restringir ao pacote `com.<marca>...` e ao SHA-1 de assinatura.
3. **Política de privacidade** (URL pública), **termos de uso** e **página de exclusão de conta** (URL pública). Cadastrar em: painel → Marcas → *Contatos e textos legais* e no formulário da loja.
4. **Formulário "Segurança dos dados"** e **classificação de conteúdo** (questionários na Play Console).
5. **Material gráfico:** ícone 512×512, imagem de destaque 1024×500, no mínimo 2 capturas de tela de celular por app.
6. **Login de teste para a revisão da Google** (o app entra por telefone + código SMS: é preciso um número de teste com código fixo ou usuário de demonstração).
7. Textos: nome do app, descrição curta (80 caracteres), descrição completa.

## Antes de publicar (o app precisa funcionar de verdade)
- [ ] SMS real do código de acesso (hoje o servidor só registra o código no log; sem isso ninguém entra).
- [ ] Pelo menos uma cidade com área de atendimento e preços cadastrados para a marca (painel da marca → Cidades).
- [ ] Conta Asaas da marca configurada (ou decisão de usar a da plataforma) e formas de pagamento escolhidas.
- [ ] Contatos de suporte e URLs legais preenchidos na tela Marcas.
- [ ] Teste de ponta a ponta com dois celulares (passageiro pede, motorista aceita, finaliza e paga).
- [ ] Notificação push para o motorista (hoje ele só recebe corrida com o app aberto) — tarefa 16.

## Limites conhecidos desta versão
- O app do motorista só rastreia a localização com o app aberto.
- Não há notificação push (tarefa 16).
- iOS fica para depois (conta Apple Developer, US$ 99/ano).
- Erro de tipo antigo em `passenger/src/screens/HomeScreen.tsx` (linha ~222), sem impacto no build.
