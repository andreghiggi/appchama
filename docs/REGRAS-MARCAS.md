# Regras (todos os computadores)
1. Um só código. Nada de copiar/forkar o app por marca.
2. Diferença entre marcas = configuração (`marcas/<apelido>/marca.json` + assets) e dados do servidor. Nunca `if (marca == ...)` espalhado no código.
3. Recurso exclusivo de uma marca = "recurso ligado por marca" (desligado nas outras).
4. Branch principal `main`. Branches curtas por tarefa (ex.: `login-whatsapp`), que entram na `main` e somem. Não existem branches longas por marca.
5. Cada versão publicada recebe uma etiqueta: `vaide55-passageiro-1.0.0`, `appchama-motorista-1.4.2`.
6. Antes de enviar: `git pull --rebase origin main`. Nunca forçar o push.
7. Nunca guardar senha, chave de assinatura (keystore), `.env` ou google-services no repositório. Esses ficam fora, com o dono.
