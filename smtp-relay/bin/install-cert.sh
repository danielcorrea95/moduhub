#!/bin/sh
# Deploy hook do certbot para relay.moduhub.com.br: copia o certificado para onde o Haraka lê
# (config/tls_*.pem, legível pelo usuário haraka) e reinicia o relay. Roda na emissão e em cada
# renovação. Ver docs/runbooks/email.md, "Relay SMTP".
set -eu

DIR="$(cd "$(dirname "$0")/.." && pwd)"
LIVE="${RENEWED_LINEAGE:-/etc/letsencrypt/live/relay.moduhub.com.br}"

install -o root -g haraka -m 640 "$LIVE/privkey.pem" "$DIR/config/tls_key.pem"
install -o root -g haraka -m 644 "$LIVE/fullchain.pem" "$DIR/config/tls_cert.pem"

# Na primeira emissão o programa ainda pode não existir no Supervisor.
supervisorctl restart moduhub-smtp-relay || true
