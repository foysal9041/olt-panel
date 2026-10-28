#!/usr/bin/env bash
#
# Undo an update: put the panel back the way it was when a backup was made —
# the code at that version, the database and the uploaded files. .env, the
# company's settings in it, nginx and the scheduler stay as they are.
#
#   sudo bash deploy/rollback.sh /var/backups/olt-panel/olt-panel-backup-YYYYmmdd-HHMM.tar.gz [-y]
#
# install.sh makes such a backup before every update (in /var/backups/olt-panel).
# Changes made in the panel after that backup are lost. Graph / latency
# history isn't in those backups and is kept as it is.
set -Eeuo pipefail

# Run from a copy: checking out the old version replaces this very file.
if [[ -z "${OLT_ROLLBACK_DIR:-}" ]]; then
    OLT_ROLLBACK_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
    copy="$(mktemp /tmp/olt-panel-rollback.XXXXXX)"
    cp "${BASH_SOURCE[0]}" "$copy"
    OLT_ROLLBACK_DIR="$OLT_ROLLBACK_DIR" OLT_ROLLBACK_COPY="$copy" exec bash "$copy" "$@"
fi
APP_DIR="$OLT_ROLLBACK_DIR"
FILE="${1:-}"
YES="${2:-}"
die() { echo "Error: $*" >&2; exit 1; }
step() { echo; echo "==> $*"; }

[[ $EUID -eq 0 ]] || die "run it as root:  sudo bash $0 <backup file>"
[[ -n "$FILE" && -r "$FILE" ]] || die "give the backup file:  sudo bash $0 /var/backups/olt-panel/olt-panel-backup-….tar.gz"

CODE_OWNER="$(stat -c %U "$APP_DIR")"
RUN_AS="$(stat -c %U "$APP_DIR/storage")"
as() { local u="$1"; shift; if [[ "$u" == root ]]; then "$@"; else runuser -u "$u" -- env HOME="$(getent passwd "$u" | cut -d: -f6)" "$@"; fi; }
artisan() { (cd "$APP_DIR" && as "$RUN_AS" php artisan "$@"); }
git_() { as "$CODE_OWNER" git -c safe.directory="$APP_DIR" -C "$APP_DIR" "$@"; }
env_value() { local v; v="$(grep -E "^$1=" "$APP_DIR/.env" | tail -n1 | cut -d= -f2-)" || true; v="${v#\"}"; printf '%s' "${v%\"}"; }

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP" "${OLT_ROLLBACK_COPY:-}"' EXIT
tar -xzf "$FILE" -C "$TMP"
[[ -f "$TMP/database.sql.gz" && -f "$TMP/storage.tar.gz" ]] || die "$FILE isn't a backup made by deploy/backup.sh."
VERSION="$(cat "$TMP/version" 2>/dev/null || true)"
[[ -n "$VERSION" ]] || die "this backup doesn't say which code version it was made with (older backup) — check out that version by hand, then import its database.sql.gz."
git_ cat-file -e "$VERSION^{commit}" 2>/dev/null || git_ fetch -q origin || true
git_ cat-file -e "$VERSION^{commit}" 2>/dev/null || die "version $VERSION isn't in $APP_DIR's git history."
git_ diff --quiet && git_ diff --cached --quiet || die "$APP_DIR has local code changes — commit or stash them first."

echo "Rolling $APP_DIR back to the backup of $(cat "$TMP/created" 2>/dev/null || echo '?') (code $(git_ log -1 --format='%h %s' "$VERSION"))."
if [[ "$YES" != -y ]]; then
    read -r -p "Changes made since then are lost. Type yes to go on: " ok < /dev/tty || ok=""
    [[ "$ok" == yes ]] || die "stopped; nothing was changed."
fi

step "Maintenance mode on"
artisan down --retry=30 || true

step "Code → $(git_ rev-parse --short "$VERSION")"
git_ checkout -q "$VERSION"
(cd "$APP_DIR" && COMPOSER_ALLOW_SUPERUSER=1 as "$CODE_OWNER" composer install --no-dev --optimize-autoloader --no-interaction --no-progress -q)

step "Database"
export MYSQL_PWD="$(env_value DB_PASSWORD)"
DB=(mysql --no-defaults -h "$(env_value DB_HOST)" -P "$(env_value DB_PORT)" -u "$(env_value DB_USERNAME)" "$(env_value DB_DATABASE)")
tables_in() { [[ -f "$1" ]] && gunzip -c "$1" | sed -n 's/^CREATE TABLE `\([^`]*\)`.*/\1/p'; true; }
# Tables the newer version's migrations added go, so its migrations can run again later;
# the backup's own tables come back, and those it left out (history, sessions) stay.
KEEP="$( { tables_in "$TMP/database.sql.gz"; tables_in "$TMP/skipped-tables.sql.gz"; } | sort -u)"
DROP=()
while read -r t; do
    [[ -n "$t" ]] && ! grep -qxF "$t" <<< "$KEEP" && DROP+=("$t")
done < <("${DB[@]}" -N -e 'SHOW TABLES')
if (( ${#DROP[@]} )); then
    echo "   Removing tables added after the backup: ${DROP[*]}"
    "${DB[@]}" -e "SET FOREIGN_KEY_CHECKS=0; $(printf 'DROP TABLE `%s`; ' "${DROP[@]}") SET FOREIGN_KEY_CHECKS=1;"
fi
gunzip -c "$TMP/database.sql.gz" | "${DB[@]}"

step "Uploaded files"
tar -xzf "$TMP/storage.tar.gz" -C "$APP_DIR/storage"
chown -R "$RUN_AS" "$APP_DIR/storage/app" 2>/dev/null || true

step "Back online"
artisan optimize:clear -q || true
artisan optimize -q
artisan up

echo
echo "Done: back at $(git_ log -1 --format='%h %s'). To update again later: sudo bash $APP_DIR/install.sh"
