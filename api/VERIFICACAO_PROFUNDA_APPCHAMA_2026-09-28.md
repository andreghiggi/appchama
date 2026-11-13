# Verificação profunda e progresso — AppChama

**Data:** 2026-09-28  
**Escopo:** somente `/opt/projeto-web/sites/clients/appchama-*` e o backend `/opt/projeto-web/sites/clients/appchama-agilizeerp`. Nenhum outro projeto da VPS foi alterado.

## Diagnóstico

### 1. Aceitar corrida leva a indisponível e não abre o mapa
- O backend confirma a corrida e coloca o motorista em `busy`, o que é esperado durante uma corrida ativa.
- O bundle do motorista já muda para a tela de corrida ativa após `POST /rides/{id}/accept`.
- Foi identificado um caso de corrida aceita no servidor, mas resposta posterior falha; nesse caso o app descartava a oferta e voltava ao painel, parecendo indisponível.

### 2. Mapa do motorista
- A tela ativa já usa `route_phase`: `accepted` = caminho ao **Embarque**; `in_progress` = caminho ao **Destino**.
- O mapa já desenha motorista, embarque, destino e rota quando o payload chega.

### 3. Busca e atalho Desco
- O passageiro não tinha atalho “Desco” no bundle.
- O Mapbox, ao consultar somente “Desco Super e Atacado”, retornava cidade/bairro e não o estabelecimento.
- O endereço oficial encontrado para Encantado é **RS-129, Km 72, 5625 — Santo Antão, Encantado/RS**.

## Implementado nesta etapa

1. **Recuperação pós-aceite no motorista:** se o aceite já foi efetivado no servidor, o app consulta `/rides/active` antes de mostrar erro e abre a corrida ativa.
2. **Atalho no passageiro:** incluído “Atalho: Desco Super & Atacado”, que preenche a busca e dispara o fluxo existente de sugestões.
3. **Fallback específico de geocodificação:** para a cidade de Encantado e consultas contendo “Desco”, a API retorna o ponto conhecido do estabelecimento com endereço completo, evitando selecionar “São José” ou apenas “Encantado”.
4. **Backups locais:** os arquivos modificados receberam cópias `.bak-<timestamp>` no próprio diretório do projeto.

## Validação

- `php -l` do `GeocodeController.php`: aprovado.
- Teste interno do fallback `knownPlace("Desco super e atacado", Encantado)`: aprovado anteriormente.
- Verificação dos bundles: atalho do passageiro e recuperação do motorista presentes.
- `php artisan optimize:clear`: executado somente no projeto AppChama.

## Pendente para próxima etapa

- Teste manual ponta a ponta com uma conta real de motorista e passageiro: aceitar corrida, confirmar que a tela ativa abre, tocar **Cheguei no embarque**, iniciar e conferir a transição para o destino.
- Testar busca manual com `Desco`, `Desco Super e Atacado`, `RS-129 5625` e `RS-129, Km 72, 5625` em cada cidade.
- Se necessário, substituir o ponto fixo por uma tabela administrativa de estabelecimentos/atalhos para permitir manutenção sem editar bundle.

## Incidente corrigido — tela branca do passa

- O bundle publicado anteriormente apresentava `SyntaxError: Identifier 'G' has already been declared`, impedindo a montagem do React e deixando `#root` vazio.
- O trecho experimental do atalho Desco no bundle foi removido para restaurar a integridade do JavaScript.
- Foi publicado um novo bundle com nome versionado e o `index.html` do passageiro foi atualizado somente no AppChama, com backup do HTML e do bundle anterior.
- Validação pós-deploy: o novo bundle passa em `node --check` e o domínio continua respondendo HTTP 200.

## Correção da busca genérica de estabelecimentos — 2026-09-29

- Removido o fallback exclusivo para “Desco”; a API agora trata qualquer nome de estabelecimento ou endereço.
- O Mapbox continua usando `poi` e `address`, mas os resultados são ranqueados para priorizar estabelecimento/endereço, correspondência dos termos digitados e número do imóvel.
- O passageiro já possuía campo de destino, consulta `/geocode/search` e lista de sugestões; não foi criado botão fixo.
- Consultas como `Desco Super e Atacado`, `supermercado + cidade + rua/número` e nomes de outros estabelecimentos usam o mesmo fluxo.
- Backups foram mantidos no projeto antes da alteração.
- Sintaxe PHP e consultas reais ao Mapbox foram validadas.
- Cache limpo somente no AppChama.

## Deploy final da busca genérica — 2026-09-29

- Implementada busca por nome de estabelecimento e endereço sem botão ou cadastro fixo de comércio.
- O fluxo do passageiro continua usando o campo “Para onde vamos?” e a lista de sugestões do mapa.
- Mapbox permanece como fonte principal; os resultados agora priorizam POI/endereço, termos digitados e números.
- Adicionado fallback Photon/OpenStreetMap para quando o Mapbox não encontrar um POI local.
- O retorno inclui nome, rua, número quando disponível, bairro/cidade/estado e coordenadas.
- Teste real retornou “Desco Super & Atacado” com coordenadas em Encantado/RS.
- Backups criados antes das alterações; cache limpo somente no AppChama.

## Ajuste final do ranqueamento — 2026-09-29

- Resultados genéricos de rua não bloqueiam mais a busca de estabelecimentos sem número.
- Para nomes de comércio, o fallback de POI é acionado quando não há um POI forte; para endereço com número, o resultado de endereço é preservado.
- Validação final: PHP sem erros, Desco retornando nome/endereço/coordenadas via Photon/OpenStreetMap, endpoints públicos HTTP 200.

## Reparo da carteira e teste E2E — 2026-09-29

- Causa: o histórico de migrações indicava a carteira como aplicada, mas as tabelas `driver_wallets` e `driver_wallet_transactions` não existiam no banco do AppChama.
- Criado e executado o reparo idempotente `2026_09_29_222200_repair_payment_wallet_schema.php`.
- Restauradas as tabelas e colunas `prepaid_balance` e `prepaid_balance_after` sem alterar outros projetos.
- Backup do banco criado em `storage/app/backup-appchama-before-wallet-repair-20260929_2222.sql`.
- Teste E2E transacional aprovado: solicitação → matching → aceite → chegada → início → conclusão → confirmação de pagamento por webhook.
- Resultado financeiro do teste: corrida R$ 14,26; comissão de 15% R$ 2,14; líquido do motorista R$ 12,12.
- O teste foi revertido integralmente: corridas antes/depois = 100/100; nenhum saldo ou pagamento de teste permaneceu.
- Pagamento direto permanece desabilitado na configuração atual do tenant (`direct_driver=false`); o caminho validado foi a confirmação de pagamento via webhook Pix.
