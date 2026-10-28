#!/usr/bin/env bash
#
# OLT Panel — one-command installer for Ubuntu 22.04 / 24.04 and Debian 12.
#
#   curl -fsSL https://raw.githubusercontent.com/foysal9041/olt-panel/master/install.sh | sudo bash
#
# or, from a checkout:   sudo bash install.sh
#
# Sets up nginx, PHP-FPM, MariaDB, Composer, the app, its database, the first
# admin, the every-minute scheduler and (optionally) a Let's Encrypt
# certificate. Run it again at any time to update: it pulls the latest code,
# installs packages and runs new migrations, keeping .env and all data.
#
# One installation = one company: its own server, domain, database, files and
# branding (name, logos, colours: Settings → Company Branding).
#
# Options (each also works as an environment variable, e.g. DOMAIN=…):
#   --company="Acme Fiber"       the company's name (asked; logos and colours later in the panel)
#   --domain=noc.example.com     address the panel answers on (default: this server's IP)
#   --admin-user=admin           first admin's username (default: admin)
#   --admin-pass=…               first admin's password (default: asked, or a random one)
#   --ssl --email=you@example.com   free HTTPS from Let's Encrypt (needs a domain pointing here)
#   --restore=/path/backup.tar.gz   move an existing panel here: a file made by deploy/backup.sh
#   --dir=/var/www/olt-panel     where the app lives
#   --repo=URL --branch=master   where the code comes from
#   --version=v1.2.0             install / update to this release (a git tag or commit)
#   --no-backup                  updating: skip the automatic backup taken first
#   --php=8.3                    PHP version (default: the system's, if 8.2 or newer)
#   -y, --yes                    no questions; defaults for everything not given
#
set -Eeuo pipefail

# Run from a copy: updating pulls a new install.sh over this very file.
if [[ -n "${BASH_SOURCE[0]:-}" && -f "${BASH_SOURCE[0]}" && -z "${OLT_INSTALLER_COPY:-}" ]]; then
    OLT_INSTALLER_COPY="$(mktemp /tmp/olt-panel-install.XXXXXX)"
    cp "${BASH_SOURCE[0]}" "$OLT_INSTALLER_COPY"
    OLT_INSTALLER_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    export OLT_INSTALLER_COPY OLT_INSTALLER_DIR
    exec bash "$OLT_INSTALLER_COPY" "$@"
fi
[[ -n "${OLT_INSTALLER_COPY:-}" ]] && trap 'rm -f "$OLT_INSTALLER_COPY"' EXIT

REPO_URL="${REPO_URL:-https://github.com/foysal9041/olt-panel.git}"
BRANCH="${BRANCH:-master}"
APP_DIR="${APP_DIR:-}"
DOMAIN="${DOMAIN:-}"
ADMIN_USER="${ADMIN_USER:-}"
ADMIN_PASS="${ADMIN_PASS:-}"
SSL="${SSL:-}"
EMAIL="${EMAIL:-}"
RESTORE="${RESTORE:-}"
PHP_VERSION="${PHP_VERSION:-}"
ASSUME_YES="${ASSUME_YES:-}"
DB_NAME="${DB_NAME:-olt_panel}"
DB_USER="${DB_USER:-olt_panel}"
APP_NAME="${APP_NAME:-OLT Panel}"
COMPANY="${COMPANY:-}"
VERSION_REF="${VERSION_REF:-}"
NO_BACKUP="${NO_BACKUP:-}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/olt-panel}"
WEB_USER="www-data"

for arg in "$@"; do
    case "$arg" in
        --domain=*) DOMAIN="${arg#*=}" ;;
        --admin-user=*) ADMIN_USER="${arg#*=}" ;;
        --admin-pass=*) ADMIN_PASS="${arg#*=}" ;;
        --ssl) SSL=yes ;;
        --no-ssl) SSL=no ;;
        --email=*) EMAIL="${arg#*=}" ;;
        --restore=*) RESTORE="${arg#*=}" ;;
        --dir=*) APP_DIR="${arg#*=}" ;;
        --repo=*) REPO_URL="${arg#*=}" ;;
        --branch=*) BRANCH="${arg#*=}" ;;
        --company=*) COMPANY="${arg#*=}" ;;
        --version=*) VERSION_REF="${arg#*=}" ;;
        --no-backup) NO_BACKUP=yes ;;
        --php=*) PHP_VERSION="${arg#*=}" ;;
        -y|--yes) ASSUME_YES=yes ;;
        -h|--help) sed -n '2,27p' "${BASH_SOURCE[0]:-/dev/null}" 2>/dev/null | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "Unknown option: $arg (see --help)" >&2; exit 1 ;;
    esac
done

# ---- Output helpers ---------------------------------------------------------

if [[ -t 1 ]]; then G=$'\e[32m'; Y=$'\e[33m'; R=$'\e[31m'; B=$'\e[1m'; N=$'\e[0m'; else G=; Y=; R=; B=; N=; fi
step() { echo; echo "${B}${G}==>${N}${B} $*${N}"; }
info() { echo "    $*"; }
warn() { echo "${Y}    ! $*${N}"; }
die()  { echo; echo "${R}${B}Error:${N} $*" >&2; exit 1; }
trap 'die "the installer stopped at line $LINENO ($BASH_COMMAND). Fix the problem above and run it again — it picks up where it left off."' ERR

# Questions go to the terminal even when the script itself comes through a pipe (curl | bash).
can_ask() { [[ "$ASSUME_YES" != yes ]] && { : < /dev/tty; } 2>/dev/null; }
ask() { # ask VAR "Question" "default"
    local __var="$1" __q="$2" __def="${3:-}" __ans=""
    if can_ask; then
        read -r -p "    $__q${__def:+ [$__def]}: " __ans < /dev/tty || true
    fi
    printf -v "$__var" '%s' "${__ans:-$__def}"
}
ask_secret() { # ask_secret VAR "Question"
    local __var="$1" __q="$2" __ans=""
    if can_ask; then
        read -r -s -p "    $__q: " __ans < /dev/tty || true
        echo
    fi
    printf -v "$__var" '%s' "$__ans"
}
yes_no() { # yes_no "Question" default(y|n)
    local ans
    ask ans "$1 (y/n)" "$2"
    [[ "$ans" =~ ^[Yy] ]]
}
random() { local s; s="$(head -c 400 /dev/urandom | tr -dc 'A-Za-z0-9')"; printf '%s' "${s:0:${1:-20}}"; }
as_user() { local u="$1"; shift; if [[ "$u" == root ]]; then "$@"; else runuser -u "$u" -- "$@"; fi; }
artisan() { (cd "$APP_DIR" && as_user "$RUN_AS" php artisan "$@"); }
as_owner() { as_user "$CODE_OWNER" env HOME="$(getent passwd "$CODE_OWNER" | cut -d: -f6)" "$@"; }
set_env() { # set_env KEY VALUE — in .env, quoted when needed
    local key="$1" val="$2" file="$APP_DIR/.env"
    [[ "$val" =~ [[:space:]\#\"\$] ]] && val="\"${val//\"/\\\"}\""
    if grep -qE "^#? *${key}=" "$file"; then
        KEY="$key" VAL="$val" perl -0pi -e 's/^#? *\Q$ENV{KEY}\E=.*$/$ENV{KEY}=$ENV{VAL}/m' "$file"
    else
        printf '%s=%s\n' "$key" "$val" >> "$file"
    fi
}
env_value() { # env_value KEY FILE — empty when missing
    local v
    v="$(grep -E "^$1=" "$2" 2>/dev/null | tail -n1 | cut -d= -f2-)" || true
    v="${v#\"}"; printf '%s' "${v%\"}"
}

# ---- Checks -----------------------------------------------------------------

[[ $EUID -eq 0 ]] || die "run it as root:  sudo bash install.sh"
[[ -r /etc/os-release ]] || die "can't tell which Linux this is (no /etc/os-release)."
. /etc/os-release
case "${ID:-}" in
    ubuntu) [[ "${VERSION_ID%%.*}" -ge 22 ]] || die "Ubuntu 22.04 or newer is needed (this is $PRETTY_NAME)." ;;
    debian) [[ "${VERSION_ID%%.*}" -ge 12 ]] || die "Debian 12 or newer is needed (this is $PRETTY_NAME)." ;;
    *) die "this installer supports Ubuntu 22.04+ and Debian 12+ (this is ${PRETTY_NAME:-unknown}). See README.md for a manual install." ;;
esac
[[ -z "$RESTORE" || -r "$RESTORE" ]] || die "can't read the backup file $RESTORE"

# Run from a checkout: install that checkout, unless told otherwise.
SCRIPT_DIR="${OLT_INSTALLER_DIR:-}"
if [[ -z "$APP_DIR" ]]; then
    if [[ -n "$SCRIPT_DIR" && -f "$SCRIPT_DIR/artisan" ]]; then APP_DIR="$SCRIPT_DIR"; else APP_DIR=/var/www/olt-panel; fi
fi

UPDATING=no
[[ -f "$APP_DIR/.env" ]] && grep -qE '^APP_KEY=.+' "$APP_DIR/.env" && UPDATING=yes

# Who owns what: a fresh install is root's code with www-data's storage; an
# existing install keeps its owners (artisan runs as whoever owns storage/).
CODE_OWNER=root
RUN_AS="$WEB_USER"
if [[ "$UPDATING" == yes ]]; then
    CODE_OWNER="$(stat -c %U "$APP_DIR")"
    RUN_AS="$(stat -c %U "$APP_DIR/storage")"
fi

SERVER_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
DOMAIN_GIVEN="$DOMAIN"
# Updating: the address is the one already in use, unless --domain changes it.
if [[ "$UPDATING" == yes && -z "$DOMAIN" ]]; then
    DOMAIN="$(env_value APP_URL "$APP_DIR/.env" | sed -E 's#^https?://##; s#[/:].*$##')"
fi

echo "${B}OLT Panel installer${N} — $PRETTY_NAME"
if [[ "$UPDATING" == yes ]]; then
    info "Found an installed panel in $APP_DIR: updating it (settings and data are kept)."
else
    info "Installing into $APP_DIR"
    [[ -n "$DOMAIN" ]] || ask DOMAIN "Domain or IP the panel will be opened on" "$SERVER_IP"
    if [[ -z "$RESTORE" ]]; then
        [[ -n "$COMPANY" ]] || ask COMPANY "Company name (shown in the panel, invoices and e-mails)" "OLT Panel"
        [[ -n "$ADMIN_USER" ]] || ask ADMIN_USER "Admin username" "admin"
        [[ -n "$ADMIN_PASS" ]] || ask_secret ADMIN_PASS "Admin password (leave blank for a random one)"
        if [[ -z "$ADMIN_PASS" ]]; then ADMIN_PASS="$(random 16)"; SHOW_PASS=yes; fi
        [[ ${#ADMIN_PASS} -ge 8 ]] || die "the admin password needs at least 8 characters."
    fi
    if [[ -z "$SSL" && "$DOMAIN" =~ [a-zA-Z] ]]; then
        yes_no "Get a free HTTPS certificate from Let's Encrypt for $DOMAIN? (the domain must already point to this server)" n && SSL=yes || SSL=no
    fi
    if [[ "$SSL" == yes && -z "$EMAIL" ]]; then
        ask EMAIL "Email for Let's Encrypt expiry notices" ""
        [[ -n "$EMAIL" ]] || die "Let's Encrypt needs an email address (--email=…)."
    fi
fi
DOMAIN="${DOMAIN:-$SERVER_IP}"

# ---- System packages ----------------------------------------------------------

step "Installing system packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq ca-certificates curl git unzip openssl perl gnupg lsb-release cron sudo \
    nginx mariadb-server mariadb-client iputils-ping snmp certbot >/dev/null

# PHP: an update keeps the version already in use; a new install takes the system's own when
# it is 8.2 or newer, else 8.3 from the well-known Sury / Ondřej repository.
if [[ -z "$PHP_VERSION" && "$UPDATING" == yes ]]; then
    PHP_VERSION="$(sed -n 's/^PHP_VERSION=//p' /etc/olt-panel/admin.conf 2>/dev/null | tail -n1)"
    [[ -n "$PHP_VERSION" ]] || PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;' 2>/dev/null || true)"
fi
if [[ -z "$PHP_VERSION" ]]; then
    PHP_VERSION="$(apt-cache policy php 2>/dev/null | awk '/Candidate:/ {print $2}' | sed -E 's/^[0-9]+://; s/^([0-9]+\.[0-9]+).*/\1/')"
fi
if [[ -z "$PHP_VERSION" || "$(printf '%s\n8.2\n' "$PHP_VERSION" | sort -V | head -n1)" != "8.2" ]]; then
    info "This system's PHP (${PHP_VERSION:-none}) is older than 8.2: adding the PHP repository for 8.3"
    PHP_VERSION=8.3
    if [[ "$ID" == ubuntu ]]; then
        apt-get install -y -qq software-properties-common >/dev/null
        add-apt-repository -y ppa:ondrej/php >/dev/null
    else
        curl -fsSL https://packages.sury.org/php/apt.gpg -o /usr/share/keyrings/sury-php.gpg
        echo "deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php/ $VERSION_CODENAME main" > /etc/apt/sources.list.d/sury-php.list
    fi
    apt-get update -qq
fi
info "PHP $PHP_VERSION"
apt-get install -y -qq "php$PHP_VERSION-fpm" "php$PHP_VERSION-cli" "php$PHP_VERSION-mysql" "php$PHP_VERSION-mbstring" \
    "php$PHP_VERSION-xml" "php$PHP_VERSION-curl" "php$PHP_VERSION-zip" "php$PHP_VERSION-gd" "php$PHP_VERSION-bcmath" \
    "php$PHP_VERSION-intl" "php$PHP_VERSION-snmp" "php$PHP_VERSION-opcache" "php$PHP_VERSION-readline" >/dev/null
update-alternatives --set php "/usr/bin/php$PHP_VERSION" >/dev/null 2>&1 || true

cat > "/etc/php/$PHP_VERSION/fpm/conf.d/99-olt-panel.ini" <<'INI'
; OLT Panel (install.sh)
upload_max_filesize = 20M
post_max_size = 20M
memory_limit = 512M
max_execution_time = 120
INI
cp "/etc/php/$PHP_VERSION/fpm/conf.d/99-olt-panel.ini" "/etc/php/$PHP_VERSION/cli/conf.d/99-olt-panel.ini"
sed -i 's/^memory_limit = .*/memory_limit = -1/' "/etc/php/$PHP_VERSION/cli/conf.d/99-olt-panel.ini"

if ! command -v composer >/dev/null || ! composer --version 2>/dev/null | grep -qE 'Composer (version )?2'; then
    step "Installing Composer"
    TMP_C="$(mktemp -d)"
    curl -fsSL https://getcomposer.org/installer -o "$TMP_C/composer-setup.php"
    [[ "$(curl -fsSL https://composer.github.io/installer.sig)" == "$(php -r "echo hash_file('sha384', '$TMP_C/composer-setup.php');")" ]] \
        || die "the Composer installer's checksum didn't match — not running it."
    php "$TMP_C/composer-setup.php" --quiet --install-dir=/usr/local/bin --filename=composer
    rm -rf "$TMP_C"
fi

systemctl enable --now mariadb >/dev/null 2>&1 || systemctl enable --now mysql >/dev/null 2>&1 || true
systemctl enable --now "php$PHP_VERSION-fpm" nginx cron >/dev/null

# ---- Backup before an update -----------------------------------------------------

PREV_VERSION=""
BACKUP_FILE=""
if [[ "$UPDATING" == yes ]]; then
    PREV_VERSION="$(git -c safe.directory="$APP_DIR" -C "$APP_DIR" rev-parse --short HEAD 2>/dev/null || true)"
    if [[ "$NO_BACKUP" != yes && -f "$APP_DIR/deploy/backup.sh" ]]; then
        step "Backing up first (database, uploaded files, settings)"
        bash "$APP_DIR/deploy/backup.sh" --no-history --out="$BACKUP_DIR" | sed -n 's/^/    /;2,3p'
        BACKUP_FILE="$(ls -t "$BACKUP_DIR"/olt-panel-backup-*.tar.gz 2>/dev/null | head -n1)"
        [[ -n "$BACKUP_FILE" ]] || die "the backup wasn't made — not updating. (Skip it with --no-backup.)"
        info "Saved: $BACKUP_FILE"
        # Keep the 10 newest.
        ls -t "$BACKUP_DIR"/olt-panel-backup-*.tar.gz 2>/dev/null | tail -n +11 | xargs -r rm -f
    fi
fi

# ---- The code -------------------------------------------------------------------

step "Getting the code"
git config --global --get-all safe.directory 2>/dev/null | grep -qxF "$APP_DIR" || git config --global --add safe.directory "$APP_DIR"
if [[ -d "$APP_DIR/.git" ]]; then
    if [[ "$APP_DIR" != "$SCRIPT_DIR" || "$UPDATING" == yes ]]; then
        # As the folder's owner: their SSH key, and no root-owned files left in .git.
        if ! { as_owner git -C "$APP_DIR" diff --quiet && as_owner git -C "$APP_DIR" diff --cached --quiet; }; then
            warn "$APP_DIR has local changes — not pulling; installing what is there."
        elif [[ -n "$VERSION_REF" ]]; then
            { as_owner git -C "$APP_DIR" fetch -q --tags origin && as_owner git -C "$APP_DIR" checkout -q "$VERSION_REF"; } \
                || die "couldn't check out version $VERSION_REF."
        else
            { as_owner git -C "$APP_DIR" fetch -q origin "$BRANCH" && as_owner git -C "$APP_DIR" checkout -q "$BRANCH" \
                && as_owner git -C "$APP_DIR" pull -q --ff-only origin "$BRANCH"; } \
                || warn "couldn't pull the latest code (local commits?) — installing what is there."
        fi
    fi
elif [[ -e "$APP_DIR" && -n "$(ls -A "$APP_DIR" 2>/dev/null)" ]]; then
    die "$APP_DIR already exists and isn't a copy of the panel. Pick another place with --dir=…"
else
    mkdir -p "$(dirname "$APP_DIR")"
    git clone -q --branch "$BRANCH" "$REPO_URL" "$APP_DIR"
    [[ -z "$VERSION_REF" ]] || git -C "$APP_DIR" checkout -q "$VERSION_REF" || die "there's no version $VERSION_REF."
fi
info "$(git -C "$APP_DIR" log -1 --format='%h %s (%cd)' --date=short)"

step "Installing PHP packages (composer)"
(cd "$APP_DIR" && COMPOSER_ALLOW_SUPERUSER=1 as_owner composer install --no-dev --optimize-autoloader --no-interaction --no-progress -q)
[[ -f "$APP_DIR/public/build/manifest.json" ]] || warn "public/build is missing: the sign-in page needs it. Run 'npm ci && npm run build' in $APP_DIR (Node 20+)."

# ---- Restore: unpack the backup -------------------------------------------------

RESTORE_DIR=""
if [[ -n "$RESTORE" && "$UPDATING" != yes ]]; then
    step "Unpacking the backup"
    RESTORE_DIR="$(mktemp -d)"
    tar -xzf "$RESTORE" -C "$RESTORE_DIR"
    [[ -f "$RESTORE_DIR/database.sql.gz" && -f "$RESTORE_DIR/env" ]] || die "$RESTORE isn't a backup made by deploy/backup.sh."
    info "made $(cat "$RESTORE_DIR/created" 2>/dev/null || echo '?') from $(cat "$RESTORE_DIR/source" 2>/dev/null || echo '?')"
fi

# ---- Database and .env ----------------------------------------------------------

if [[ "$UPDATING" != yes ]]; then
    step "Creating the database"
    DB_PASS="$(random 24)"
    mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
ALTER USER '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
    if [[ -z "$RESTORE_DIR" && "$(mysql -uroot -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME'")" != 0 ]]; then
        warn "the database $DB_NAME already has tables — keeping them (migrations add what's missing)."
    fi
    if [[ -n "$RESTORE_DIR" ]]; then
        info "Importing the backed-up database (this can take a while)…"
        gunzip -c "$RESTORE_DIR/database.sql.gz" | mysql -uroot "$DB_NAME"
        [[ ! -f "$RESTORE_DIR/skipped-tables.sql.gz" ]] || gunzip -c "$RESTORE_DIR/skipped-tables.sql.gz" | mysql -uroot "$DB_NAME"
    fi

    step "Writing the settings (.env)"
    cp "$APP_DIR/.env.example" "$APP_DIR/.env"
    SCHEME=http   # https once a certificate is in (olt-panel-admin ssl-on)
    set_env APP_NAME "${COMPANY:-$APP_NAME}"
    set_env APP_ENV production
    set_env APP_DEBUG false
    set_env APP_URL "$SCHEME://$DOMAIN"
    set_env LOG_STACK daily
    set_env LOG_LEVEL warning
    set_env DB_CONNECTION mysql
    set_env DB_HOST 127.0.0.1
    set_env DB_PORT 3306
    set_env DB_DATABASE "$DB_NAME"
    set_env DB_USERNAME "$DB_USER"
    set_env DB_PASSWORD "$DB_PASS"
    set_env SESSION_DRIVER database
    set_env CACHE_STORE database
    set_env QUEUE_CONNECTION sync
    set_env MAIL_MAILER log
    if [[ -n "$RESTORE_DIR" ]]; then
        # The old key: saved OLT, SMTP and Telegram passwords are encrypted with it.
        set_env APP_KEY "$(env_value APP_KEY "$RESTORE_DIR/env")"
        OLD_NAME="$(env_value APP_NAME "$RESTORE_DIR/env")"
        [[ -n "$OLD_NAME" ]] && set_env APP_NAME "$OLD_NAME"
    fi
    # Laravel's key format, written here: the web user can't write .env.
    grep -qE '^APP_KEY=.+' "$APP_DIR/.env" || set_env APP_KEY "base64:$(head -c 32 /dev/urandom | base64)"
    chmod 640 "$APP_DIR/.env"
fi

# ---- Files and permissions ------------------------------------------------------

step "Setting up the app"
mkdir -p "$APP_DIR"/storage/{app/public,app/private,framework/{cache/data,sessions,views},logs,fonts} "$APP_DIR/bootstrap/cache"
if [[ -n "$RESTORE_DIR" && -f "$RESTORE_DIR/storage.tar.gz" ]]; then
    info "Putting back uploaded files…"
    tar -xzf "$RESTORE_DIR/storage.tar.gz" -C "$APP_DIR/storage"
fi
if [[ "$UPDATING" != yes ]]; then
    chown -R root:"$WEB_USER" "$APP_DIR"
    chown -R "$WEB_USER":"$WEB_USER" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
    chmod -R ug+rwX "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
    chown root:"$WEB_USER" "$APP_DIR/.env"
fi

[[ -e "$APP_DIR/public/storage" ]] || ln -s "$APP_DIR/storage/app/public" "$APP_DIR/public/storage"
artisan optimize:clear -q >/dev/null 2>&1 || true
artisan migrate --force

# No admin yet (a first run that stopped half-way): make one now.
ADMINS="$(mysql -uroot -N -e "SELECT COUNT(*) FROM \`$(env_value DB_DATABASE "$APP_DIR/.env")\`.users WHERE role = 'admin'" 2>/dev/null || echo '?')"
if [[ "$ADMINS" == 0 && -z "$ADMIN_USER" ]]; then
    warn "There is no admin user yet."
    ask ADMIN_USER "Admin username" "admin"
    ask_secret ADMIN_PASS "Admin password (leave blank for a random one)"
    if [[ -z "$ADMIN_PASS" ]]; then ADMIN_PASS="$(random 16)"; SHOW_PASS=yes; fi
fi

# The first admin (or, with --admin-user/--admin-pass on a restore or update, a password reset).
if [[ -n "$ADMIN_USER" && -n "$ADMIN_PASS" ]]; then
    printf '%s' "$ADMIN_PASS" | artisan app:create-admin --username="$ADMIN_USER" --password-stdin
fi
# The company's name (logos, colours, contact details: Settings → Company Branding).
[[ -z "$COMPANY" ]] || artisan app:branding --name="$COMPANY" -q
artisan optimize -q

# ---- Scheduler --------------------------------------------------------------------

step "Starting the scheduler (polls, pings and alerts every minute)"
if grep -rsl "cd $APP_DIR && php artisan schedule:run" /var/spool/cron/crontabs /etc/crontab /etc/cron.d 2>/dev/null | grep -qv '/etc/cron.d/olt-panel$'; then
    info "A scheduler for $APP_DIR is already in a crontab — not adding a second one."
else
    cat > /etc/cron.d/olt-panel <<CRON
# OLT Panel (install.sh): Laravel's scheduler — OLT/switch polls, latency, NTTN checks, alerts.
* * * * * $RUN_AS cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1
CRON
    chmod 644 /etc/cron.d/olt-panel
fi

# ---- The root helper: address and HTTPS, also from the panel (Settings → Server & HTTPS) ----

HELPER=/usr/local/sbin/olt-panel-admin
install -m 755 -o root -g root "$APP_DIR/deploy/olt-panel-admin" "$HELPER"
install -d -m 755 /etc/olt-panel
cat > /etc/olt-panel/admin.conf <<CONF
# OLT Panel (install.sh): read by $HELPER.
APP_DIR=$APP_DIR
PHP_VERSION=$PHP_VERSION
RUN_AS=$RUN_AS
CONF
chmod 644 /etc/olt-panel/admin.conf
# The web server may run that one file as root — nothing else.
SUDOERS_TMP="$(mktemp)"
printf '# OLT Panel (install.sh): the panel changes its address and HTTPS through this helper only.\n%s ALL=(root) NOPASSWD: %s\n' "$WEB_USER" "$HELPER" > "$SUDOERS_TMP"
if visudo -cqf "$SUDOERS_TMP"; then
    install -m 440 -o root -g root "$SUDOERS_TMP" /etc/sudoers.d/olt-panel
else
    warn "couldn't add the sudoers rule — address and HTTPS can't be changed from the panel."
fi
rm -f "$SUDOERS_TMP"

# ---- nginx ------------------------------------------------------------------------

SITE=/etc/nginx/sites-available/olt-panel.conf
if [[ ! -f "$SITE" && -n "$(grep -rl "root $APP_DIR/public" /etc/nginx/sites-enabled/ 2>/dev/null || true)" ]]; then
    info "nginx already serves $APP_DIR (set up by hand) — left as it is; the panel can't change its address or HTTPS."
else
    if [[ ! -f "$SITE" ]]; then
        step "Configuring nginx"
        rm -f /etc/nginx/sites-enabled/default
        "$HELPER" address "$DOMAIN" --create | sed 's/^/    /'
    elif [[ "$UPDATING" == yes && -n "$DOMAIN_GIVEN" ]]; then
        step "Changing the panel's address to $DOMAIN_GIVEN"
        "$HELPER" address "$DOMAIN_GIVEN" | sed 's/^/    /'
        DOMAIN="$DOMAIN_GIVEN"
    fi
    if [[ "$SSL" == yes ]]; then
        step "Getting a Let's Encrypt certificate for $DOMAIN"
        if "$HELPER" ssl-on "$EMAIL" | sed 's/^/    /'; then
            info "certbot renews it by itself."
        else
            warn "The panel stays on plain HTTP. Turn HTTPS on later in Settings → Server & HTTPS (or run the installer again with --ssl)."
        fi
    fi
fi

if command -v ufw >/dev/null && ufw status 2>/dev/null | grep -q "Status: active"; then
    ufw allow 80/tcp >/dev/null; ufw allow 443/tcp >/dev/null
    info "Firewall (ufw): opened ports 80 and 443."
fi

systemctl restart "php$PHP_VERSION-fpm"
systemctl reload nginx

# ---- Check and summary -----------------------------------------------------------

step "Checking"
CODE="$(curl -s -o /dev/null -w '%{http_code}' -H "Host: $DOMAIN" http://127.0.0.1/login || true)"
if [[ "$CODE" == 200 || "$CODE" == 301 ]]; then info "The sign-in page answers ($CODE)."; else warn "The sign-in page answered $CODE — see $APP_DIR/storage/logs and /var/log/nginx/error.log"; fi
UP="$(curl -s -o /dev/null -w '%{http_code}' -H "Host: $DOMAIN" http://127.0.0.1/up || true)"
[[ "$UP" == 200 || "$UP" == 301 ]] && info "Health check /up: OK." || warn "Health check /up answered $UP."
[[ -n "$RESTORE_DIR" ]] && rm -rf "$RESTORE_DIR"

URL="$(env_value APP_URL "$APP_DIR/.env")"
CRED=/root/olt-panel-install.txt
if [[ "$UPDATING" != yes ]]; then
    {
        echo "OLT Panel — installed $(date '+%Y-%m-%d %H:%M')"
        echo "URL:             $URL"
        [[ -z "$RESTORE" ]] && echo "Admin username:  $ADMIN_USER"
        [[ -z "$RESTORE" && "${SHOW_PASS:-}" == yes ]] && echo "Admin password:  $ADMIN_PASS"
        echo "App folder:      $APP_DIR"
        echo "Database:        $DB_NAME (user $DB_USER — password in $APP_DIR/.env)"
    } > "$CRED"
    chmod 600 "$CRED"
fi

echo
echo "${B}${G}Done.${N} ${B}The panel is ready at $URL${N}"
if [[ "$UPDATING" == yes ]]; then
    echo "    Updated to $(git -C "$APP_DIR" log -1 --format='%h %s')${PREV_VERSION:+ (was $PREV_VERSION)}."
    [[ -n "$BACKUP_FILE" ]] && echo "    If something is wrong:  sudo bash $APP_DIR/deploy/rollback.sh $BACKUP_FILE"
    [[ "${SHOW_PASS:-}" == yes ]] && echo "    New admin: $ADMIN_USER / $ADMIN_PASS   (change it after signing in)"
elif [[ -n "$RESTORE" ]]; then
    echo "    Sign in with the same usernames and passwords as on the old server."
else
    echo "    Sign in as: $ADMIN_USER"
    [[ "${SHOW_PASS:-}" == yes ]] && echo "    Password:   $ADMIN_PASS   (also saved in $CRED — change it after signing in)"
fi
echo "    Next: Settings → Email (SMTP), Telegram alerts, then add OLTs, switches and routers."
echo "    Update later with:  sudo bash $APP_DIR/install.sh"
