# Regras (todos os computadores)
1. Um só código. Nada de copiar/forkar o app por marca.
2. Diferença entre marcas = configuração (`marcas/<apelido>/marca.json` + assets) e dados do servidor. Nunca `if (marca == ...)` espalhado no código.
3. Recurso exclusivo de uma marca = "recurso ligado por marca" (desligado nas outras).
4. Branch principal `main`. Branches curtas por tarefa (ex.: `login-whatsapp`), que entram na `main` e somem. Não existem branches longas por marca.
5. Cada versão publicada recebe uma etiqueta: `vaide55-passageiro-1.0.0`, `appchama-motorista-1.4.2`.
6. Antes de enviar: `git pull --rebase origin main`. Nunca forçar o push.
7. Nunca guardar senha, chave de assinatura (keystore), `.env` ou google-services no repositório. Esses ficam fora, com o dono.

## Configuracao da marca no backend (13/11/2026)
- `GET /api/v1/brand-config` (publico, resolve a marca pelo endereco ou por `X-Tenant-Slug`). Marca inexistente ou suspensa = 404. Nunca devolve chaves de pagamento.
- Campos da marca (tela Marcas, so a plataforma edita): nome, lema, cor principal, cores do app, logo, contatos, textos legais, recursos.
- Logos servidas pelo backend: `api/public/brands/<marca>/logo.png`.
- Identidade de cada marca: `marcas/<marca>/IDENTIDADE.md` + `marca.json` + `assets/`.
- Teste: `api/scripts/verifica-isolamento.php` (secao 11) e `verifica-telas.php`.
