#!/usr/bin/env bash
# =============================================================================
#  Hydra Wings – Installer · Debian 12/13 · Ubuntu 22.04/24.04
#
#  Richtet einen Node für das Hydra Panel ein: Docker, Hydra Wings (aus dem
#  Quellcode gebaut oder als fertige Binary), systemd-Dienst, optional ein
#  Let's-Encrypt-Zertifikat und die automatische Verbindung zum Panel.
#
#  Installation direkt von GitHub (lädt den Quellcode selbst):
#    bash <(curl -fsSL https://raw.githubusercontent.com/WyvernLabs/hydra/main/installer/wings-install.sh)
#
#  Installation aus dem Hydra-Repository heraus:
#    sudo bash installer/wings-install.sh --fqdn node1.example.com
#
#  Private Repositorys brauchen ein Token mit Lesezugriff: --git-token <token>
#
#  Mit automatischer Konfiguration (Token aus Admin > Nodes > Configuration):
#    sudo bash installer/wings-install.sh --fqdn node1.example.com --email admin@example.com \
#      --panel-url https://panel.example.com --token <token> --node <id>
#
#  Update: dasselbe Kommando erneut ausführen oder
#    sudo hydra-node update
#
#  Weitere Optionen: --help
# =============================================================================
set -Eeuo pipefail
# Systembefehle (apt-cache, systemctl, free …) auf Englisch, damit ihre Ausgabe unabhängig von der
# Serversprache ausgewertet werden kann. Die Meldungen des Installers selbst bleiben deutsch.
export LC_ALL=C.UTF-8 LANG=C.UTF-8
# Ohne Login-Shell (z. B. gestartet vom Panel über systemd-run) fehlt HOME.
export HOME="${HOME:-/root}"

BIN=/usr/local/bin/hydra-wings
CONF_DIR=/etc/hydra
NODE_CONF=$CONF_DIR/node.conf
WINGS_CONFIG=$CONF_DIR/config.yml
SERVICE=hydra-wings
GO_DIR=/opt/hydra/go
HYDRA_REPO=https://github.com/WyvernLabs/hydra.git

SCRIPT_PATH="$(readlink -f "${BASH_SOURCE[0]}")"
SCRIPT_DIR="$(dirname "$SCRIPT_PATH")"
ORIGINAL_ARGS=("$@")

FQDN=""
EMAIL=""
PANEL_URL=""
TOKEN=""
NODE_ID=""
SOURCE=""
GIT_TOKEN="${HYDRA_GIT_TOKEN:-}"
REF=""
BINARY_URL=""
SSL=""              # leer = automatisch (FQDN → Zertifikat, sonst keins)
ALLOW_INSECURE=0
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

# curl mit IPv4-Fallback: Manche Hoster-Netze bekommen von Google (dl.google.com, Ziel von go.dev/dl)
# über IPv6 nur 404 geliefert, über IPv4 klappt der Download.
fetch() {
  curl -fsSL "$@" 2>/dev/null || curl -4 -fsSL "$@"
}

git_clone() { # git_clone <quelle> <ref> <ziel> – mit Token auch aus privaten GitHub-Repositorys
  local auth=()
  if [[ -n "$GIT_TOKEN" && "$1" == https://github.com/* ]]; then
    auth=(-c "http.https://github.com/.extraheader=Authorization: Basic $(printf 'x-access-token:%s' "$GIT_TOKEN" | base64 -w0)")
  fi
  GIT_TERMINAL_PROMPT=0 git "${auth[@]}" clone -q --depth 1 --branch "$2" "$1" "$3"
}

usage() {
  cat <<EOF
Hydra Wings – Installer

Aufruf: sudo bash wings-install.sh [Befehl] [Optionen]

Befehle:
  install              Installieren bzw. aktualisieren (Standard)
  update               Nur Wings neu bauen/laden und neu starten
  status               Zustand von Docker und Wings anzeigen

Optionen:
  --fqdn <domain>      Domain des Nodes, z. B. node1.example.com (für das SSL-Zertifikat)
  --email <mail>       E-Mail für Let's Encrypt
  --ssl / --no-ssl     Zertifikat erzwingen bzw. weglassen (z. B. Panel ohne HTTPS)
  --panel-url <url>    URL des Panels      ┐
  --token <token>      Konfigurations-Token ├ aus Admin > Nodes > <Node> > Configuration
  --node <id>          ID des Nodes         ┘   ("Generate Token")
  --allow-insecure     Zertifikat des Panels beim Konfigurieren nicht prüfen
  --source <quelle>    Lokales Verzeichnis oder Git-URL des Hydra-Repositorys
                       (Standard: das Repository, aus dem dieses Skript stammt)
  --ref <ref>          Git-Branch oder -Tag (nur mit Git-URL), Standard: main
  --binary-url <url>   Fertige hydra-wings-Binary laden statt aus dem Quellcode zu bauen
  --git-token <token>  Token mit Lesezugriff für ein privates GitHub-Repository
                       (wird für Updates in /etc/hydra/node.conf gespeichert)
  --yes                Keine Rückfragen
  --help               Diese Hilfe
EOF
}

# ------------------------------------------------------------------ Argumente

while (( $# )); do
  case "$1" in
    install|update|status) MODE="$1" ;;
    --fqdn) FQDN="${2:-}"; shift ;;
    --email) EMAIL="${2:-}"; shift ;;
    --panel-url) PANEL_URL="${2:-}"; shift ;;
    --token) TOKEN="${2:-}"; shift ;;
    --node) NODE_ID="${2:-}"; shift ;;
    --source) SOURCE="${2:-}"; shift ;;
    --ref) REF="${2:-}"; shift ;;
    --binary-url) BINARY_URL="${2:-}"; shift ;;
    --git-token) GIT_TOKEN="${2:-}"; shift ;;
    --ssl) SSL=1 ;;
    --no-ssl) SSL=0 ;;
    --allow-insecure) ALLOW_INSECURE=1 ;;
    --yes|-y) ASSUME_YES=1 ;;
    --help|-h) usage; exit 0 ;;
    *) die "Unbekannte Option: $1 (siehe --help)" ;;
  esac
  shift
done

[[ $EUID -eq 0 ]] || die "Bitte als root ausführen (sudo)."

if [[ -f "$NODE_CONF" ]]; then
  # shellcheck disable=SC1090
  source "$NODE_CONF"
  FQDN="${FQDN:-${HYDRA_FQDN:-}}"
  EMAIL="${EMAIL:-${HYDRA_EMAIL:-}}"
  SOURCE="${SOURCE:-${HYDRA_SOURCE:-}}"
  REF="${REF:-${HYDRA_REF:-}}"
  BINARY_URL="${BINARY_URL:-${HYDRA_BINARY_URL:-}}"
  GIT_TOKEN="${GIT_TOKEN:-${HYDRA_GIT_TOKEN:-}}"
  SSL="${SSL:-${HYDRA_SSL:-}}"
  INSTALLED=1
else
  INSTALLED=0
fi

if [[ "$MODE" == status ]]; then
  printf '  %-16s %s\n' docker "$(systemctl is-active docker 2>/dev/null || true)"
  printf '  %-16s %s\n' "$SERVICE" "$(systemctl is-active "$SERVICE" 2>/dev/null || true)"
  [[ -x "$BIN" ]] && printf '  %-16s %s\n' Version "$("$BIN" version 2>/dev/null | head -n1)"
  [[ -f "$WINGS_CONFIG" ]] && printf '  %-16s %s\n' Konfiguration "$WINGS_CONFIG" || printf '  %-16s %s\n' Konfiguration "fehlt"
  exit 0
fi

if [[ "$MODE" == update ]]; then
  (( INSTALLED )) || die "Hydra Wings ist noch nicht installiert – zuerst ohne 'update' ausführen."
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
ARCH="$(dpkg --print-architecture)"
case "$ARCH" in
  amd64|arm64) ;;
  *) die "Architektur $ARCH wird nicht unterstützt (amd64 oder arm64)." ;;
esac
VIRT="$(systemd-detect-virt 2>/dev/null || true)"
case "$VIRT" in
  openvz|lxc|lxc-libvirt)
    warn "Virtualisierung '$VIRT' erkannt – Docker läuft dort meist nicht. Empfohlen ist KVM."
    confirm "Trotzdem fortfahren?" || exit 1 ;;
  *) info "Virtualisierung: ${VIRT:-keine} ok" ;;
esac

if [[ -z "$SOURCE" && -z "$BINARY_URL" ]]; then
  if [[ -f "$SCRIPT_DIR/../wings/go.mod" ]]; then
    SOURCE="$(cd "$SCRIPT_DIR/.." && pwd)"
  else
    SOURCE="$HYDRA_REPO"
  fi
fi
REF="${REF:-main}"

# Läuft das Skript ohne Repository daneben (z. B. bash <(curl …) oder hydra-node update mit
# Git-URL), wird zuerst der Quellcode geladen und dessen Installer übernimmt. So kommen Fragen
# und Pakete nur einmal, und es läuft immer der Installer, der zum Quellcode passt.
if [[ -n "$SOURCE" && ! -d "$SOURCE" && -z "$BINARY_URL" && -z "${HYDRA_REEXEC:-}" ]]; then
  if ! command -v git >/dev/null; then
    info "Installiere git …"
    apt-get update -qq && DEBIAN_FRONTEND=noninteractive apt-get install -y -qq git ca-certificates >/dev/null
  fi
  BOOT_DIR="$(mktemp -d)"
  info "Lade $SOURCE ($REF) …"
  git_clone "$SOURCE" "$REF" "$BOOT_DIR/repo" \
    || die "git clone fehlgeschlagen. Ist das Repository privat? Dann --git-token <token> angeben."
  [[ -f "$BOOT_DIR/repo/installer/wings-install.sh" ]] || die "$SOURCE enthält keinen Hydra-Installer."
  export HYDRA_GIT_TOKEN="$GIT_TOKEN"
  HYDRA_REEXEC=1 HYDRA_BOOT_DIR="$BOOT_DIR" exec bash "$BOOT_DIR/repo/installer/wings-install.sh" \
    "${ORIGINAL_ARGS[@]}" --source "$SOURCE" --ref "$REF"
fi

# ------------------------------------------------------------------ Fragen

if (( ! INSTALLED )); then
  step "Einstellungen"
  DEFAULT_FQDN="$(hostname -f 2>/dev/null || hostname)"
  [[ -n "$FQDN" ]] || FQDN="$(ask "Domain des Nodes (leer lassen für kein SSL)" "$DEFAULT_FQDN")"
  if [[ -z "$SSL" ]]; then
    if [[ -n "$FQDN" && "$FQDN" == *.* && ! "$FQDN" =~ ^[0-9.]+$ ]]; then SSL=1; else SSL=0; fi
  fi
  if (( SSL )) && [[ -z "$EMAIL" ]]; then
    EMAIL="$(ask "E-Mail-Adresse für Let's Encrypt" "admin@${FQDN#*.}")"
  fi
  if [[ -z "$PANEL_URL" ]] && (( ! ASSUME_YES )); then
    info "Verbindung zum Panel – Token und Node-ID stehen im Panel unter Admin › Nodes › <Node> ›"
    info "Konfiguration › Generate Token. Leer lassen, um später zu verbinden."
    info "(Noch einfacher: im Panel unter Nodes › <Node> › Installation per SSH installieren lassen.)"
    PANEL_URL="$(ask "Panel-URL, z. B. https://panel.example.com" "")"
    if [[ -n "$PANEL_URL" ]]; then
      [[ "$PANEL_URL" == http://* || "$PANEL_URL" == https://* ]] || PANEL_URL="https://$PANEL_URL"
      PANEL_URL="${PANEL_URL%/}"
      [[ -n "$NODE_ID" ]] || NODE_ID="$(ask "Node-ID" "1")"
      [[ -n "$TOKEN" ]] || TOKEN="$(ask "Token" "")"
      [[ -n "$TOKEN" ]] || { warn "Kein Token angegeben – Wings wird nur installiert."; PANEL_URL=""; }
    fi
  fi
fi
SSL="${SSL:-0}"

if (( SSL )) && (( ! INSTALLED )); then
  PUBLIC_IP="$(curl -fsS4 --max-time 5 https://api.ipify.org 2>/dev/null || true)"
  DNS_IP="$(getent ahostsv4 "$FQDN" 2>/dev/null | awk 'NR==1 {print $1}' || true)"
  if [[ -n "$PUBLIC_IP" && "$DNS_IP" != "$PUBLIC_IP" ]]; then
    warn "$FQDN zeigt auf ${DNS_IP:-nichts}, dieser Server hat aber $PUBLIC_IP."
    warn "Ohne passenden A-Record kann kein Let's-Encrypt-Zertifikat ausgestellt werden."
    confirm "Trotzdem fortfahren?" || exit 1
  fi
fi

info "Node:   ${FQDN:-(ohne Domain)} $( (( SSL )) && echo "mit SSL" || echo "ohne SSL")"
info "Wings:  $( [[ -n "$BINARY_URL" ]] && echo "Binary von $BINARY_URL" || echo "aus dem Quellcode ($SOURCE)")"
(( INSTALLED )) || confirm "Installation starten?" || exit 1

# ------------------------------------------------------------------ Pakete & Docker

export DEBIAN_FRONTEND=noninteractive
step "Pakete installieren"
apt-get update -qq || die "apt-get update fehlgeschlagen."
apt-get install -y -qq curl ca-certificates gnupg tar git rsync ufw >/dev/null
info "Basis-Pakete ok"

if ! command -v docker >/dev/null; then
  info "Installiere Docker (offizielles Repository) …"
  install -m 0755 -d /etc/apt/keyrings
  curl -fsSL "https://download.docker.com/linux/$ID/gpg" -o /etc/apt/keyrings/docker.asc
  chmod a+r /etc/apt/keyrings/docker.asc
  echo "deb [arch=$ARCH signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/$ID ${VERSION_CODENAME} stable" \
    > /etc/apt/sources.list.d/docker.list
  apt-get update -qq || die "apt-get update fehlgeschlagen (Docker-Repository)."
  apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin >/dev/null
fi
systemctl enable --now docker >/dev/null 2>&1
info "Docker $(docker version --format '{{.Server.Version}}' 2>/dev/null || echo '?') ok"

# ------------------------------------------------------------------ Hydra-Images
#
# Laufzeit-Images, die es nicht fertig in einer Registry gibt, baut jeder Node selbst. Die
# Eggs verweisen mit "~hydra/yolks:<tag>" darauf, das "~" sagt Wings, dass es nicht pullen
# soll. Bei jedem Update neu gebaut (--pull), damit Sicherheitsupdates der Basis ankommen.
#
# java_25_graalvm: GraalVM Community für Java 25 (frei nutzbar, laufend aktualisiert) auf
# derselben Ubuntu-Basis wie die Java-Yolks. Ältere GraalVM-CE-Versionen bekommen keine
# Updates mehr, deshalb gibt es nur Java 25.
step "Hydra-Images bauen"
build_yolk() { # build_yolk <tag> <verzeichnis>
  if docker build --pull -q -t "hydra/yolks:$1" "$2" >/dev/null 2>"$2/build.log"; then
    info "hydra/yolks:$1 ok"
  else
    warn "hydra/yolks:$1 konnte nicht gebaut werden – Server mit diesem Image starten nicht."
    tail -n 5 "$2/build.log" | sed 's/^/      /'
  fi
}
YOLK_DIR="$(mktemp -d)"
cat > "$YOLK_DIR/Dockerfile" <<'EOF'
FROM ghcr.io/graalvm/jdk-community:25i-ol9 AS graalvm
RUN cp -a "$JAVA_HOME" /graalvm

FROM ubuntu:24.04
LABEL org.opencontainers.image.description="Hydra yolk: GraalVM Community Java 25"
RUN apt-get update -y \
    && apt-get install -y --no-install-recommends curl lsof ca-certificates openssl git tar sqlite3 \
        fontconfig tzdata iproute2 libfreetype6 tini zip unzip jq \
    && rm -rf /var/lib/apt/lists/* \
    && (userdel -r ubuntu 2>/dev/null || true) \
    && useradd -m -d /home/container -s /bin/bash container
COPY --from=graalvm /graalvm /opt/java/graalvm
ENV JAVA_HOME=/opt/java/graalvm PATH=/opt/java/graalvm/bin:$PATH
USER container
ENV USER=container HOME=/home/container
WORKDIR /home/container
STOPSIGNAL SIGINT
COPY --chown=container:container entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/usr/bin/tini", "-g", "--"]
CMD ["/entrypoint.sh"]
EOF
cat > "$YOLK_DIR/entrypoint.sh" <<'EOF'
#!/bin/bash
TZ=${TZ:-UTC}
export TZ
INTERNAL_IP=$(ip route get 1 | awk '{print $(NF-2);exit}')
export INTERNAL_IP
cd /home/container || exit 1

printf "\033[1m\033[33mcontainer~ \033[0mjava -version\n"
java -version

# "{{VARIABLE}}" der Startzeile in "${VARIABLE}" umwandeln und ausführen.
PARSED=$(echo "$STARTUP" | sed -e 's/{{/${/g' -e 's/}}/}/g')
printf "\033[1m\033[33mcontainer~ \033[0m"
echo "$PARSED"
# shellcheck disable=SC2086
eval "$PARSED"
EOF
build_yolk java_25_graalvm "$YOLK_DIR"
rm -rf "$YOLK_DIR"

# Caddy für die Reverse Proxys der Server (Addon "Reverse Proxy"). Ohne Proxys lauscht Caddy
# auf keinem Port; Wings schreibt die Proxys nach /etc/hydra/caddy/ und lädt Caddy neu.
CADDY_LIST=/etc/apt/sources.list.d/caddy-stable.list
install_caddy_github() {
  local arch tag ver deb tmp
  arch="$(dpkg --print-architecture)"
  tag="$(curl -fsSL https://api.github.com/repos/caddyserver/caddy/releases/latest | sed -n 's/.*"tag_name": *"\(v[^"]*\)".*/\1/p' | head -n1 || true)"
  [[ -n "$tag" ]] || { warn "Aktuelle Caddy-Version konnte nicht ermittelt werden – Reverse Proxys bleiben aus."; return 1; }
  ver="${tag#v}"
  command -v caddy >/dev/null && [[ "$(caddy version | awk '{print $1}')" == "$tag" ]] && return 0
  info "Installiere Caddy $ver aus dem GitHub-Release …"
  deb="caddy_${ver}_linux_${arch}.deb"
  tmp="$(mktemp -d)"
  if curl -fsSL -o "$tmp/$deb" "https://github.com/caddyserver/caddy/releases/download/$tag/$deb" \
    && curl -fsSL -o "$tmp/checksums.txt" "https://github.com/caddyserver/caddy/releases/download/$tag/caddy_${ver}_checksums.txt" \
    && (cd "$tmp" && grep " $deb\$" checksums.txt | sha512sum -c --status) \
    && dpkg -i "$tmp/$deb" >/dev/null; then
    rm -rf "$tmp"
    return 0
  fi
  rm -rf "$tmp"
  warn "Caddy konnte nicht installiert werden – Reverse Proxys bleiben aus."
  return 1
}
if [[ -f "$CADDY_LIST.disabled" ]]; then
  install_caddy_github || true
elif ! command -v caddy >/dev/null; then
  info "Installiere Caddy (offizielles Repository) …"
  curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor --yes -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
  curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' > "$CADDY_LIST"
  if ! apt-get update -qq || ! apt-get install -y -qq caddy >/dev/null 2>&1; then
    warn "Caddy-Repository nicht nutzbar – weiche auf das GitHub-Release aus."
    mv -f "$CADDY_LIST" "$CADDY_LIST.disabled"
    apt-get update -qq || true
    install_caddy_github || true
  fi
fi
if command -v caddy >/dev/null; then
  PROXY_DIR=$CONF_DIR/caddy
  mkdir -p "$PROXY_DIR"
  [[ -f "$PROXY_DIR/proxies.caddy" ]] || echo "# Managed by Hydra (reverse proxies of this node). Changes are overwritten." > "$PROXY_DIR/proxies.caddy"
  CADDY_MAIN=/etc/caddy/Caddyfile
  IMPORT_LINE="import $PROXY_DIR/*.caddy"
  if ! grep -qxF "$IMPORT_LINE" "$CADDY_MAIN" 2>/dev/null; then
    # Die Standardseite der Caddy-Pakete belegt Port 80 und wird ersetzt; eigene Konfigurationen
    # (z. B. das Panel auf demselben Server) bleiben erhalten.
    if [[ -f "$CADDY_MAIN" ]] && grep -qv '^\s*\(#.*\)\?$' "$CADDY_MAIN" && ! grep -q '^:80\b' "$CADDY_MAIN"; then
      cp "$CADDY_MAIN" "$CADDY_MAIN.bak.$(date +%Y%m%d%H%M%S)"
      printf '\n%s\n' "$IMPORT_LINE" >> "$CADDY_MAIN"
    else
      [[ -f "$CADDY_MAIN" ]] && cp "$CADDY_MAIN" "$CADDY_MAIN.bak.$(date +%Y%m%d%H%M%S)"
      if [[ -n "$EMAIL" ]]; then
        printf '{\n\temail %s\n}\n\n%s\n' "$EMAIL" "$IMPORT_LINE" > "$CADDY_MAIN"
      else
        printf '%s\n' "$IMPORT_LINE" > "$CADDY_MAIN"
      fi
    fi
  fi
  if caddy validate --config "$CADDY_MAIN" --adapter caddyfile >/dev/null 2>&1; then
    systemctl enable caddy >/dev/null 2>&1
    systemctl reload-or-restart caddy >/dev/null 2>&1 || systemctl restart caddy >/dev/null 2>&1 || true
    info "Caddy $(caddy version | awk '{print $1}') für Reverse Proxys ok"
  else
    warn "Caddy-Konfiguration ungültig ($CADDY_MAIN) – Reverse Proxys funktionieren erst nach einer Korrektur."
  fi
  # certbot erneuert das Zertifikat von Wings über Port 80; solange Caddy dort Proxys bedient,
  # wird es dafür kurz angehalten.
  mkdir -p /etc/letsencrypt/renewal-hooks/pre /etc/letsencrypt/renewal-hooks/post
  cat > /etc/letsencrypt/renewal-hooks/pre/hydra-caddy.sh <<'HOOK'
#!/bin/sh
# Hydra: Caddy für die Erneuerung über Port 80 anhalten.
if systemctl is-active --quiet caddy; then systemctl stop caddy && touch /run/hydra-caddy-stopped; fi
HOOK
  cat > /etc/letsencrypt/renewal-hooks/post/hydra-caddy.sh <<'HOOK'
#!/bin/sh
# Hydra: Caddy nach der Erneuerung wieder starten.
if [ -f /run/hydra-caddy-stopped ]; then rm -f /run/hydra-caddy-stopped; systemctl start caddy; fi
HOOK
  chmod 755 /etc/letsencrypt/renewal-hooks/pre/hydra-caddy.sh /etc/letsencrypt/renewal-hooks/post/hydra-caddy.sh
fi

# ------------------------------------------------------------------ Wings bauen / laden

step "Hydra Wings bereitstellen"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"; die "Abbruch in Zeile $LINENO: $BASH_COMMAND"' ERR

if [[ -n "$BINARY_URL" ]]; then
  info "Lade $BINARY_URL …"
  curl -fsSL -o "$WORK/hydra-wings" "$BINARY_URL" || die "Download der Binary fehlgeschlagen."
else
  if [[ -d "$SOURCE" ]]; then
    [[ -f "$SOURCE/wings/go.mod" ]] || die "$SOURCE enthält kein Hydra-Repository (wings/go.mod fehlt)."
    WINGS_SRC="$SOURCE/wings"
  else
    info "Lade $SOURCE ($REF) …"
    git_clone "$SOURCE" "$REF" "$WORK/repo" || die "git clone fehlgeschlagen."
    WINGS_SRC="$WORK/repo/wings"
  fi

  # Bringt die Quelle einen neueren Installer mit (typisch bei "hydra-node update"), übernimmt der.
  NEW_INSTALLER="$(dirname "$WINGS_SRC")/installer/wings-install.sh"
  if [[ -z "${HYDRA_REEXEC:-}" && -f "$NEW_INSTALLER" ]] && ! cmp -s "$NEW_INSTALLER" "$SCRIPT_PATH"; then
    info "Neuere Installer-Version gefunden – starte sie"
    cp "$NEW_INSTALLER" "$WORK/wings-install.sh"
    HYDRA_REEXEC=1 HYDRA_BOOT_DIR="$WORK" exec bash "$WORK/wings-install.sh" "${ORIGINAL_ARGS[@]}" --source "$SOURCE" --ref "$REF"
  fi

  # Go in genau der Version, die wings/go.mod verlangt (toolchain-Zeile), nach /opt/hydra/go.
  GO_VERSION="$(sed -n 's/^toolchain go\(.*\)$/\1/p' "$WINGS_SRC/go.mod")"
  [[ -n "$GO_VERSION" ]] || GO_VERSION="$(sed -n 's/^go \(.*\)$/\1/p' "$WINGS_SRC/go.mod")"
  if [[ ! -x "$GO_DIR/bin/go" ]] || [[ "$("$GO_DIR/bin/go" env GOVERSION 2>/dev/null)" != "go$GO_VERSION" ]]; then
    info "Installiere Go $GO_VERSION …"
    GO_TAR="go${GO_VERSION}.linux-${ARCH}.tar.gz"
    fetch -o "$WORK/$GO_TAR" "https://go.dev/dl/$GO_TAR" || die "Download von Go $GO_VERSION fehlgeschlagen."
    GO_SHA="$(fetch "https://go.dev/dl/?mode=json&include=all" | tr -d ' \n' \
      | grep -o "\"filename\":\"$GO_TAR\",\"os\":\"[a-z]*\",\"arch\":\"[a-z0-9]*\",\"version\":\"[^\"]*\",\"sha256\":\"[0-9a-f]*\"" \
      | sed 's/.*"sha256":"\([0-9a-f]*\)"/\1/' | head -n1 || true)"
    if [[ -n "$GO_SHA" ]]; then
      echo "$GO_SHA  $WORK/$GO_TAR" | sha256sum -c --status || die "Prüfsumme von $GO_TAR stimmt nicht."
    else
      warn "Prüfsumme für Go konnte nicht geladen werden – Download wird ungeprüft verwendet."
    fi
    rm -rf "$GO_DIR" && install -d "$(dirname "$GO_DIR")"
    tar -C "$(dirname "$GO_DIR")" -xzf "$WORK/$GO_TAR"
  fi
  info "Go $("$GO_DIR/bin/go" env GOVERSION) ok"

  WINGS_VERSION="$(sed -n 's/^var Version = "\(.*\)"$/\1/p' "$WINGS_SRC/system/const.go")"
  if git -C "$WINGS_SRC" rev-parse --short HEAD >/dev/null 2>&1; then
    WINGS_VERSION="$WINGS_VERSION+$(git -C "$WINGS_SRC" rev-parse --short HEAD)"
  fi
  info "Baue Hydra Wings $WINGS_VERSION …"
  rsync -a --delete "$WINGS_SRC/" "$WORK/wings/"
  # proxy.golang.org antwortet manchen Hoster-Netzen mit 403 (wie dl.google.com über IPv6).
  # Mit "|" holt Go ein Modul bei jedem Fehler direkt aus seinem Repository (GitHub, GitLab …);
  # die Prüfsummen aus go.sum gelten auch dann.
  GO_PROXY_CHAIN="${GOPROXY:-https://proxy.golang.org|direct}"
  (cd "$WORK/wings" && CGO_ENABLED=0 GOTOOLCHAIN=local GOPATH=/opt/hydra/gopath GOCACHE=/opt/hydra/gocache \
    GOPROXY="$GO_PROXY_CHAIN" GOFLAGS=-mod=readonly \
    "$GO_DIR/bin/go" build -trimpath \
      -ldflags="-s -w -X github.com/pterodactyl/wings/system.Version=$WINGS_VERSION" \
      -o "$WORK/hydra-wings" .) || die "Wings konnte nicht gebaut werden."
fi

chmod 755 "$WORK/hydra-wings"
"$WORK/hydra-wings" version >/dev/null 2>&1 || die "Die neue hydra-wings-Binary startet nicht."
WAS_RUNNING=0
systemctl is-active --quiet "$SERVICE" && WAS_RUNNING=1
install -m 755 "$WORK/hydra-wings" "$BIN"
info "$("$BIN" version | head -n1) nach $BIN installiert"

# ------------------------------------------------------------------ systemd

install -d -m 755 "$CONF_DIR"
cat > "/etc/systemd/system/$SERVICE.service" <<EOF
[Unit]
Description=Hydra Wings Daemon
After=docker.service
Requires=docker.service
PartOf=docker.service

[Service]
User=root
WorkingDirectory=$CONF_DIR
LimitNOFILE=4096
PIDFile=/var/run/wings/daemon.pid
ExecStart=$BIN --config $WINGS_CONFIG
Restart=on-failure
StartLimitInterval=180
StartLimitBurst=30
RestartSec=5s

[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload
systemctl enable "$SERVICE" >/dev/null 2>&1
info "Dienst $SERVICE eingerichtet"

# ------------------------------------------------------------------ SSL

if (( SSL )) && [[ ! -d "/etc/letsencrypt/live/$FQDN" ]]; then
  step "SSL-Zertifikat für $FQDN"
  apt-get install -y -qq certbot >/dev/null
  # Läuft auf dem Server schon ein Webserver auf Port 80 (z. B. Caddy vom Panel), wird er für die
  # Ausstellung und jede Erneuerung kurz angehalten.
  HOOKS=()
  for web in caddy nginx apache2; do
    if systemctl is-active --quiet "$web"; then
      HOOKS=(--pre-hook "systemctl stop $web" --post-hook "systemctl start $web")
      info "$web belegt Port 80 – wird während der Zertifikatsausstellung kurz angehalten"
      break
    fi
  done
  certbot certonly --standalone --non-interactive --agree-tos -m "$EMAIL" -d "$FQDN" \
    "${HOOKS[@]}" --deploy-hook "systemctl try-restart $SERVICE" \
    || die "Zertifikat konnte nicht ausgestellt werden (DNS-Eintrag und Port 80 prüfen)."
  info "Zertifikat unter /etc/letsencrypt/live/$FQDN/ – erneuert sich automatisch"
fi

# ------------------------------------------------------------------ Mit dem Panel verbinden

if [[ -n "$PANEL_URL" && -n "$TOKEN" && -n "$NODE_ID" ]]; then
  step "Mit dem Panel verbinden"
  CONFIGURE_ARGS=(configure --panel-url "$PANEL_URL" --token "$TOKEN" --node "$NODE_ID" --config-path "$WINGS_CONFIG" --override)
  (( ALLOW_INSECURE )) && CONFIGURE_ARGS+=(--allow-insecure)
  "$BIN" "${CONFIGURE_ARGS[@]}" || die "Konfiguration vom Panel konnte nicht geladen werden."
  info "Konfiguration nach $WINGS_CONFIG geschrieben"
fi

# UFW wird mitinstalliert, aber nicht eingeschaltet – das geht im Adminbereich unter
# Nodes > Firewall. Die Regeln für SSH, Wings und SFTP stehen schon bereit. Die Ports der
# Spielserver gibt Docker selbst frei.
SSH_PORTS="$(sshd -T 2>/dev/null | awk '$1 == "port" {print $2}' | sort -u || true)"
for port in ${SSH_PORTS:-22}; do ufw allow "$port/tcp" comment 'SSH' >/dev/null; done
ufw allow 8080/tcp comment 'Hydra Wings' >/dev/null && ufw allow 2022/tcp comment 'Hydra SFTP' >/dev/null
(( SSL )) && ufw allow 80/tcp comment 'Certbot' >/dev/null
if command -v caddy >/dev/null; then
  ufw allow 80/tcp comment 'Hydra Proxy' >/dev/null && ufw allow 443/tcp comment 'Hydra Proxy' >/dev/null
fi
if ufw status | grep -q "Status: active"; then
  info "Firewall (ufw): SSH, Ports 8080 (API) und 2022 (SFTP) freigegeben"
else
  info "Firewall (ufw) installiert, Regeln für SSH, 8080 und 2022 angelegt (einschalten im Adminbereich)"
fi

if [[ -f "$WINGS_CONFIG" ]]; then
  systemctl restart "$SERVICE"
  sleep 3
  if systemctl is-active --quiet "$SERVICE"; then
    info "$SERVICE läuft"
  else
    warn "$SERVICE startet nicht – Log: journalctl -u $SERVICE -n 50"
  fi
elif (( WAS_RUNNING )); then
  systemctl restart "$SERVICE"
fi

cat > "$NODE_CONF" <<EOF
# Hydra Wings – vom Installer gespeicherte Einstellungen (für Updates)
HYDRA_FQDN=$(printf '%q' "$FQDN")
HYDRA_EMAIL=$(printf '%q' "$EMAIL")
HYDRA_SOURCE=$(printf '%q' "$SOURCE")
HYDRA_REF=$(printf '%q' "$REF")
HYDRA_GIT_TOKEN=$(printf '%q' "$GIT_TOKEN")
HYDRA_BINARY_URL=$(printf '%q' "$BINARY_URL")
HYDRA_SSL=$SSL
EOF
chmod 600 "$NODE_CONF"

[[ "$SCRIPT_PATH" == /usr/local/bin/hydra-node ]] || install -m 755 "$SCRIPT_PATH" /usr/local/bin/hydra-node

# ------------------------------------------------------------------ Updates aus dem Adminbereich
#
# Das Panel bittet Wings über die geschützte Node-API um ein Update. Wings startet
# dann nur diese Unit, die als root genau "hydra-node update" mit den gespeicherten
# Einstellungen ausführt. Status und Log liest Wings für das Panel aus.
install -d -m 755 /usr/local/lib/hydra /var/lib/hydra/updates
cat > /usr/local/lib/hydra/node-updater <<'EOF'
#!/usr/bin/env bash
# Hydra Wings – führt im Adminbereich angeforderte Updates aus (Einstellungen > Updates).
# Wird vom Installer erzeugt und von hydra-node-update.service als root gestartet.
set -u
DIR=/var/lib/hydra/updates

status() { # status <zustand> <zeitfeld>
  printf '{"state":"%s","%s":"%s"}\n' "$1" "$2" "$(date -Iseconds)" > "$DIR/.status.json.tmp"
  mv "$DIR/.status.json.tmp" "$DIR/status.json"
}

status running started_at
if HYDRA_UPDATER=1 /usr/local/bin/hydra-node update > "$DIR/node.log" 2>&1; then
  status success finished_at
else
  status failed finished_at
fi
EOF
chmod 755 /usr/local/lib/hydra/node-updater
cat > /etc/systemd/system/hydra-node-update.service <<'EOF'
[Unit]
Description=Hydra Wings Updater (Adminbereich > Einstellungen > Updates)

[Service]
Type=oneshot
Environment=HOME=/root
ExecStart=/usr/local/lib/hydra/node-updater
TimeoutStartSec=3600
EOF
systemctl daemon-reload
info "Updates aus dem Adminbereich ok"

rm -rf "$WORK"
if [[ -n "${HYDRA_BOOT_DIR:-}" ]]; then rm -rf "$HYDRA_BOOT_DIR"; fi

step "Fertig"
if [[ -f "$WINGS_CONFIG" ]]; then
  info "Hydra Wings ist eingerichtet und mit dem Panel verbunden."
else
  cat <<EOF
    Hydra Wings ist installiert, aber noch nicht mit dem Panel verbunden:

    1. Im Panel unter Admin > Nodes einen Node anlegen
       (FQDN: ${FQDN:-<IP-Adresse>}, $( (( SSL )) && echo "Use SSL Connection" || echo "Use HTTP Connection"), Daemon Port 8080, SFTP 2022)
    2. Beim Node unter "Configuration" auf "Generate Token" klicken
    3. Auf diesem Server ausführen:
         sudo hydra-wings configure --panel-url <panel-url> --token <token> --node <id>
         sudo systemctl restart $SERVICE
EOF
fi
cat <<EOF

    Nützliche Befehle:
      hydra-node update                  Wings neu bauen und neu starten
      hydra-node status                  Zustand von Docker und Wings
      journalctl -u $SERVICE -f          Wings-Log
      hydra-wings diagnostics            Diagnosebericht
EOF
