#!/usr/bin/env bash
#
# Turn on HTTPS for this installation's domain with a Cloudflare Origin Certificate.
#
#   sudo bash deploy/enable-https.sh      (domain: APP_URL in .env, or DOMAIN=…)
#
# You'll be asked to paste the Origin Certificate and the Private Key from
# Cloudflare (SSL/TLS -> Origin Server -> Create Certificate). If anything
# fails, nginx is put back exactly as it was.

set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# The domain: DOMAIN=…, else the host of APP_URL in .env.
DOMAIN="${DOMAIN:-$(sed -n 's#^APP_URL="\{0,1\}https\{0,1\}://\([^/":]*\).*#\1#p' "$APP_DIR/.env" 2>/dev/null | head -n1)}"
[[ -n "$DOMAIN" && "$DOMAIN" =~ [a-zA-Z] ]] || { echo "Set the domain: DOMAIN=panel.example.com sudo -E bash $0 (APP_URL in .env has none)." >&2; exit 1; }
PHP_SOCK="$(ls /run/php/php*-fpm.sock 2>/dev/null | sort -V | tail -n1)"
[[ -n "$PHP_SOCK" ]] || { echo "No PHP-FPM socket in /run/php — is php-fpm installed and running?" >&2; exit 1; }
render_site() { # render_site CERT KEY > file
    sed -e "s#__DOMAIN__#$DOMAIN#g" -e "s#__APP_DIR__#$APP_DIR#g" -e "s#__PHP_SOCK__#$PHP_SOCK#g" \
        -e "s#__CERT__#$1#g" -e "s#__KEY__#$2#g" "$APP_DIR/deploy/nginx/https-site.conf.template"
}
CERT_DIR="/etc/ssl/cloudflare"
CERT="$CERT_DIR/$DOMAIN.pem"
KEY="$CERT_DIR/$DOMAIN.key"
SITE_AVAIL="/etc/nginx/sites-available/$DOMAIN.conf"
SITE_ENABLED="/etc/nginx/sites-enabled/$DOMAIN.conf"

if [[ $EUID -ne 0 ]]; then
    echo "Run with sudo:  sudo bash $0" >&2
    exit 1
fi

read_block() {
    # Read a pasted PEM block until its END line.
    local label="$1" end="$2" out="" line
    echo
    echo "Paste the $label (from -----BEGIN to -----END), then press Enter:"
    while IFS= read -r line; do
        out+="$line"$'\n'
        [[ "$line" == *"$end"* ]] && break
    done
    printf '%s' "$out"
}

TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

read_block "Origin Certificate" "-----END CERTIFICATE-----" > "$TMP/cert.pem"
read_block "Private Key" "PRIVATE KEY-----" > "$TMP/key.pem"

echo
echo "Checking the certificate and key..."
openssl x509 -in "$TMP/cert.pem" -noout >/dev/null 2>&1 || { echo "That doesn't look like a certificate. Nothing was changed." >&2; exit 1; }
openssl pkey -in "$TMP/key.pem" -noout >/dev/null 2>&1 || { echo "That doesn't look like a private key. Nothing was changed." >&2; exit 1; }

if [[ "$(openssl x509 -in "$TMP/cert.pem" -noout -pubkey | openssl sha256)" != "$(openssl pkey -in "$TMP/key.pem" -pubout | openssl sha256)" ]]; then
    echo "The certificate and the private key don't belong together. Nothing was changed." >&2
    exit 1
fi

openssl x509 -in "$TMP/cert.pem" -noout -ext subjectAltName 2>/dev/null | grep -qF -e "$DOMAIN" -e "*.${DOMAIN#*.}" \
    || echo "Warning: the certificate doesn't list $DOMAIN — continuing anyway."

# Back up anything we're about to replace.
BACKUP="/root/https-backup-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP"
[[ -e "$CERT" ]] && cp -a "$CERT" "$BACKUP/"
[[ -e "$KEY" ]] && cp -a "$KEY" "$BACKUP/"
[[ -e "$SITE_AVAIL" ]] && cp -a "$SITE_AVAIL" "$BACKUP/"
HAD_LINK=0; [[ -L "$SITE_ENABLED" ]] && HAD_LINK=1

rollback() {
    echo "Something went wrong — restoring the previous setup." >&2
    [[ -e "$BACKUP/$DOMAIN.conf" ]] && cp -a "$BACKUP/$DOMAIN.conf" "$SITE_AVAIL" || rm -f "$SITE_AVAIL"
    [[ $HAD_LINK -eq 0 ]] && rm -f "$SITE_ENABLED"
    [[ -e "$BACKUP/$DOMAIN.pem" ]] && cp -a "$BACKUP/$DOMAIN.pem" "$CERT"
    [[ -e "$BACKUP/$DOMAIN.key" ]] && cp -a "$BACKUP/$DOMAIN.key" "$KEY"
    nginx -t >/dev/null 2>&1 && systemctl reload nginx
    exit 1
}

install -d -m 755 "$CERT_DIR"
install -m 644 "$TMP/cert.pem" "$CERT"
install -m 600 "$TMP/key.pem" "$KEY"

render_site "$CERT" "$KEY" > "$SITE_AVAIL" && chmod 644 "$SITE_AVAIL"
ln -sfn "$SITE_AVAIL" "$SITE_ENABLED"

echo "Testing nginx configuration..."
nginx -t || rollback
systemctl reload nginx || rollback

if command -v ufw >/dev/null && ufw status | grep -q "Status: active"; then
    ufw allow 443/tcp >/dev/null && echo "Firewall: port 443 opened."
fi

echo
echo "Local HTTPS check:"
curl -sk -o /dev/null -w "  https://$DOMAIN (on this server) -> HTTP %{http_code}\n" --resolve "$DOMAIN:443:127.0.0.1" "https://$DOMAIN/login" || true

cat <<MSG

Done. HTTPS is enabled on this server. Backup of anything replaced: $BACKUP

Last step, in Cloudflare:
  SSL/TLS -> Overview        -> set mode to "Full (strict)"
  SSL/TLS -> Edge Certificates -> "Always Use HTTPS" = On
MSG
