#!/usr/bin/env bash
#
# Turn on HTTPS for noc.sunlitnetwork.com with a free Let's Encrypt
# certificate (certbot). Renews itself; nginx reloads after each renewal.
#
#   sudo bash /var/www/olt-panel/deploy/enable-letsencrypt.sh [your-email]
#
# The email (optional) gets Let's Encrypt's expiry warnings. Running this
# means you accept the Let's Encrypt terms (https://letsencrypt.org/repository/).
#
# Plain HTTP on port 80 stays on the existing default site — the ZKTeco
# attendance devices post to it by IP, and certbot's checks use it. If
# anything fails, nginx is put back exactly as it was.

set -euo pipefail

DOMAIN="noc.sunlitnetwork.com"
APP_DIR="/var/www/olt-panel"
WEBROOT="$APP_DIR/public"
LIVE="/etc/letsencrypt/live/$DOMAIN"
SITE_SRC="$APP_DIR/deploy/nginx/$DOMAIN.conf"
SITE_AVAIL="/etc/nginx/sites-available/$DOMAIN.conf"
SITE_ENABLED="/etc/nginx/sites-enabled/$DOMAIN.conf"
EMAIL="${1:-}"

if [[ $EUID -ne 0 ]]; then
    echo "Run with sudo:  sudo bash $0 [email]" >&2
    exit 1
fi

step() { echo; echo "==> $*"; }

# 1. certbot
if ! command -v certbot >/dev/null; then
    step "Installing certbot"
    apt-get update -qq
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq certbot
fi

# 2. Can Let's Encrypt reach this server over http://$DOMAIN ?
step "Checking that http://$DOMAIN reaches this server"
CHALLENGE_DIR="$WEBROOT/.well-known/acme-challenge"
install -d -m 755 "$CHALLENGE_DIR"
PROBE="probe-$RANDOM$RANDOM"
echo "$PROBE" > "$CHALLENGE_DIR/$PROBE"
GOT="$(curl -s -m 20 "http://$DOMAIN/.well-known/acme-challenge/$PROBE" || true)"
rm -f "$CHALLENGE_DIR/$PROBE"
if [[ "$GOT" != "$PROBE" ]]; then
    cat >&2 <<MSG
http://$DOMAIN/.well-known/acme-challenge/ didn't come back to this server.
Check that the DNS record points here (or through Cloudflare, orange cloud)
and that port 80 is open. Nothing was changed.
MSG
    exit 1
fi
echo "   OK"

# 3. The certificate
step "Getting the certificate from Let's Encrypt"
if [[ -n "$EMAIL" ]]; then ACCOUNT=(--email "$EMAIL"); else ACCOUNT=(--register-unsafely-without-email); fi
certbot certonly --webroot -w "$WEBROOT" -d "$DOMAIN" \
    --non-interactive --agree-tos "${ACCOUNT[@]}" \
    --keep-until-expiring \
    --deploy-hook "systemctl reload nginx"

[[ -s "$LIVE/fullchain.pem" && -s "$LIVE/privkey.pem" ]] || { echo "No certificate found in $LIVE. Nothing else was changed." >&2; exit 1; }

# 4. nginx: HTTPS site for the domain (same settings as the Cloudflare template)
step "Enabling HTTPS in nginx"
BACKUP="/root/https-backup-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$BACKUP"
[[ -e "$SITE_AVAIL" ]] && cp -a "$SITE_AVAIL" "$BACKUP/"
HAD_LINK=0; [[ -L "$SITE_ENABLED" ]] && HAD_LINK=1

rollback() {
    echo "Something went wrong — restoring the previous nginx setup." >&2
    if [[ -e "$BACKUP/$DOMAIN.conf" ]]; then cp -a "$BACKUP/$DOMAIN.conf" "$SITE_AVAIL"; else rm -f "$SITE_AVAIL"; fi
    [[ $HAD_LINK -eq 0 ]] && rm -f "$SITE_ENABLED"
    nginx -t >/dev/null 2>&1 && systemctl reload nginx
    exit 1
}

sed -e "s#/etc/ssl/cloudflare/$DOMAIN.pem#$LIVE/fullchain.pem#" \
    -e "s#/etc/ssl/cloudflare/$DOMAIN.key#$LIVE/privkey.pem#" \
    -e "s#Uses a Cloudflare Origin Certificate.*#Uses the Let's Encrypt certificate from certbot (renews itself).#" \
    "$SITE_SRC" > "$SITE_AVAIL"
chmod 644 "$SITE_AVAIL"
ln -sfn "$SITE_AVAIL" "$SITE_ENABLED"

nginx -t || rollback
systemctl reload nginx || rollback

if command -v ufw >/dev/null && ufw status | grep -q "Status: active"; then
    ufw allow 443/tcp >/dev/null && echo "   Firewall: port 443 opened."
fi

# 5. Checks
step "Checking"
curl -s -o /dev/null -w "   https://$DOMAIN on this server -> HTTP %{http_code}\n" --resolve "$DOMAIN:443:127.0.0.1" "https://$DOMAIN/login" || true
echo "   Certificate valid until: $(openssl x509 -in "$LIVE/fullchain.pem" -noout -enddate | cut -d= -f2)"
if systemctl list-timers 2>/dev/null | grep -q certbot; then
    echo "   Automatic renewal: on (certbot timer)"
fi

cat <<MSG

Done — HTTPS is on for $DOMAIN. Backup of anything replaced: $BACKUP

Last step, in Cloudflare (sunlitnetwork.com):
  SSL/TLS -> Overview -> Configure -> Custom SSL/TLS -> "Full (strict)" -> Save
MSG
