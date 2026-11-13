# Alterações do Chama — 26/09/2026

## Escopo
Alterações aplicadas somente nos clientes estáticos e backend do Chama. Nenhum outro app, container ou serviço da VPS foi alterado.

## Implementado
- **Oferta do motorista:** a API retorna `gross_fare` e `driver_net_amount` antes da aceitação; o valor exibido é o líquido de 85% (desconto de 15%).
- **Card da chamada:** o valor líquido aparece em destaque (`Você recebe (85%)`) acima dos botões.
- **Alertas:** três variações de frequência e volume elevado, mantendo vibração e notificação do navegador.
- **Passageiro:** a estimativa e o card da corrida ativa usam **Valor cheio da corrida** e **Valor cheio**, priorizando `final_fare` quando disponível.
- **Mapa:** a implementação existente foi preservada; ela já desenha embarque, destino, motorista/passageiro e a rota conforme o status.

## Arquivos alterados
- `app/Domains/Rides/Services/RideTrackingService.php`
- `../appchama-motorista/_expo/static/js/web/index-6ad17a0d8324669b53b1b12d81d2efa0.js`
- `../appchama-passageiro/_expo/static/js/web/index-6a5c077c00b751342599e5d1c0dd4bf1.js`

Backups dos três arquivos foram criados com sufixo de data/hora no mesmo diretório antes das alterações.
