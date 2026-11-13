# Vai de 55 — identidade e orientações da marca

> Fonte: pasta de identidade visual do dono (recebida em 13/11/2026). Tudo que é do Vai de 55 fica em `marcas/vaide55/`. Esta marca usa o **mesmo código** do AppChama; o que muda é só configuração e arquivos.

## Nome e lema
- **Nome da marca:** Vai de 55 (com espaços, "de" minúsculo). Em endereços: `vaide55`.
- **Lema:** Viu passar? Só chamar!
- **Nomes nas lojas (a confirmar com o dono):** passageiro "Vai de 55"; motorista "Vai de 55 Motorista".
- **Pacotes Android:** `com.vaide55.passageiro` e `com.vaide55.motorista` (permanentes depois de publicados).

## Cores
| Uso | Cor |
|---|---|
| Destaque (botões, ícones ativos) | `#FCD200` (amarelo do logo) |
| Fundo escuro do app e do ícone | `#111111` |
| Fundo escuro 2 (cartões) | `#1E1E1E` |
| Destaque pressionado | `#C9A700` |
| Texto sobre o amarelo | `#111111` (nunca texto branco sobre amarelo) |

Os mesmos valores estão em `marca.json` (campo `tema`) e no backend (tela Marcas). Mudou aqui, mude lá.

## Logo e arquivos (`assets/`)
- `logo-amarela-original-transparente.png` — logo principal (1536×1024, fundo transparente).
- `logo-amarela-original-fundo-branco.png` — para papel/fundo claro.
- `logo-amarela-original.pdf` — vetor/impressão.
- `icone.png` (512) e `icone-adaptativo.png` (maskable 512, fundo `#111111`) — ícone do app (o "55" em amarelo sobre preto).
- `splash.png` — tela de abertura (logo sobre `#111111`), gerada a partir do logo.
- `favicon*.ico/png`, `android-chrome-*`, `mstile-150x150.png`, `site.webmanifest`, `browserconfig.xml`, `COLE-NO-SITE.html` — kit para o **site** vaide55.com.br (copiar para a raiz do site e colar o `<head>` do `COLE-NO-SITE.html`).
- Regras: não esticar, não trocar as cores, manter margem livre ao redor; sobre fundo escuro usar o logo transparente.

## Como o app e o backend usam isso
- O backend (`api/`) guarda a identidade por marca e entrega em `GET /api/v1/brand-config` (nome, lema, logo, cores, contatos, textos legais, formas de pagamento e recursos ligados). Nunca devolve chaves de pagamento.
- A logo servida pelo backend fica em `api/public/brands/vaide55/logo.png`.
- O app lê essa configuração ao abrir (tarefa 15) e o build de cada marca usa `marca.json` + `assets/`.

## Pendências do dono
- [ ] Contatos de suporte: WhatsApp, e-mail e telefone (tela Marcas → Vai de 55).
- [ ] Textos legais: política de privacidade, termos de uso e exclusão de conta (URLs) — as lojas exigem.
- [ ] Confirmar nomes nas lojas.
- [ ] Faltam no kit: `apple-touch-icon.png` (180×180) e `compartilhamento-link-1200x630.png` (citados em `COLE-NO-SITE.html`).
- [ ] Conta Asaas própria do Vai de 55 (ou decisão de usar a da plataforma).
