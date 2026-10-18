#!/usr/bin/env bash
# =============================================================================
#  Hydra Panel – Installer · Debian 12/13 · Ubuntu 22.04/24.04
#
#  Installiert alles, was das Panel braucht, auf einem leeren Server:
#  PHP-FPM, MariaDB, Redis, Caddy (HTTPS per Let's Encrypt), Composer, Node.js,
#  baut das Frontend, richtet Datenbank, Queue-Worker, Cronjob und den ersten
#  Admin ein.
#
#  Installation (aus dem Hydra-Repository heraus):
#    sudo bash installer/panel-install.sh
#
#  Installation direkt aus einem Git-Repository:
#    sudo bash panel-install.sh --source https://github.com/<user>/hydra.git --ref main
#
#  Update: dasselbe Kommando erneut ausführen oder
#    sudo hydra-panel update
#  (.env, hochgeladene Logos und alle Daten bleiben erhalten)
#
#  Nicht-interaktiv:
#    sudo HYDRA_ADMIN_PASSWORD='…' bash installer/panel-install.sh --yes \
#      --domain panel.example.com --email admin@example.com --admin admin
#
#  Weitere Optionen: --help
# =============================================================================
set -Eeuo pipefail

INSTALL_DIR=/var/www/hydra
CONF_DIR=/etc/hydra
CONF_FILE=$CONF_DIR/panel.conf
CADDY_SITE=$CONF_DIR/panel.caddy
CREDENTIALS_FILE=/root/hydra-panel-credentials.txt
QUEUE_SERVICE=hydra-queue
NODE_MAJOR=22
YARN_VERSION=1.22.22
WEB_USER=www-data

SCRIPT_PATH="$(readlink -f "${BASH_SOURCE[0]}")"
ORIGINAL_ARGS=("$@")
SCRIPT_DIR="$(dirname "$SCRIPT_PATH")"

DOMAIN=""
EMAIL=""
ADMIN_USER=""
ADMIN_PASSWORD="${HYDRA_ADMIN_PASSWORD:-}"
TIMEZONE=""
SOURCE=""
REF=""
SSL=""              # leer = automatisch (Domain → HTTPS, IP → HTTP)
ASSUME_YES=0
MODE=install        # install | update | status

# ------------------------------------------------------------------ Ausgabe

if [[ -t 1 ]]; then
  C_G=$'\e[32m'; C_Y=$'\e[33m'; C_R=$'\e[31m'; C_B=$'\e[1m'; C_D=$'\e[2m'; C_0=$'\e[0m'
else
  C_G=""; C_Y=""; C_R=""; C_B=""; C_D=""; C_0=""
fi
step() { printf '\n%s==>%s %s%s%s\n' "$C_G" "$C_0" "$C_B" "$*" "$C_0"; }
info() { printf '    %s\n' "$*"; }
warn() { printf '%s  ! %s%s\n' "$C_Y" "$*" "$C_0"; }
die()  { printf '%s  ✗ %s%s\n' "$C_R" "$*" "$C_0" >&2; exit 1; }
trap 'die "Abbruch in Zeile $LINENO: $BASH_COMMAND"' ERR

ask() { # ask "Frage" "Standard" -> Antwort
  local answer
  if (( ASSUME_YES )); then echo "$2"; return; fi
  read -r -p "    $1 ${C_D}[$2]${C_0}: " answer </dev/tty
  echo "${answer:-$2}"
}

confirm() { # confirm "Frage" -> 0 = ja
  local answer
  (( ASSUME_YES )) && return 0
  read -r -p "    $1 [J/n]: " answer </dev/tty
  [[ -z "$answer" || "$answer" =~ ^[JjYy] ]]
}

random_string() { # random_string <länge>
  tr -dc 'A-Za-z0-9' </dev/urandom | head -c "$1" || true
}

usage() {
  cat <<EOF
Hydra Panel – Installer

Aufruf: sudo bash panel-install.sh [Befehl] [Optionen]

Befehle:
  install            Installieren bzw. aktualisieren (Standard)
  update             Nur aktualisieren (nutzt die gespeicherten Einstellungen)
  status             Zustand aller Dienste anzeigen

Optionen:
  --domain <fqdn>    Domain des Panels, z. B. panel.example.com (oder IP-Adresse)
  --email <mail>     E-Mail für Let's Encrypt und den ersten Admin
  --admin <name>     Benutzername des ersten Admins (Passwort: HYDRA_ADMIN_PASSWORD)
  --timezone <tz>    Zeitzone, Standard: Europe/Berlin
  --source <quelle>  Lokales Verzeichnis oder Git-URL des Hydra-Repositorys
                     (Standard: das Repository, aus dem dieses Skript stammt)
  --ref <ref>        Git-Branch oder -Tag (nur mit Git-URL), Standard: main
  --ssl / --no-ssl   HTTPS über Let's Encrypt erzwingen bzw. abschalten
  --yes              Keine Rückfragen, Standardwerte verwenden
  --help             Diese Hilfe
EOF
}

# ------------------------------------------------------------------ Argumente

while (( $# )); do
  case "$1" in
    install|update|status) MODE="$1" ;;
    --domain) DOMAIN="${2:-}"; shift ;;
    --email) EMAIL="${2:-}"; shift ;;
    --admin) ADMIN_USER="${2:-}"; shift ;;
    --timezone) TIMEZONE="${2:-}"; shift ;;
    --source) SOURCE="${2:-}"; shift ;;
    --ref) REF="${2:-}"; shift ;;
    --ssl) SSL=1 ;;
    --no-ssl) SSL=0 ;;
    --yes|-y) ASSUME_YES=1 ;;
    --help|-h) usage; exit 0 ;;
    *) die "Unbekannte Option: $1 (siehe --help)" ;;
  esac
  shift
done

[[ $EUID -eq 0 ]] || die "Bitte als root ausführen (sudo)."

# Gespeicherte Einstellungen einer früheren Installation laden (Update).
if [[ -f "$CONF_FILE" ]]; then
  # shellcheck disable=SC1090
  source "$CONF_FILE"
  DOMAIN="${DOMAIN:-${HYDRA_DOMAIN:-}}"
  EMAIL="${EMAIL:-${HYDRA_EMAIL:-}}"
  TIMEZONE="${TIMEZONE:-${HYDRA_TIMEZONE:-}}"
  SOURCE="${SOURCE:-${HYDRA_SOURCE:-}}"
  REF="${REF:-${HYDRA_REF:-}}"
  SSL="${SSL:-${HYDRA_SSL:-}}"
  INSTALLED=1
else
  INSTALLED=0
fi

# ------------------------------------------------------------------ Status

if [[ "$MODE" == status ]]; then
  for unit in caddy mariadb redis-server "$QUEUE_SERVICE" $(systemctl list-unit-files 'php*-fpm.service' --no-legend 2>/dev/null | awk '{print $1}'); do
    printf '  %-28s %s\n' "$unit" "$(systemctl is-active "$unit" 2>/dev/null || true)"
  done
  [[ -f "$INSTALL_DIR/config/app.php" ]] && printf '  %-28s %s\n' "Version" "$(cd "$INSTALL_DIR" && php artisan --version 2>/dev/null | head -n1)"
  exit 0
fi

if [[ "$MODE" == update ]]; then
  (( INSTALLED )) || die "Hydra Panel ist noch nicht installiert – zuerst ohne 'update' ausführen."
  ASSUME_YES=1
fi

# ------------------------------------------------------------------ System prüfen

step "System prüfen"
[[ -r /etc/os-release ]] || die "/etc/os-release fehlt – unbekanntes Betriebssystem."
# shellcheck disable=SC1091
. /etc/os-release
case "${ID:-}:${VERSION_ID:-}" in
  debian:12|debian:13|ubuntu:22.04|ubuntu:24.04) info "${PRETTY_NAME} ok" ;;
  *) warn "${PRETTY_NAME:-Unbekanntes System} wird nicht offiziell unterstützt (Debian 12/13, Ubuntu 22.04/24.04)."
     confirm "Trotzdem fortfahren?" || exit 1 ;;
esac
case "$(dpkg --print-architecture)" in
  amd64|arm64) ;;
  *) die "Architektur $(dpkg --print-architecture) wird nicht unterstützt (amd64 oder arm64)." ;;
esac
MEM_MB=$(awk '/MemTotal/ {print int($2/1024)}' /proc/meminfo)
info "Arbeitsspeicher: ${MEM_MB} MB"

# ------------------------------------------------------------------ Quelle ermitteln

if [[ -z "$SOURCE" ]]; then
  if [[ -f "$SCRIPT_DIR/../panel/artisan" ]]; then
    SOURCE="$(cd "$SCRIPT_DIR/.." && pwd)"
  else
    die "Hydra-Quelle unbekannt. Bitte --source <Verzeichnis|Git-URL> angeben."
  fi
fi
REF="${REF:-main}"

# ------------------------------------------------------------------ Fragen (nur Neuinstallation)

if (( ! INSTALLED )); then
  step "Einstellungen"
  DEFAULT_DOMAIN="$(hostname -f 2>/dev/null || hostname)"
  [[ "$DEFAULT_DOMAIN" == *.* ]] || DEFAULT_DOMAIN="$(curl -fsS4 --max-time 5 https://api.ipify.org 2>/dev/null || hostname -I | awk '{print $1}')"
  [[ -n "$DOMAIN" ]] || DOMAIN="$(ask "Domain des Panels (oder IP-Adresse)" "$DEFAULT_DOMAIN")"
  [[ -n "$EMAIL" ]] || EMAIL="$(ask "E-Mail-Adresse (Let's Encrypt & Admin)" "admin@${DOMAIN}")"
  [[ -n "$ADMIN_USER" ]] || ADMIN_USER="$(ask "Benutzername des Admins" "admin")"
  [[ -n "$TIMEZONE" ]] || TIMEZONE="$(ask "Zeitzone" "Europe/Berlin")"
fi
TIMEZONE="${TIMEZONE:-Europe/Berlin}"
[[ -n "$DOMAIN" ]] || die "Keine Domain angegeben."

if [[ -z "$SSL" ]]; then
  if [[ "$DOMAIN" =~ ^[0-9.]+$ || "$DOMAIN" == *:* ]]; then SSL=0; else SSL=1; fi
fi
if (( SSL )); then APP_URL="https://$DOMAIN"; else APP_URL="http://$DOMAIN"; fi

if (( SSL )) && (( ! INSTALLED )); then
  PUBLIC_IP="$(curl -fsS4 --max-time 5 https://api.ipify.org 2>/dev/null || true)"
  DNS_IP="$(getent ahostsv4 "$DOMAIN" 2>/dev/null | awk 'NR==1 {print $1}' || true)"
  if [[ -n "$PUBLIC_IP" && "$DNS_IP" != "$PUBLIC_IP" ]]; then
    warn "$DOMAIN zeigt auf ${DNS_IP:-nichts}, dieser Server hat aber $PUBLIC_IP."
    warn "Ohne passenden A-Record kann kein Let's-Encrypt-Zertifikat ausgestellt werden."
    confirm "Trotzdem fortfahren?" || exit 1
  fi
fi

info "Panel-URL:  $APP_URL"
info "Quelle:     $SOURCE$( [[ -d "$SOURCE" ]] || echo " ($REF)" )"
info "Verzeichnis: $INSTALL_DIR"
(( INSTALLED )) || confirm "Installation starten?" || exit 1

# ------------------------------------------------------------------ Pakete

export DEBIAN_FRONTEND=noninteractive
CADDY_LIST=/etc/apt/sources.list.d/caddy-stable.list

# Das Caddy-Repository bei Cloudsmith fällt gelegentlich aus. apt bricht dann komplett ab –
# deshalb wird die Quelle in dem Fall abgeschaltet und Caddy aus dem GitHub-Release installiert.
apt_update() {
  apt-get update -qq && return 0
  if [[ -f "$CADDY_LIST" ]]; then
    warn "Paketquellen konnten nicht aktualisiert werden – vermutlich ist das Caddy-Repository gestört."
    warn "Es wird abgeschaltet ($CADDY_LIST.disabled); Caddy kommt künftig aus den GitHub-Releases."
    mv -f "$CADDY_LIST" "$CADDY_LIST.disabled"
    apt-get update -qq && return 0
  fi
  return 1
}

# Installiert bzw. aktualisiert Caddy aus dem offiziellen GitHub-Release (.deb, SHA-512 geprüft).
install_caddy_github() {
  local arch tag ver deb tmp
  arch="$(dpkg --print-architecture)"
  tag="$(curl -fsSL https://api.github.com/repos/caddyserver/caddy/releases/latest | sed -n 's/.*"tag_name": *"\(v[^"]*\)".*/\1/p' | head -n1 || true)"
  [[ -n "$tag" ]] || die "Aktuelle Caddy-Version konnte nicht ermittelt werden (GitHub nicht erreichbar?)."
  ver="${tag#v}"
  if command -v caddy >/dev/null && [[ "$(caddy version | awk '{print $1}')" == "$tag" ]]; then
    return 0
  fi
  info "Installiere Caddy $ver aus dem GitHub-Release …"
  deb="caddy_${ver}_linux_${arch}.deb"
  tmp="$(mktemp -d)"
  curl -fsSL -o "$tmp/$deb" "https://github.com/caddyserver/caddy/releases/download/$tag/$deb" \
    || { rm -rf "$tmp"; die "Download von $deb fehlgeschlagen."; }
  curl -fsSL -o "$tmp/checksums.txt" "https://github.com/caddyserver/caddy/releases/download/$tag/caddy_${ver}_checksums.txt" \
    || { rm -rf "$tmp"; die "Prüfsummen für Caddy $ver konnten nicht geladen werden."; }
  (cd "$tmp" && grep " $deb\$" checksums.txt | sha512sum -c --status) \
    || { rm -rf "$tmp"; die "Prüfsumme von $deb stimmt nicht – Installation abgebrochen."; }
  dpkg -i "$tmp/$deb" >/dev/null || { rm -rf "$tmp"; die "Caddy-Paket konnte nicht installiert werden."; }
  rm -rf "$tmp"
}

step "Pakete installieren"
apt_update || die "apt-get update fehlgeschlagen."
apt-get install -y -qq curl ca-certificates gnupg lsb-release apt-transport-https rsync git tar unzip cron >/dev/null
info "Basis-Pakete ok"

# PHP: Die Distributionsversion reicht ab PHP 8.2 (Debian 12: 8.2, Debian 13: 8.4, Ubuntu 24.04: 8.3).
# Ubuntu 22.04 bringt nur 8.1 mit – dort kommt PHP 8.3 aus dem PPA von Ondřej Surý.
if [[ "$ID:$VERSION_ID" == ubuntu:22.04 ]]; then
  if ! grep -rqs "ondrej/php" /etc/apt/sources.list.d/; then
    info "Füge PPA ondrej/php hinzu …"
    apt-get install -y -qq software-properties-common >/dev/null
    LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php >/dev/null
    apt_update || die "apt-get update fehlgeschlagen."
  fi
  PHP_VER=8.3
else
  PHP_VER="$(apt-cache depends php-cli 2>/dev/null | sed -n 's/.*Depends: php\([0-9.]*\)-cli.*/\1/p' | head -n1)"
  [[ -n "$PHP_VER" ]] || die "PHP-Version der Distribution konnte nicht ermittelt werden."
fi
apt-get install -y -qq "php${PHP_VER}-cli" "php${PHP_VER}-fpm" "php${PHP_VER}-common" "php${PHP_VER}-gd" \
  "php${PHP_VER}-mysql" "php${PHP_VER}-mbstring" "php${PHP_VER}-bcmath" "php${PHP_VER}-xml" \
  "php${PHP_VER}-curl" "php${PHP_VER}-zip" "php${PHP_VER}-intl" >/dev/null
PHP_BIN="/usr/bin/php${PHP_VER}"
PHP_FPM_SOCK="/run/php/php${PHP_VER}-fpm.sock"
info "PHP $("$PHP_BIN" -r 'echo PHP_VERSION;') ok"

apt-get install -y -qq mariadb-server redis-server >/dev/null
systemctl enable --now mariadb redis-server >/dev/null 2>&1
info "MariaDB und Redis ok"

if ! command -v composer >/dev/null; then
  info "Installiere Composer …"
  tmp="$(mktemp -d)"
  EXPECTED="$(curl -fsSL https://composer.github.io/installer.sig)"
  curl -fsSL -o "$tmp/composer-setup.php" https://getcomposer.org/installer
  ACTUAL="$("$PHP_BIN" -r "echo hash_file('sha384', '$tmp/composer-setup.php');")"
  [[ "$EXPECTED" == "$ACTUAL" ]] || { rm -rf "$tmp"; die "Prüfsumme des Composer-Installers stimmt nicht."; }
  "$PHP_BIN" "$tmp/composer-setup.php" --quiet --install-dir=/usr/local/bin --filename=composer
  rm -rf "$tmp"
fi
info "Composer $(COMPOSER_ALLOW_SUPERUSER=1 composer --version 2>/dev/null | awk '{print $3}') ok"

NODE_OK=0
if command -v node >/dev/null; then
  (( $(node -p 'process.versions.node.split(".")[0]') >= NODE_MAJOR )) && NODE_OK=1
fi
if (( ! NODE_OK )); then
  info "Installiere Node.js $NODE_MAJOR (NodeSource) …"
  curl -fsSL "https://deb.nodesource.com/setup_${NODE_MAJOR}.x" | bash - >/dev/null
  apt-get install -y -qq nodejs >/dev/null
fi
if ! command -v yarn >/dev/null || [[ "$(yarn --version 2>/dev/null)" != "$YARN_VERSION" ]]; then
  npm install -g --silent "yarn@$YARN_VERSION" >/dev/null 2>&1
fi
info "Node.js $(node -v), Yarn $(yarn --version) ok"

if [[ -f "$CADDY_LIST.disabled" ]]; then
  install_caddy_github
elif ! command -v caddy >/dev/null; then
  info "Installiere Caddy (offizielles Repository) …"
  curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor --yes -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
  curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' > "$CADDY_LIST"
  if ! apt_update || ! apt-get install -y -qq caddy >/dev/null 2>&1; then
    warn "Caddy-Repository nicht nutzbar – weiche auf das GitHub-Release aus."
    [[ -f "$CADDY_LIST" ]] && mv -f "$CADDY_LIST" "$CADDY_LIST.disabled"
    install_caddy_github
  fi
fi
info "Caddy $(caddy version | awk '{print $1}') ok"

# ------------------------------------------------------------------ Quellcode

step "Hydra Panel bereitstellen"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"; die "Abbruch in Zeile $LINENO: $BASH_COMMAND"' ERR
if [[ -d "$SOURCE" ]]; then
  [[ -f "$SOURCE/panel/artisan" ]] || die "$SOURCE enthält kein Hydra-Repository (panel/artisan fehlt)."
  PANEL_SRC="$SOURCE/panel"
else
  info "Lade $SOURCE ($REF) …"
  git clone -q --depth 1 --branch "$REF" "$SOURCE" "$WORK/repo" || die "git clone fehlgeschlagen."
  PANEL_SRC="$WORK/repo/panel"
fi

# Bringt die Quelle einen neueren Installer mit (typisch bei "hydra-panel update"), übernimmt der.
NEW_INSTALLER="$(dirname "$PANEL_SRC")/installer/panel-install.sh"
if [[ -z "${HYDRA_REEXEC:-}" && -f "$NEW_INSTALLER" ]] && ! cmp -s "$NEW_INSTALLER" "$SCRIPT_PATH"; then
  info "Neuere Installer-Version gefunden – starte sie"
  cp "$NEW_INSTALLER" "$WORK/panel-install.sh"
  HYDRA_REEXEC=1 exec bash "$WORK/panel-install.sh" "${ORIGINAL_ARGS[@]}" --source "$SOURCE" --ref "$REF"
fi

if (( INSTALLED )) && [[ -f "$INSTALL_DIR/artisan" ]]; then
  info "Wartungsmodus an"
  (cd "$INSTALL_DIR" && runuser -u "$WEB_USER" -- "$PHP_BIN" artisan down >/dev/null 2>&1 || true)
fi

# Programmdateien abgleichen. Nutzerdaten (.env, storage/, hochgeladene Dateien) bleiben unberührt.
install -d -m 755 "$INSTALL_DIR"
rsync -a --delete \
  --exclude '.env' --exclude '/storage/' --exclude '/vendor/' --exclude '/node_modules/' \
  --exclude '/public/assets/' --exclude '/bootstrap/cache/*.php' \
  "$PANEL_SRC/" "$INSTALL_DIR/"
# storage/ beim ersten Mal komplett anlegen, später nur fehlende Ordner ergänzen.
rsync -a --ignore-existing "$PANEL_SRC/storage/" "$INSTALL_DIR/storage/"
info "Dateien nach $INSTALL_DIR kopiert"

cd "$INSTALL_DIR"
info "PHP-Abhängigkeiten (composer install) …"
COMPOSER_ALLOW_SUPERUSER=1 "$PHP_BIN" /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction --quiet

# webpack braucht beim Bauen gut 1,5 GB Speicher – auf kleinen Servern hilft vorübergehend Swap.
TEMP_SWAP=""
SWAP_MB=$(awk '/SwapTotal/ {print int($2/1024)}' /proc/meminfo)
if (( MEM_MB + SWAP_MB < 3000 )); then
  TEMP_SWAP=/hydra-build.swap
  info "Wenig Arbeitsspeicher – lege vorübergehend 2 GB Swap an"
  fallocate -l 2G "$TEMP_SWAP" 2>/dev/null || dd if=/dev/zero of="$TEMP_SWAP" bs=1M count=2048 status=none
  chmod 600 "$TEMP_SWAP" && mkswap "$TEMP_SWAP" >/dev/null && swapon "$TEMP_SWAP"
fi
info "Frontend bauen (yarn install & build) – das dauert ein paar Minuten …"
install -d public/assets
yarn install --frozen-lockfile --silent --non-interactive >/dev/null 2>&1 || yarn install --frozen-lockfile --non-interactive
NODE_OPTIONS=--max-old-space-size=2048 yarn run build:production >/dev/null 2>&1 \
  || { NODE_OPTIONS=--max-old-space-size=2048 yarn run build:production; die "Frontend-Build fehlgeschlagen."; }
if [[ -n "$TEMP_SWAP" ]]; then
  swapoff "$TEMP_SWAP" && rm -f "$TEMP_SWAP"
fi
rm -rf "$INSTALL_DIR/node_modules"
info "Frontend gebaut"

# ------------------------------------------------------------------ Datenbank & .env

set_env() { # set_env KEY VALUE – setzt oder ergänzt einen Eintrag in der .env
  local key="$1" value="$2"
  if [[ "$value" =~ [[:space:]\#\"\'] ]]; then value="\"${value//\"/\\\"}\""; fi
  if grep -q "^${key}=" .env; then
    local escaped
    escaped="$(printf '%s' "$value" | sed -e 's/[\/&|]/\\&/g')"
    sed -i "s|^${key}=.*|${key}=${escaped}|" .env
  else
    printf '%s=%s\n' "$key" "$value" >> .env
  fi
}

if [[ ! -f .env ]]; then
  step "Datenbank und Konfiguration einrichten"
  DB_PASSWORD="$(random_string 32)"
  mariadb -u root <<SQL
CREATE DATABASE IF NOT EXISTS hydra CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'hydra'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER 'hydra'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON hydra.* TO 'hydra'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
  info "Datenbank 'hydra' angelegt"

  cp .env.example .env
  set_env APP_URL "$APP_URL"
  set_env APP_TIMEZONE "$TIMEZONE"
  set_env APP_ENVIRONMENT_ONLY false
  set_env APP_SERVICE_AUTHOR "$EMAIL"
  set_env DB_DATABASE hydra
  set_env DB_USERNAME hydra
  set_env DB_PASSWORD "$DB_PASSWORD"
  set_env CACHE_DRIVER redis
  set_env SESSION_DRIVER redis
  set_env QUEUE_CONNECTION redis
  set_env HASHIDS_SALT "$(random_string 20)"
  set_env MAIL_FROM_ADDRESS "no-reply@${DOMAIN}"
  set_env MAIL_EHLO_DOMAIN "$DOMAIN"
  "$PHP_BIN" artisan key:generate --force --no-interaction >/dev/null
  info ".env erstellt (APP_KEY gesetzt – bitte zusammen mit der Datenbank sichern!)"
  NEW_INSTALL=1
else
  set_env APP_URL "$APP_URL"
  NEW_INSTALL=0
fi

step "Datenbank migrieren"
"$PHP_BIN" artisan migrate --seed --force --no-interaction | sed 's/^/    /' | tail -n 3
"$PHP_BIN" artisan view:clear >/dev/null
"$PHP_BIN" artisan config:clear >/dev/null
"$PHP_BIN" artisan optimize:clear >/dev/null 2>&1 || true

if (( NEW_INSTALL )); then
  step "Admin anlegen"
  ADMIN_PASSWORD="${ADMIN_PASSWORD:-$(random_string 20)}"
  "$PHP_BIN" artisan p:user:make --no-interaction --admin=1 --email="$EMAIL" --username="$ADMIN_USER" \
    --name-first="Admin" --name-last="Hydra" --password="$ADMIN_PASSWORD" >/dev/null
  umask 077
  cat > "$CREDENTIALS_FILE" <<EOF
Hydra Panel – Zugangsdaten ($(date '+%Y-%m-%d %H:%M'))

Panel:     $APP_URL
Benutzer:  $ADMIN_USER
E-Mail:    $EMAIL
Passwort:  $ADMIN_PASSWORD

Datenbank: hydra / Benutzer hydra / Passwort in $INSTALL_DIR/.env
EOF
  umask 022
  info "Admin '$ADMIN_USER' angelegt (Zugangsdaten: $CREDENTIALS_FILE)"
fi

# ------------------------------------------------------------------ Rechte, PHP, Dienste

step "Dienste einrichten"
chown -R "$WEB_USER:$WEB_USER" "$INSTALL_DIR"
chmod -R u+rwX,g+rX,o-rwx "$INSTALL_DIR/storage" "$INSTALL_DIR/bootstrap/cache"
chmod 640 "$INSTALL_DIR/.env"

cat > "/etc/php/$PHP_VER/fpm/conf.d/99-hydra.ini" <<'EOF'
; Hydra Panel – Uploads bis 100 MB (Dateimanager, Logos)
upload_max_filesize = 100M
post_max_size = 100M
memory_limit = 512M
max_execution_time = 300
EOF
systemctl enable "php${PHP_VER}-fpm" >/dev/null 2>&1
systemctl restart "php${PHP_VER}-fpm"
info "PHP-FPM $PHP_VER ok"

cat > /etc/cron.d/hydra-panel <<EOF
# Hydra Panel – Laravel-Scheduler (Zeitpläne, Aufräumarbeiten)
* * * * * $WEB_USER $PHP_BIN $INSTALL_DIR/artisan schedule:run >/dev/null 2>&1
EOF
chmod 644 /etc/cron.d/hydra-panel
info "Cronjob ok"

cat > "/etc/systemd/system/$QUEUE_SERVICE.service" <<EOF
[Unit]
Description=Hydra Panel Queue Worker
After=redis-server.service mariadb.service

[Service]
User=$WEB_USER
Group=$WEB_USER
Restart=always
ExecStart=$PHP_BIN $INSTALL_DIR/artisan queue:work --queue=high,standard,low --sleep=3 --tries=3
StartLimitInterval=180
StartLimitBurst=30
RestartSec=5s

[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload
systemctl enable "$QUEUE_SERVICE" >/dev/null 2>&1
systemctl restart "$QUEUE_SERVICE"
info "Queue-Worker ($QUEUE_SERVICE) ok"

install -d -m 755 "$CONF_DIR"
if (( SSL )); then SITE_ADDRESS="$DOMAIN"; else SITE_ADDRESS="http://$DOMAIN, http://:80"; fi
cat > "$CADDY_SITE" <<EOF
# Hydra Panel – wird vom Installer erzeugt, Änderungen gehen beim Update verloren.
$SITE_ADDRESS {
	root * $INSTALL_DIR/public
	encode zstd gzip

	request_body {
		max_size 100MB
	}

	header {
		-Server
		X-Content-Type-Options nosniff
		X-Frame-Options DENY
		Referrer-Policy same-origin
		Strict-Transport-Security "max-age=31536000"
	}

	php_fastcgi unix/$PHP_FPM_SOCK {
		env HTTP_PROXY ""
	}
	file_server
}
EOF
CADDY_MAIN=/etc/caddy/Caddyfile
IMPORT_LINE="import $CADDY_SITE"
if ! grep -qxF "$IMPORT_LINE" "$CADDY_MAIN" 2>/dev/null; then
  if [[ -f "$CADDY_MAIN" ]] && grep -qv '^\s*\(#.*\)\?$' "$CADDY_MAIN"; then
    cp "$CADDY_MAIN" "$CADDY_MAIN.bak.$(date +%Y%m%d%H%M%S)"
    if grep -q '^:80\b' "$CADDY_MAIN"; then
      info "Ersetze die Caddy-Standardseite (Sicherung angelegt)"
      printf '{\n\temail %s\n}\n\n%s\n' "$EMAIL" "$IMPORT_LINE" > "$CADDY_MAIN"
    else
      info "Bestehende Caddy-Konfiguration bleibt erhalten, Hydra wird ergänzt"
      printf '\n%s\n' "$IMPORT_LINE" >> "$CADDY_MAIN"
    fi
  else
    printf '{\n\temail %s\n}\n\n%s\n' "$EMAIL" "$IMPORT_LINE" > "$CADDY_MAIN"
  fi
fi
caddy validate --config "$CADDY_MAIN" --adapter caddyfile >/dev/null 2>&1 \
  || die "Caddy-Konfiguration ungültig: caddy validate --config $CADDY_MAIN"
usermod -aG "$WEB_USER" caddy
systemctl enable caddy >/dev/null 2>&1
systemctl restart caddy
info "Caddy ok"

if command -v ufw >/dev/null && ufw status | grep -q "Status: active"; then
  ufw allow 80/tcp >/dev/null && ufw allow 443/tcp >/dev/null
  info "Firewall (ufw): Ports 80 und 443 freigegeben"
fi

# ------------------------------------------------------------------ Abschluss

(cd "$INSTALL_DIR" && runuser -u "$WEB_USER" -- "$PHP_BIN" artisan up >/dev/null 2>&1 || true)
runuser -u "$WEB_USER" -- "$PHP_BIN" "$INSTALL_DIR/artisan" queue:restart >/dev/null 2>&1 || true

cat > "$CONF_FILE" <<EOF
# Hydra Panel – vom Installer gespeicherte Einstellungen (für Updates)
HYDRA_DOMAIN=$(printf '%q' "$DOMAIN")
HYDRA_EMAIL=$(printf '%q' "$EMAIL")
HYDRA_TIMEZONE=$(printf '%q' "$TIMEZONE")
HYDRA_SOURCE=$(printf '%q' "$SOURCE")
HYDRA_REF=$(printf '%q' "$REF")
HYDRA_SSL=$SSL
EOF
chmod 600 "$CONF_FILE"

# Das Skript selbst als "hydra-panel" bereitstellen (update/status).
[[ "$SCRIPT_PATH" == /usr/local/bin/hydra-panel ]] || install -m 755 "$SCRIPT_PATH" /usr/local/bin/hydra-panel
rm -rf "$WORK"

step "Fertig"
VERSION="$(sed -n "s/.*'version' => '\(.*\)',.*/\1/p" "$INSTALL_DIR/config/app.php" | head -n1)"
info "Hydra Panel $VERSION läuft unter ${C_B}$APP_URL${C_0}"
if (( NEW_INSTALL )); then
  info "Admin:    $ADMIN_USER / $ADMIN_PASSWORD"
  info "          (gespeichert in $CREDENTIALS_FILE)"
fi
cat <<EOF

    Nützliche Befehle:
      hydra-panel update                 Panel aktualisieren
      hydra-panel status                 Zustand der Dienste
      journalctl -u caddy -f             HTTPS-/Zertifikats-Log
      journalctl -u $QUEUE_SERVICE -f        Queue-Worker-Log
      tail -f $INSTALL_DIR/storage/logs/laravel-*.log

    Nächster Schritt: Im Adminbereich unter "Locations" und "Nodes" einen Node anlegen
    und auf dem Node-Server installer/wings-install.sh ausführen.
EOF
