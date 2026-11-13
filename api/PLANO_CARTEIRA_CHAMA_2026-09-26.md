# Plano e progresso — Carteira pré-paga do Chama

**Data:** 26/09/2026  
**Escopo:** somente `appchama`; nenhum outro app, container ou serviço da VPS.

## Progresso atual

- Plano revisado e salvo no projeto.
- Auditoria do código concluída sem alterações nesta etapa.
- Já existentes no Chama: comissão configurável por tenant, cálculo de `platform_fee_amount`/`driver_net_amount`, carteira de ganhos do motorista, saques via Pix e pagamento direto ao motorista.
- Ainda não implementado: carteira pré-paga, recargas, débito idempotente da comissão e saldo mínimo de R$ 20,00.

## Implementação prevista — itens 1 e 2

### 1. Regras financeiras

- Comissão padrão do Chama: **15%**, configurável no tenant.
- Motorista recebe líquido de **85%**.
- A comissão será registrada separadamente por corrida, com transação idempotente.
- A carteira de ganhos/saques continuará separada da carteira pré-paga de comissão, para não quebrar os saques já existentes.
- Toda alteração financeira ocorrerá dentro de transação de banco e com trava de linha.

### 2. Carteira pré-paga

- Criar saldo específico para a carteira operacional do motorista.
- Criar lançamentos de recarga e débito de comissão.
- Impedir duplicidade de débito por corrida.
- Exibir saldo, último débito e aviso de saldo insuficiente no endpoint da carteira.
- Integrar a verificação ao fluxo de aceite/online conforme a decisão financeira abaixo.

## Decisão necessária antes do código

A solicitação informa “mínimo de R$ 20,00” e que o saldo será consumido pela comissão, mas não define qual comportamento deve ocorrer:

1. **Saldo mínimo para operar:** motorista só fica online/aceita corridas se o saldo pré-pago tiver pelo menos R$ 20,00; a comissão é debitada ao finalizar cada corrida.
2. **Recarga mínima:** o motorista pode usar o saldo até zerar; quando precisar recarregar, o valor mínimo da recarga é R$ 20,00. Nesse caso, a recarga via Pix/cartão precisa ser implementada junto para evitar bloqueio sem forma de abastecimento.
3. **Bloqueio por comissão:** o motorista pode ficar online, mas não pode aceitar uma nova corrida quando o saldo não cobrir a comissão estimada.

Também é necessário definir se a comissão de 15% será debitada da carteira pré-paga em corridas pagas diretamente ao motorista (dinheiro/Pix pessoal).

## Próxima etapa após a decisão

- Implementar a regra escolhida e a recarga correspondente.
- Criar/rodar migrações somente no banco do `appchama`.
- Atualizar API e app do motorista.
- Testar aceite, conclusão, pagamento direto, pagamento via app, duplicidade, estorno e saldo insuficiente.
- Fazer deploy somente do `appchama`.
- Atualizar este arquivo com arquivos alterados, testes e resultado do deploy.

## Progresso da implementação

- **Implementado:** carteira operacional pré-paga separada da carteira de ganhos/saques.
- **Implementado:** recarga via Pix Asaas com valor mínimo de R$ 20,00. O saldo só é creditado após webhook de pagamento confirmado.
- **Implementado:** débito idempotente da comissão de 15% por corrida, inclusive em pagamento direto ao motorista.
- **Implementado:** verificação no aceite: o motorista pode consumir o saldo até zerar, mas não aceita nova corrida se não houver saldo para a comissão estimada.
- **Implementado:** saldo pré-pago e aviso de recarga no endpoint da carteira.
- **Implementado:** botão de recarga Pix no painel web do motorista.
- **Migração executada somente no appchama:** `2026_09_26_110000_add_prepaid_wallet_recharges`.
- **Validação:** sintaxe PHP sem erros; rotas da carteira disponíveis; bundle do motorista validado com `node --check`; domínios do Chama respondendo HTTP 200.

### Arquivos principais alterados

- `database/migrations/2026_09_26_110000_add_prepaid_wallet_recharges.php`
- `app/Models/WalletRecharge.php`
- `app/Domains/Payments/Services/DriverWalletService.php`
- `app/Domains/Payments/Services/DriverWalletRechargeService.php`
- `app/Http/Controllers/Api/DriverWalletController.php`
- `app/Http/Controllers/Webhooks/AsaasWebhookController.php`
- `app/Domains/Rides/Services/RideService.php`
- `app/Domains/Payments/Services/RidePaymentService.php`
- `routes/api.php`
- `../appchama-motorista/_expo/static/js/web/index-6ad17a0d8324669b53b1b12d81d2efa0.js`

## Deploy

Deploy realizado somente no conjunto do Chama (`appchama` e seu app web de motorista). Nenhum outro cliente foi reiniciado, migrado ou alterado.

## Verificação final — 26/09/2026
- **Escopo confirmado:** somente o conjunto AppChama foi inspecionado e operado; nenhum cliente, migration, container ou serviço de outro projeto foi reiniciado ou alterado.
- **Implementação confirmada em produção:** carteira pré-paga separada, recarga Pix Asaas com mínimo de R$ 20,00, crédito condicionado ao webhook confirmado, débito idempotente de comissão e bloqueio de aceite quando o saldo não cobre a comissão estimada.
- **Banco:** a migration `2026_09_26_110000_add_prepaid_wallet_recharges` está com status `Ran` no banco `appchama`; a tabela `driver_wallet_recharges` existe nesse banco.
- **API:** rotas de carteira, recarga e saques aparecem no `route:list` do Laravel.
- **Sintaxe:** todos os arquivos PHP listados neste plano passaram em `php -l` dentro do container PHP.
- **Frontend:** o bundle web do motorista está presente em `../appchama-motorista/_expo/static/js/web/index-6ad17a0d8324669b53b1b12d81d2efa0.js`; a validação `node --check` não pôde ser repetida nesta VPS porque o binário `node` não está instalado no host.
- **Testes automatizados:** o projeto não possui o executável `vendor/bin/phpunit` instalado no ambiente de produção; portanto, a suíte PHPUnit não foi declarada como aprovada.
- **Disponibilidade externa:** `https://appchama.agilizeerp.com.br`, `https://apichama.agilizeerp.com.br`, `https://motor.agilizeerp.com.br` e `https://passa.agilizeerp.com.br` responderam HTTP 200.
- **Deploy/ativação:** os arquivos já estavam no volume ativo do AppChama; foi executado `php artisan optimize:clear` somente no AppChama para remover caches antigos. Não foi necessário reiniciar containers compartilhados nem executar migration adicional.
- **Backup de prudência:** cópia do plano anterior salva em `/root/appchama_plan_before_final_20260926121017.md`.

## Validação adicional — 26/09/2026
- **Dependências de teste:** dependências de desenvolvimento foram instaladas somente no AppChama a partir do `composer.lock`, usando container Composer temporário; nenhum pacote foi instalado no host e nenhum outro projeto foi tocado.
- **PHPUnit:** executado no container PHP do AppChama. Resultado: **10 testes, 16 asserções, 1 erro e 1 falha**. As falhas ocorreram nos testes preexistentes de `ServiceAreaService`/`ExampleTest` (validação de área de atendimento e resposta HTTP), não nos arquivos da carteira pré-paga. Nenhum código foi alterado para mascarar ou corrigir essas falhas.
- **Bundle JavaScript:** o arquivo `../appchama-motorista/_expo/static/js/web/index-6ad17a0d8324669b53b1b12d81d2efa0.js` passou em `node --check` fora da VPS. Checksum validado: `850e5370767a0c50a36a84e0bdad424f5170b2c14d0d58d00535da26e561f1f1`.
- **Escopo:** a cópia temporária do bundle foi removida após a validação; nenhuma alteração foi feita fora do AppChama.

## Correção e validação dos testes — 26/09/2026
- **Falha de ambiente corrigida apenas nos testes:** `APP_KEY` de teste foi definido com `force="true"` no `phpunit.xml`; nenhuma chave de produção foi alterada.
- **Falha de regra de teste corrigida:** o teste de área usava as coordenadas de Encantado, que ficam dentro do raio configurado de 50 km de Guaporé; o cenário de rejeição passou a usar coordenadas de São Paulo, realmente fora do raio.
- **Cobertura adicionada:** `tests/Unit/PrepaidWalletRulesTest.php` valida comissão configurada de 15% (R$ 100,00 → taxa R$ 15,00 e líquido R$ 85,00) e rejeição de recarga abaixo de R$ 20,00 antes de chamar o gateway.
- **PHPUnit final:** **12 testes, 21 asserções, todos aprovados**.
- **Sintaxe/XML:** arquivos PHP alterados e `phpunit.xml` validados sem erro.
- **Produção preservada:** nenhuma lógica de produção, migration, container ou serviço foi alterada nesta etapa; não foi necessário novo deploy.

## Próxima etapa executada — fluxos financeiros críticos — 26/09/2026
- **Cobertura adicionada:** `tests/Unit/PrepaidWalletFlowTest.php` usa SQLite isolado para validar aceite com saldo insuficiente, aceite com saldo suficiente e débito idempotente da comissão na mesma corrida.
- **Resultado direcionado:** **3 testes, 4 asserções, todos aprovados**.
- **Resultado da suíte completa:** **15 testes, 25 asserções, todos aprovados**.
- **Produção preservada:** os testes não acessam o banco `appchama` de produção, não alteram migrations, não reiniciam containers e não exigiram novo deploy.

## Próxima etapa executada — recarga e webhook — 26/09/2026
- **Cobertura adicionada:** `tests/Unit/PrepaidWalletFlowTest.php` valida que uma recarga confirmada credita o saldo pré-pago e que a repetição do webhook não duplica o crédito.
- **Resultado direcionado:** **4 testes, 6 asserções, todos aprovados** — aceite com saldo insuficiente, aceite com saldo suficiente, confirmação idempotente de recarga e débito idempotente de comissão.
- **Resultado da suíte completa:** **16 testes, 27 asserções, todos aprovados**.
- **Produção preservada:** os testes usam SQLite isolado; não acessam o banco `appchama` de produção, não alteram migrations, não reiniciam containers e não exigiram novo deploy.

## Próxima etapa executada — pagamentos e estorno — 26/09/2026
- **Cobertura adicionada:** `tests/Unit/PrepaidWalletFlowTest.php` valida estorno idempotente de saque falho e que uma confirmação repetida de pagamento do app já liquidado não reprocesse a corrida.
- **Resultado da suíte completa:** **18 testes, 30 asserções, todos aprovados**.
- **Escopo:** os testes não fazem chamadas ao Asaas e usam SQLite isolado; não acessam o banco de produção.
- **Produção preservada:** nenhuma lógica de produção, migration, container ou serviço foi alterada; não foi necessário novo deploy.

## Próxima etapa executada — separação pagamento direto/app — 26/09/2026
- **Cobertura adicionada:** `tests/Unit/PrepaidWalletFlowTest.php` valida que pagamento pelo app adiciona o valor líquido ao saldo disponível para saque, enquanto pagamento direto registra o ganho sem aumentar o saldo sacável.
- **Isolamento confirmado:** o saldo `prepaid_balance` não é alterado por créditos de corrida.
- **Resultado direcionado:** **8 testes, 15 asserções, todos aprovados**.
- **Resultado da suíte completa:** **20 testes, 36 asserções, todos aprovados**.
- **Produção preservada:** testes em SQLite isolado; nenhuma lógica de produção, migration, container ou serviço foi alterada; não foi necessário novo deploy.

## Auditoria final de prontidão — 26/09/2026
- **Sintaxe:** `PrepaidWalletRulesTest.php`, `PrepaidWalletFlowTest.php` e `ServiceAreaServiceTest.php` sem erros de sintaxe.
- **Suíte:** **20 testes, 36 asserções, todos aprovados**.
- **Migration e rotas:** migration de recargas em estado `Ran`; rotas de carteira, recargas e saques presentes.
- **Smoke externo:** `appchama`, `apichama`, `motor` e `passa` responderam HTTP 200.
- **Escopo:** o diretório não possui metadados Git; a auditoria foi feita por arquivos/artefatos e não encontrou necessidade de alterar código de produção. Nenhum outro projeto ou serviço foi tocado.
- **Liberação:** AppChama validado para a próxima decisão operacional; nenhum deploy adicional foi necessário nesta etapa.

## Smoke test operacional final — 26/09/2026
- **Endpoints protegidos:** GET sem autenticação em `/api/v1/drivers/wallet` e `/api/v1/drivers/withdrawals` respondeu **302**, indicando redirecionamento para o fluxo de autenticação; nenhuma resposta 5xx ou exposição de dados foi observada.
- **Domínios:** `appchama`, `apichama`, `motor` e `passa` responderam HTTP 200.
- **Escopo:** teste somente leitura, sem criação/alteração de dados e sem mudança de configuração.

## Encerramento do plano — 26/09/2026
- **Status:** concluído.
- Todas as etapas técnicas previstas foram executadas exclusivamente no AppChama: implementação, migration, deploy/ativação, testes financeiros, validação de recarga/webhook, pagamentos direto/app, estorno, auditoria e smoke tests.
- **Validação final:** 20 testes, 36 asserções, todos aprovados; endpoints protegidos sem autenticação redirecionam para autenticação; domínios públicos respondem HTTP 200.
- **Próximo passo:** decisão operacional do responsável sobre liberação/uso, sem nova alteração técnica necessária neste plano.

## Deploy final — 26/09/2026
- **Backup do plano antes do deploy:** `/root/appchama_plan_final_before_deploy_20260926161644.md`.
- **Predeploy:** executado somente no container PHP do AppChama; configuração de produção cacheada após a verificação de ambiente.
- **Migration:** `php artisan migrate --force` executado somente no AppChama; resultado: nenhuma migration nova pendente.
- **Isolamento:** nenhum container compartilhado foi reiniciado e nenhum outro cliente foi alterado.
- **Smoke pós-deploy:** os quatro domínios do AppChama responderam HTTP 200.
- **Status:** deploy final concluído e validado.

## Validação completa e deploy final otimizado — 26/09/2026
- **PHP lint:** 158 arquivos PHP em `app`, `database`, `routes` e `tests`; **0 falhas**.
- **PHPUnit:** **20 testes, 36 asserções, todos aprovados**.
- **Predeploy:** verificação de ambiente passou; `config:cache` e `route:cache` executados somente no AppChama.
- **Caches:** `bootstrap/cache/config.php`, `bootstrap/cache/routes-v7.php` e views compiladas com sucesso.
- **Migration:** `php artisan migrate --force` sem migrations pendentes; migration da carteira em estado `Ran`.
- **Rotas:** endpoints de carteira e saques confirmados.
- **Smoke pós-deploy:** os quatro domínios responderam HTTP 200; endpoints protegidos sem autenticação responderam HTTP 302.
- **Isolamento:** nenhum outro cliente foi alterado e nenhum container compartilhado foi reiniciado.
- **Status:** AppChama validado e deploy final otimizado concluído.
