#!/usr/bin/env bash
#
# Turn on HTTPS for noc.sunlitnetwork.com with a Cloudflare Origin Certificate.
#
#   sudo bash /var/www/olt-panel/deploy/enable-https.sh
#
# You'll be asked to paste the Origin Certificate and the Private Key from
# Cloudflare (SSL/TLS -> Origin Server -> Create Certificate). If anything
# fails, nginx is put back exactly as it was.

set -euo pipefail

DOMAIN="noc.sunlitnetwork.com"
APP_DIR="/var/www/olt-panel"
CERT_DIR="/etc/ssl/cloudflare"
CERT="$CERT_DIR/$DOMAIN.pem"
KEY="$CERT_DIR/$DOMAIN.key"
SITE_SRC="$APP_DIR/deploy/nginx/$DOMAIN.conf"
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

openssl x509 -in "$TMP/cert.pem" -noout -ext subjectAltName 2>/dev/null | grep -q "$DOMAIN\|\*\.sunlitnetwork\.com" \
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

install -m 644 "$SITE_SRC" "$SITE_AVAIL"
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

Last step, in Cloudflare (sunlitnetwork.com):
  SSL/TLS -> Overview        -> set mode to "Full (strict)"
  SSL/TLS -> Edge Certificates -> "Always Use HTTPS" = On
MSG
