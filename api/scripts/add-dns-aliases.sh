#!/bin/sh
set -e

ZONE=/etc/bind/zones/db.agilizeerp.com.br
cp "$ZONE" "$ZONE.bak-appchama-aliases-$(date +%Y%m%d%H%M%S)"

sed -i 's/2026082802 ; serial/2026090201 ; serial/' "$ZONE"

if ! grep -q '^passageiro IN' "$ZONE"; then
  cat >> "$ZONE" <<'EOF'
passageiro IN A 69.169.97.213
driver IN A 69.169.97.213
chama IN A 69.169.97.213
EOF
fi

named-checkzone agilizeerp.com.br "$ZONE"
systemctl reload bind9 2>/dev/null || rndc reload

cat > /opt/projeto-web/clients/appchama-passageiro.vps.caddy <<'CADDY'
passa.agilizeerp.com.br, passageiro.agilizeerp.com.br {
    root * /srv/sites/clients/appchama-passageiro
    encode gzip
    try_files {path} /index.html
    file_server
}
CADDY

cat > /opt/projeto-web/clients/appchama-motorista.vps.caddy <<'CADDY'
motor.agilizeerp.com.br, driver.agilizeerp.com.br {
    root * /srv/sites/clients/appchama-motorista
    encode gzip
    try_files {path} /index.html
    file_server
}
CADDY

cat > /opt/projeto-web/clients/appchama-agilizeerp.vps.caddy <<'CADDY'
appchama.agilizeerp.com.br, chama.agilizeerp.com.br {
    root * /srv/sites/clients/appchama-agilizeerp/public
    encode gzip
    try_files {path} {path}/ /index.php?{query}
    php_fastcgi php:9000
    file_server
}

apichama.agilizeerp.com.br {
    root * /srv/sites/clients/appchama-agilizeerp/public
    encode gzip
    try_files {path} {path}/ /index.php?{query}
    php_fastcgi php:9000
    file_server
}
CADDY

docker compose -f /opt/projeto-web/docker-compose.vps.yml exec -T caddy caddy reload --config /etc/caddy/Caddyfile \
  || docker compose -f /opt/projeto-web/docker-compose.vps.yml restart caddy

echo "Aliases configured OK"
