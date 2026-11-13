#!/bin/bash
# Roda TODAS as verificacoes automaticas do AppChama / Vai de 55 na copia de desenvolvimento (appchama-dev).
# Uso (na VPS):  bash /root/chk/scripts/rodar-verificacoes.sh [pasta-do-repositorio]
# Tudo roda dentro de transacao e nao deixa rastro no banco de desenvolvimento.
set -u
REPO="${1:-/root/chk}"
DEV=/opt/projeto-web/sites/clients/appchama-dev
PHP=projeto-web-php-1

echo "== sincronizando api/ para o desenvolvimento"
rsync -a --exclude=vendor --exclude=node_modules --exclude=.env --exclude=storage --exclude=bootstrap/cache \
  --exclude=public/build --exclude=public/vendor --exclude=public/storage "$REPO/api/" "$DEV/"
docker exec -w /srv/sites/clients/appchama-dev $PHP php artisan migrate --force 2>&1 | tail -1
docker exec -w /srv/sites/clients/appchama-dev $PHP php artisan optimize:clear >/dev/null 2>&1

total_falhas=0
for s in verifica-isolamento verifica-telas verifica-otp verifica-cidades verifica-paineis verifica-corrida verifica-textos-marca; do
  saida=$(docker exec -w /srv/sites/clients/appchama-dev $PHP php artisan tinker scripts/$s.php 2>&1)
  resumo=$(echo "$saida" | grep -E "RESULTADO:|TELAS:|OTP:|CIDADES:|PAINEIS:|CORRIDA:|TEXTOS:" | tail -1)
  falhas=$(echo "$resumo" | grep -oE "[0-9]+ falhas" | grep -oE "[0-9]+")
  printf "%-24s %s\n" "$s" "${resumo:-SEM RESULTADO (erro ao rodar)}"
  if [ -z "$falhas" ] || [ "$falhas" != "0" ]; then
    total_falhas=$((total_falhas+1)); echo "$saida" | grep -E "FALHOU| - " | head -8
  fi
done
echo
if [ $total_falhas -eq 0 ]; then echo "TUDO OK: nenhuma falha."; else echo "ATENCAO: $total_falhas verificacao(oes) com falha."; exit 1; fi
