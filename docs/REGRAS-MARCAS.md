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

## Codigo de acesso (OTP) — seguranca (13/11/2026)
- A API **nunca** devolve o codigo na resposta em producao (`OTP_EXPOSE_DEBUG_CODE=false`). So em desenvolvimento (`true`).
- Contas de teste (revisao das lojas e testes internos): `OTP_TEST_ACCOUNTS="telefone:codigo,telefone:codigo"` no `.env` do servidor. So esses telefones tem codigo fixo (6 digitos). Nunca colocar telefone real. Os valores nao vao para o Git.
- Limites: ate 5 pedidos de codigo e 10 tentativas por telefone a cada 10 minutos (e por IP). Acima disso a API responde 429.
- SMS real ainda nao esta ligado (tarefa B1): ate la, so as contas de teste conseguem entrar.
- Teste: `api/scripts/verifica-otp.php`.
