#!/usr/bin/env bash
#
# Pack the whole panel into one file: the database, uploaded files and the
# app key (the saved OLT, SMTP and Telegram passwords are encrypted with it).
#
#   sudo bash deploy/backup.sh                 → /root/olt-panel-backup-YYYYmmdd-HHMM.tar.gz
#   sudo bash deploy/backup.sh --no-history    leave out the graph / latency history (much smaller)
#   sudo bash deploy/backup.sh --out=/some/dir
#
# Move the panel to another machine:  copy the file there, then
#   sudo bash install.sh --restore=/path/olt-panel-backup-….tar.gz
#
# The file holds passwords and the app key: keep it private.
set -Eeuo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="${OUT_DIR:-$([[ $EUID -eq 0 ]] && echo /root || echo "$HOME")}"
HISTORY=yes
for arg in "$@"; do
    case "$arg" in
        --no-history) HISTORY=no ;;
        --out=*) OUT_DIR="${arg#*=}" ;;
        -h|--help) sed -n '2,15p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "Unknown option: $arg" >&2; exit 1 ;;
    esac
done

ENV_FILE="$APP_DIR/.env"
[[ -r "$ENV_FILE" ]] || { echo "Can't read $ENV_FILE — run with sudo." >&2; exit 1; }
env_value() { local v; v="$(grep -E "^$1=" "$ENV_FILE" | tail -n1 | cut -d= -f2-)" || true; v="${v#\"}"; printf '%s' "${v%\"}"; }
DB_HOST="$(env_value DB_HOST)"; DB_PORT="$(env_value DB_PORT)"
DB_NAME="$(env_value DB_DATABASE)"; DB_USER="$(env_value DB_USERNAME)"
export MYSQL_PWD="$(env_value DB_PASSWORD)"   # not on the command line, where `ps` would show it

# Big, rebuildable tables: kept out with --no-history (their structure still goes in).
HISTORY_TABLES=(switch_port_traffic switch_port_readings latency_probes device_metrics device_cpu)
SKIP=(sessions cache cache_locks)
[[ "$HISTORY" == no ]] && SKIP+=("${HISTORY_TABLES[@]}")

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
mkdir -p "$OUT_DIR"
FILE="$OUT_DIR/olt-panel-backup-$(date +%Y%m%d-%H%M).tar.gz"
DUMP=(mysqldump --no-defaults -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "$DB_USER"
      --single-transaction --quick --routines --triggers --default-character-set=utf8mb4 --no-tablespaces)

echo "Backing up $APP_DIR (database $DB_NAME)…"
{
    IGNORE=()
    for t in "${SKIP[@]}"; do IGNORE+=("--ignore-table=$DB_NAME.$t"); done
    "${DUMP[@]}" "${IGNORE[@]}" "$DB_NAME"
    # …and the skipped tables' structure, so a restore has every table.
    "${DUMP[@]}" --no-data "$DB_NAME" "${SKIP[@]}"
} | gzip -6 > "$TMP/database.sql.gz"
echo "  database: $(du -h "$TMP/database.sql.gz" | cut -f1)"

tar -czf "$TMP/storage.tar.gz" -C "$APP_DIR/storage" app
echo "  uploaded files: $(du -h "$TMP/storage.tar.gz" | cut -f1)"

cp "$ENV_FILE" "$TMP/env"
date '+%Y-%m-%d %H:%M' > "$TMP/created"
echo "$(hostname) $(git -C "$APP_DIR" log -1 --format=%h 2>/dev/null || true)" > "$TMP/source"
[[ "$HISTORY" == no ]] && echo "without graph/latency history" >> "$TMP/source"

(umask 077 && tar -czf "$FILE" -C "$TMP" .)
echo "Done: $FILE ($(du -h "$FILE" | cut -f1)) — it contains passwords, keep it private."
echo "Restore on a new machine:  sudo bash install.sh --restore=$FILE"
