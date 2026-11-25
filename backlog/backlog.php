<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Vai de 55 — Backlog</title>
<style>
:root{--ink:#111;--bg:#fff;--mut:#555;--line:#e3e3e3;--amber:#FCD200;--soft:#fafafa}
@media(prefers-color-scheme:dark){:root{--ink:#eee;--bg:#161616;--mut:#aaa;--line:#333;--soft:#1e1e1e}}
body{margin:0;background:var(--bg);color:var(--ink);font:16px/1.55 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
header{background:#111;border-bottom:4px solid var(--amber);padding:14px 16px}
header b{color:var(--amber);font-size:18px}
main{max-width:960px;margin:0 auto;padding:8px 16px 48px}
h1{font-size:1.7rem;margin:1.2rem 0 .4rem}h2{margin-top:2.2rem;border-bottom:2px solid var(--amber);padding-bottom:4px}h3{margin-top:1.6rem}
.wrap{overflow-x:auto}table{border-collapse:collapse;width:100%;font-size:.92rem;margin:.8rem 0}
th,td{border:1px solid var(--line);padding:7px 9px;text-align:left;vertical-align:top}th{background:var(--soft)}
em{color:var(--mut)}
</style></head><body>
<header><b>Vai de 55</b></header><main>
<h1>Vai de 55 — Recursos dos apps Passageiro e Motorista</h1>
<p><em>Versão do levantamento: 25/11/2026 · Passageiro v16 · Motorista v14 (teste interno do Google Play) · Cidade-base: Encantado/RS</em></p>
<p>Este documento lista o que <strong>já existe e funciona hoje</strong> em cada app, como o sistema se comporta por trás, e o que ainda <strong>falta decidir ou implementar antes do lançamento</strong>. Foi montado a partir do código-fonte; nada disso passou ainda por teste real com usuários.</p>
<p>Legenda: ✅ pronto · ⚠️ parcial / depende de configuração · ❌ não existe</p>
<h2>1. Visão geral</h2>
<div class="wrap"><table>
<thead>
<tr>
<th>Peça</th>
<th>O que é</th>
</tr>
</thead>
<tbody>
<tr>
<td>App Passageiro</td>
<td>Android. Pede e acompanha a corrida, paga.</td>
</tr>
<tr>
<td>App Motorista</td>
<td>Android. Recebe ofertas, executa a corrida, controla carteira e saques.</td>
</tr>
<tr>
<td>Servidor (API)</td>
<td>Único para os dois apps e para várias marcas (white-label).</td>
</tr>
<tr>
<td>Painel administrativo</td>
<td>Quem opera o negócio: motoristas, corridas, pagamentos, saques, cidades e tarifas.</td>
</tr>
<tr>
<td>Pagamentos</td>
<td>Asaas (Pix e cartão). Hoje a conta é de <strong>teste (sandbox)</strong>.</td>
</tr>
<tr>
<td>Mapas e endereços</td>
<td>Mapbox (rota/preço) e Google Places (busca de endereço).</td>
</tr>
<tr>
<td>Avisos</td>
<td>Notificação push; no motorista, som e vibração repetidos até responder.</td>
</tr>
</tbody>
</table></div>
<p><strong>iOS:</strong> não existe. Só Android por enquanto.</p>
<h2>2. App do Passageiro</h2>
<h3>2.1 Funcionalidades</h3>
<div class="wrap"><table>
<thead>
<tr>
<th>Recurso</th>
<th>Status</th>
<th>Como funciona</th>
</tr>
</thead>
<tbody>
<tr>
<td>Entrar com celular + código SMS</td>
<td>⚠️</td>
<td>Código de 6 dígitos. O envio real de SMS <strong>ainda não está ligado</strong> (falta conta no fornecedor, ex.: Twilio). Hoje o código só funciona para contas de teste.</td>
</tr>
<tr>
<td>GPS obrigatório</td>
<td>✅</td>
<td>O app bloqueia o uso até a localização estar ligada e permitida; reconfere sozinho e tem botões para ativar.</td>
</tr>
<tr>
<td>Mapa e posição atual</td>
<td>✅</td>
<td>Origem preenchida pelo GPS; botão "Recentralizar".</td>
</tr>
<tr>
<td>Escolher destino</td>
<td>✅</td>
<td>Busca de endereço com sugestões; limitada à área de atendimento da cidade.</td>
</tr>
<tr>
<td>Valor estimado antes de pedir</td>
<td>✅</td>
<td>Mostra o valor com base em distância e tempo da rota.</td>
</tr>
<tr>
<td>Pedir corrida</td>
<td>✅</td>
<td>Botão "Pedir corrida"; o sistema começa a procurar motorista.</td>
</tr>
<tr>
<td>Acompanhar a corrida</td>
<td>✅</td>
<td>Situação (procurando, a caminho, chegou, em viagem), cartão do motorista (nome, nota, veículo, placa) e posição dele no mapa em tempo real.</td>
</tr>
<tr>
<td>Cancelar corrida</td>
<td>✅</td>
<td>Disponível enquanto a corrida não terminou. Sem taxa de cancelamento (a regra não existe).</td>
</tr>
<tr>
<td>Motorista terminando outra corrida</td>
<td>✅</td>
<td>Se o motorista aceitou a sua corrida ainda com outro passageiro no carro (corrida encadeada), o app avisa que ele está terminando outra corrida e vem em seguida.</td>
</tr>
<tr>
<td>Sem motorista disponível</td>
<td>✅</td>
<td>Após cerca de 3 minutos sem aceite, avisa e oferece pedir outra corrida.</td>
</tr>
<tr>
<td>Pagamento</td>
<td>✅</td>
<td>Pix (QR + copia-e-cola), cartão (janela segura do Asaas) ou <strong>pagar direto ao motorista</strong> (dinheiro/Pix pessoal). Ver seção 4.</td>
</tr>
<tr>
<td>Reaproveita cadastro no pagamento</td>
<td>✅</td>
<td>Pede só o que falta (nome, CPF, e-mail, endereço) e guarda para as próximas corridas.</td>
</tr>
<tr>
<td>Avaliar motorista</td>
<td>✅</td>
<td>Nota de 1 a 5 e comentário, só em corridas pagas e concluídas.</td>
</tr>
<tr>
<td>Resumo da corrida</td>
<td>✅</td>
<td>Tela final com valores e trajeto.</td>
</tr>
<tr>
<td>Histórico de corridas</td>
<td>✅</td>
<td>Lista das corridas anteriores.</td>
</tr>
<tr>
<td>Aviso de nova versão</td>
<td>✅</td>
<td>O app avisa quando há atualização disponível.</td>
</tr>
<tr>
<td>Notificações push</td>
<td>✅</td>
<td>Mudanças de situação da corrida (aceita, chegou, etc.).</td>
</tr>
<tr>
<td>Endereços salvos (casa/trabalho)</td>
<td>⚠️</td>
<td>O servidor já tem a função; <strong>não há tela no app</strong>.</td>
</tr>
<tr>
<td>Ajuda e suporte dentro do app</td>
<td>✅</td>
<td>Aba <strong>Ajuda</strong>: abre chamado escolhendo o assunto (corrida, pagamento, denúncia/segurança, conta, app, outro), pode ligar o chamado a uma corrida recente e conversa com a equipe. Quando a equipe responde, chega aviso no celular e aparece uma bolinha na aba. Mostra também WhatsApp, telefone e e-mail da marca (assim que cadastrados no painel).</td>
</tr>
<tr>
<td>Adicional "por fora" (parada, espera, distância a mais)</td>
<td>✅</td>
<td>O motorista pede o adicional durante a viagem; o passageiro vê o valor e o motivo, <strong>concorda ou não concorda</strong>. Se não concordar, a equipe recebe um chamado automático. O valor é pago direto ao motorista e <strong>não entra</strong> no preço da corrida nem no pagamento do app.</td>
</tr>
<tr>
<td>Chat/ligação com o motorista</td>
<td>❌</td>
<td>Não existe.</td>
</tr>
<tr>
<td>Compartilhar viagem / botão SOS</td>
<td>❌</td>
<td>Não existe.</td>
</tr>
<tr>
<td>Agendar corrida, cupom/desconto, favoritos</td>
<td>❌</td>
<td>Não existe.</td>
</tr>
<tr>
<td>Recibo por e-mail/PDF</td>
<td>❌</td>
<td>Não existe.</td>
</tr>
</tbody>
</table></div>
<h3>2.2 Fluxo resumido</h3>
<ol>
<li>Entra com celular + código SMS</li>
<li>Define o destino e vê o valor</li>
<li>Pede a corrida</li>
<li>Motorista aceita, chega e inicia</li>
<li>Corrida termina</li>
<li>Paga</li>
<li>Avalia</li>
</ol>
<h2>3. App do Motorista</h2>
<h3>3.1 Funcionalidades</h3>
<div class="wrap"><table>
<thead>
<tr>
<th>Recurso</th>
<th>Status</th>
<th>Como funciona</th>
</tr>
</thead>
<tbody>
<tr>
<td>Cadastro de motorista</td>
<td>✅</td>
<td>Nome, telefone, senha, CNH (número, validade e <strong>foto do documento</strong>), veículo (placa, modelo, cor, ano) e cidade. Só abre se a cidade aceitar cadastros.</td>
</tr>
<tr>
<td>Aprovação</td>
<td>✅</td>
<td>Após cadastrar, fica <strong>pendente</strong>. O motorista recebe um <strong>código de 6 dígitos</strong> e o confirma no app; a equipe valida o código e aprova no painel (ou rejeita). Tela "aguardando aprovação" enquanto isso.</td>
</tr>
<tr>
<td>Login</td>
<td>✅</td>
<td>Telefone + senha.</td>
</tr>
<tr>
<td>GPS obrigatório</td>
<td>✅</td>
<td>Mesmo bloqueio do passageiro.</td>
</tr>
<tr>
<td>Painel do motorista</td>
<td>✅</td>
<td>Ficar online/offline; ganhos de hoje; corridas de hoje; saldo da carteira (um só, para operar e para sacar).</td>
</tr>
<tr>
<td>Localização em segundo plano</td>
<td>✅</td>
<td>Continua enviando a posição com o app minimizado, para receber ofertas.</td>
</tr>
<tr>
<td>Receber oferta de corrida</td>
<td>✅</td>
<td>Tela com origem, destino e valor. <strong>45 segundos</strong> para aceitar; som e vibração repetidos até responder. Pode recusar.</td>
</tr>
<tr>
<td>Aceitar só com crédito</td>
<td>✅</td>
<td>Antes da primeira recarga, precisa de <strong>R$ 20,00</strong> na carteira. Depois da primeira recarga pode operar até <strong>R$ 20,00 negativo</strong> (ver seção 5). Abaixo disso, o app pede recarga.</td>
</tr>
<tr>
<td>Corrida ativa</td>
<td>✅</td>
<td>Botões: <strong>Cheguei</strong>, <strong>Iniciar</strong>, <strong>Finalizar</strong>; mapa com rota e faixa de navegação; pode cancelar.</td>
</tr>
<tr>
<td>Cancelar antes de iniciar</td>
<td>✅</td>
<td>A corrida do passageiro <strong>não morre</strong>: volta a procurar outro motorista, sem repetir quem cancelou.</td>
</tr>
<tr>
<td>Receber o pagamento</td>
<td>✅</td>
<td>Tela "Aguardando pagamento" e depois "Pagamento recebido". Se o passageiro pagar direto, o motorista usa <strong>"Confirmar recebimento (direto)"</strong>.</td>
</tr>
<tr>
<td>Carteira</td>
<td>✅</td>
<td><strong>Carteira única</strong>: saldo, regras de uso, total ganho e histórico de movimentos.</td>
</tr>
<tr>
<td>Recarregar a carteira por Pix</td>
<td>✅</td>
<td>Valor mínimo <strong>R$ 20,00</strong>; gera Pix e o crédito entra quando o pagamento confirma.</td>
</tr>
<tr>
<td>Saque por Pix</td>
<td>✅</td>
<td>Cadastra a chave Pix na carteira e solicita saque (mínimo <strong>R$ 50,00</strong>, até o valor do saldo; um saque por vez). Se falhar, o valor volta para a carteira.</td>
</tr>
<tr>
<td>Histórico de corridas</td>
<td>✅</td>
<td>Lista "Minhas corridas".</td>
</tr>
<tr>
<td>Relatório de ganhos</td>
<td>✅</td>
<td>Gerado pelo servidor.</td>
</tr>
<tr>
<td>Aviso de nova versão / push</td>
<td>✅</td>
<td>Igual ao passageiro.</td>
</tr>
<tr>
<td>Mensalidade do motorista</td>
<td>⚠️</td>
<td>Existem os campos de situação da mensalidade e dos antecedentes no painel, mas a cobrança/verificação é <strong>manual</strong>.</td>
</tr>
<tr>
<td>Ajuda e suporte dentro do app</td>
<td>✅</td>
<td>Botão no painel: abre chamado (corrida, crédito/recebimento/saque, denúncia sobre passageiro, cadastro, app, outro), conversa com a equipe e recebe aviso quando respondem.</td>
</tr>
<tr>
<td>Cobrar adicional (parada / espera)</td>
<td>✅</td>
<td>Na viagem em andamento, o motorista pede um adicional com valor e motivo (limite de R$ 200 por pedido e 5 por corrida). O passageiro precisa concordar. O valor é recebido direto do passageiro, fora do app.</td>
</tr>
<tr>
<td>Aceitar próxima corrida com passageiro no carro</td>
<td>✅</td>
<td>Depois de <strong>20% do trajeto</strong> concluído, o motorista pode receber e aceitar <strong>uma</strong> próxima corrida, com embarque perto do destino atual. A nova corrida só começa quando ele finalizar a atual, e o app leva direto para ela.</td>
</tr>
<tr>
<td>Chat, documentos extras (CRLV etc.)</td>
<td>❌</td>
<td>Não existem no app.</td>
</tr>
</tbody>
</table></div>
<h3>3.2 Fluxo resumido</h3>
<p>Cadastro → código de cadastro → aprovação da equipe → recarrega crédito → fica online → recebe oferta (45 s) → aceita → cheguei → inicia → finaliza → recebe (Pix/cartão pelo sistema ou direto) → saca quando quiser.</p>
<h2>4. Como o sistema trabalha por trás</h2>
<h3>4.1 Como a corrida é cobrada</h3>
<p><strong>Passo a passo do cálculo (tudo configurado por cidade, no painel):</strong></p>
<ol>
<li><strong>Bandeirada:</strong> valor fixo cobrado no início de toda corrida.</li>
<li><strong>Distância:</strong> quilômetros da rota × valor do km. O valor do km pode ser <strong>menor nas viagens longas</strong> (faixas, abaixo).</li>
<li><strong>Tempo:</strong> minutos de viagem × valor por minuto.</li>
<li><strong>Tarifa mínima:</strong> se a soma ficar abaixo do mínimo da cidade, cobra-se o mínimo.</li>
</ol>
<p><strong>Preço fechado:</strong> o passageiro vê o valor <strong>antes de pedir</strong> a corrida e paga exatamente esse valor. O app não recalcula no fim pelo trajeto percorrido (só recalcula se a corrida, por algum motivo, não tiver estimativa).</p>
<p><strong>Km mais barato em viagens longas (opcional, por cidade):</strong> o painel permite criar faixas do tipo "a partir do km X, cada km passa a custar R$ Y". Cada km é cobrado pelo valor da faixa onde ele cai (cálculo progressivo, como imposto de renda). Por isso <strong>não existe degrau</strong>: o preço total só aumenta com a distância, mas o km médio fica mais barato nas viagens longas.</p>
<p>Exemplo de configuração: valor do km R$ 4,00; a partir do km 5, R$ 3,50; a partir do km 10, R$ 3,00 (sem bandeirada nem minutos, para facilitar a conta):</p>
<div class="wrap"><table>
<thead>
<tr>
<th>Viagem</th>
<th>Conta</th>
<th>Valor</th>
<th>Km médio</th>
</tr>
</thead>
<tbody>
<tr>
<td>3 km</td>
<td>3 × 4,00</td>
<td>R$ 12,00</td>
<td>R$ 4,00</td>
</tr>
<tr>
<td>5 km</td>
<td>5 × 4,00</td>
<td>R$ 20,00</td>
<td>R$ 4,00</td>
</tr>
<tr>
<td>8 km</td>
<td>5 × 4,00 + 3 × 3,50</td>
<td>R$ 30,50</td>
<td>R$ 3,81</td>
</tr>
<tr>
<td>12 km</td>
<td>5 × 4,00 + 5 × 3,50 + 2 × 3,00</td>
<td>R$ 43,50</td>
<td>R$ 3,63</td>
</tr>
</tbody>
</table></div>
<p>Sem faixas cadastradas, o valor do km é o mesmo em toda a viagem. Os valores das faixas <strong>ainda precisam ser definidos com o cliente</strong> e são lançados no painel, sem atualizar o aplicativo.</p>
<p><strong>O que fica fora desse cálculo:</strong>
- O <strong>adicional "por fora"</strong> (parada, espera, distância a mais) combinado durante a viagem: é pago direto ao motorista e não entra no preço da corrida (ver 4.5).
- A <strong>comissão da plataforma</strong> é um percentual aplicado sobre o valor da corrida (não sobre o adicional), conforme a seção 5.</p>
<h3>4.2 Busca de motorista</h3>
<ul>
<li>O sistema oferece a corrida a <strong>um motorista por vez</strong>, entre os online e disponíveis, com 45 segundos para responder. Motorista ocupado <strong>não</strong> recebe oferta, exceto na regra da corrida encadeada (4.4).</li>
<li>Recusou ou deixou passar: passa para o próximo, sem repetir.</li>
<li>Tenta por até <strong>3 minutos</strong>; sem ninguém, a corrida vira "sem motorista disponível".</li>
</ul>
<h3>4.3 Situações de uma corrida</h3>
<p>Solicitada → Procurando → Aceita → Motorista chegou → Em viagem → Aguardando pagamento → Concluída (paga). Alternativas: cancelada pelo passageiro, cancelada pelo motorista, sem motorista disponível.</p>
<h3>4.4 Corrida encadeada (próxima corrida com passageiro no carro)</h3>
<ul>
<li>O motorista com passageiro no carro entra na fila de ofertas quando já fez <strong>20% do trajeto</strong> (medido em linha reta, da origem ao destino) e <strong>não tem outra corrida na fila</strong>.</li>
<li>Motoristas livres sempre vêm primeiro; o encadeado só é oferecido se o embarque da nova corrida estiver a até <strong>5 km</strong> do destino da corrida atual.</li>
<li>Ao aceitar, a nova corrida fica <strong>na fila</strong>: o motorista não pode chegar nem iniciar a nova antes de finalizar a atual. Se o passageiro da fila cancelar, o motorista segue normalmente com a atual.</li>
<li>O percentual (padrão 20%), a distância (padrão 5 km) e a liberação da regra são ajustados <strong>por cidade no painel</strong>, sem atualizar o aplicativo.</li>
</ul>
<h3>4.5 Adicional "por fora"</h3>
<ul>
<li>Só durante a viagem em andamento. O passageiro responde "concordo" ou "não concordo"; ao discordar, abre-se um chamado automático ligado à corrida para a equipe analisar.</li>
<li>Fica registrado (motivo, valor, resposta), mas <strong>não soma</strong> no valor da corrida, no Pix/cartão nem na comissão da plataforma.</li>
</ul>
<h3>4.6 Pagamento do passageiro</h3>
<div class="wrap"><table>
<thead>
<tr>
<th>Forma</th>
<th>O que acontece</th>
</tr>
</thead>
<tbody>
<tr>
<td>Pix</td>
<td>Gera QR/copia-e-cola no Asaas; o aviso automático do Asaas (webhook) confirma.</td>
</tr>
<tr>
<td>Cartão</td>
<td>Abre a janela segura do Asaas; confirmação por webhook e por conferência automática a cada 10 min.</td>
</tr>
<tr>
<td>Direto ao motorista</td>
<td>Liberado por marca; o motorista confirma o recebimento e a corrida termina.</td>
</tr>
</tbody>
</table></div>
<p>Pagamentos pendentes há mais de 3 dias são cancelados automaticamente. <strong>Hoje a conta Asaas é de teste (sandbox)</strong>: Pix e cartão reais não concluem; para testar, use "Pagar ao motorista" ou cartão de teste do Asaas.</p>
<h2>5. Dinheiro: comissão, crédito e saque</h2>
<ul>
<li>A plataforma fica com <strong>15% de cada corrida</strong> (percentual por marca, configurado no painel). O restante é do motorista.</li>
<li>O motorista tem uma <strong>carteira única</strong>. Para começar a aceitar corridas precisa de <strong>R$ 20,00</strong> de saldo. Depois da primeira recarga, o saldo pode ficar até <strong>R$ 20,00 negativo</strong>; abaixo disso ele recarrega para voltar a aceitar.</li>
<li><strong>Pix ou cartão pelo app:</strong> os 15% ficam retidos e os 85% caem na carteira. <strong>Pagamento direto ao motorista</strong> (dinheiro/Pix na hora): ele fica com o valor e os 15% são descontados da carteira.</li>
<li>A carteira recebe saldo por <strong>recarga por Pix</strong> (mín. R$ 20) ou por <strong>crédito manual</strong> da equipe no painel (Motoristas → Adicionar crédito, individual ou em lote, até R$ 5.000 por lançamento).</li>
<li><strong>Saque por Pix</strong> para a chave cadastrada na carteira, com valor mínimo de <strong>R$ 50,00</strong>. Saques saem via Asaas e são conferidos automaticamente a cada 10 min.</li>
</ul>
<h2>6. Painel administrativo (equipe)</h2>
<div class="wrap"><table>
<thead>
<tr>
<th>Área</th>
<th>O que faz</th>
</tr>
</thead>
<tbody>
<tr>
<td>Cidades</td>
<td>Cadastro, área de atendimento e tarifas (base, km, minuto, mínimo); se aceita cadastro de motoristas.</td>
</tr>
<tr>
<td>Motoristas</td>
<td>Aprovar/rejeitar cadastro, validar código, ver CNH, situação de antecedentes e mensalidade, saldo de comissão, adicionar crédito.</td>
</tr>
<tr>
<td>Veículos</td>
<td>Veículos dos motoristas.</td>
</tr>
<tr>
<td>Corridas</td>
<td>Lista e detalhes de todas as corridas.</td>
</tr>
<tr>
<td>Pagamentos</td>
<td>Pagamentos das corridas e situação no Asaas.</td>
</tr>
<tr>
<td>Saques</td>
<td>Pedidos de saque e andamento.</td>
</tr>
<tr>
<td>Chamados de suporte</td>
<td>Chamados dos passageiros e motoristas com a conversa completa: a equipe responde pelo painel (o usuário recebe aviso no celular), encerra, filtra por situação e assunto, e vê um contador de chamados novos no menu.</td>
</tr>
<tr>
<td>Configuração de pagamentos</td>
<td>Chaves Asaas, formas liberadas (Pix, cartão, direto), comissão.</td>
</tr>
<tr>
<td>Marcas / usuários</td>
<td>Gestão multi-marca (white-label) e usuários do painel.</td>
</tr>
</tbody>
</table></div>
<h2>7. Situação de publicação</h2>
<ul>
<li>Google Play, <strong>teste interno</strong>: Passageiro v16 e Motorista v14 publicados.</li>
<li>Produção no Google Play exige conta pessoal com <strong>12 testadores por 14 dias seguidos</strong> de teste fechado.</li>
</ul>
<h2>8. O que falta antes de lançar</h2>
<h3>Obrigatório (bloqueia o lançamento)</h3>
<ol>
<li><strong>SMS real</strong>: contratar fornecedor (Twilio ou alternativa) e ligar; sem isso ninguém novo consegue entrar.</li>
<li><strong>Conta de produção do Asaas</strong> (hoje é sandbox) e testar Pix, cartão e saque com dinheiro real.</li>
<li><strong>Textos legais</strong>: política de privacidade, termos de uso e página de exclusão de conta — o Google Play exige; hoje estão vazios.</li>
<li><strong>Contatos de suporte</strong> da marca (WhatsApp, e-mail, telefone): o app já os mostra na aba Ajuda, mas os campos da marca ainda estão <strong>vazios</strong> e precisam ser preenchidos no painel (Marcas).</li>
<li><strong>Testes de campo</strong> com motoristas e passageiros reais.</li>
<li><strong>Cumprir os 12 testadores × 14 dias</strong> para liberar a produção no Google Play.</li>
</ol>
<h3>Recomendado (decidir se entra na primeira versão)</h3>
<div class="wrap"><table>
<thead>
<tr>
<th>Item</th>
<th>Por quê</th>
</tr>
</thead>
<tbody>
<tr>
<td>Botão SOS e compartilhar viagem</td>
<td>Segurança do passageiro.</td>
</tr>
<tr>
<td>Chat ou ligação mascarada passageiro↔motorista</td>
<td>Hoje não há contato dentro do app.</td>
</tr>
<tr>
<td>Endereços salvos (casa/trabalho)</td>
<td>Servidor pronto; só falta a tela.</td>
</tr>
<tr>
<td>Regra de taxa de cancelamento</td>
<td>Hoje cancelar não custa nada para ninguém.</td>
</tr>
<tr>
<td>Recibo da corrida</td>
<td>Passageiro e motorista não recebem comprovante.</td>
</tr>
<tr>
<td>Cobrança automática da mensalidade e checagem de antecedentes</td>
<td>Hoje é controle manual no painel.</td>
</tr>
<tr>
<td>Documentos extras do motorista (CRLV, foto do veículo)</td>
<td>Só a CNH é enviada hoje.</td>
</tr>
<tr>
<td>Versão iOS</td>
<td>Não existe; depende da decisão de público.</td>
</tr>
</tbody>
</table></div>
<h3>Para decidir com a equipe</h3>
<ul>
<li>Percentual de comissão e valores de tarifa por cidade.</li>
<li>Valor mínimo de recarga (hoje R$ 20), de saque (hoje R$ 50) e o limite negativo da carteira (hoje R$ 20).</li>
<li>Manter a carteira única com comissão de 15% ou mudar a forma de cobrança.</li>
<li>Manter a opção "pagar direto ao motorista" no lançamento.</li>
</ul>
</main></body></html>
