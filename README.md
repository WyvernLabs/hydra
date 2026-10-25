# Hydra

Hydra ist ein Hardfork von [Pterodactyl](https://pterodactyl.io) – Panel und Wings in einem Repository, mit eigenem Theme im Stil des Sylas-Panels und eigenen Installern.

| Teil | Basis | Verzeichnis |
|------|-------|-------------|
| Hydra Panel | Pterodactyl Panel **v1.15.1** (Laravel 12, React 16) | `panel/` |
| Hydra Wings | Pterodactyl Wings **v1.13.4** (Go 1.26) | `wings/` |
| Installer | eigene Skripte | `installer/` |

Der unveränderte Upstream-Stand ist als erster Commit enthalten und mit `upstream-panel-v1.15.1` bzw. `upstream-wings-v1.13.4` getaggt. Mit `git diff upstream-panel-v1.15.1 -- panel/` lässt sich jederzeit sehen, was Hydra geändert hat.

---

## Installation

Panel und Wings werden getrennt installiert, auf zwei Servern oder auf demselben. Unterstützt werden **Debian 12/13** und **Ubuntu 22.04/24.04** (amd64/arm64), für Wings ein KVM- oder Dedicated-Server, kein LXC/OpenVZ.

Die Installer installieren alle Abhängigkeiten selbst. Ein leerer Server mit root-Zugang reicht.

### 1. DNS

Zwei A-Records auf die jeweiligen Server-IPs, z. B.:

| Name | Zeigt auf |
|------|-----------|
| `panel.example.com` | Panel-Server |
| `node1.example.com` | Wings-Server |

### 2. Panel

Als root auf dem Panel-Server:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/WyvernLabs/hydra/main/installer/panel-install.sh)
```

Der Installer lädt den Quellcode selbst von GitHub und merkt sich die Quelle für Updates. Alternativ geht `sudo bash installer/panel-install.sh` aus einem geklonten oder hochgeladenen Repository.

Solange das Repository privat ist, braucht es ein GitHub-Token mit Lesezugriff (Fine-grained Token, Repository-Recht „Contents: Read“). Es wird für Updates in `/etc/hydra/panel.conf` (nur root) gespeichert:

```bash
TOKEN=github_pat_…
bash <(curl -fsSL -H "Authorization: Bearer $TOKEN" https://raw.githubusercontent.com/WyvernLabs/hydra/main/installer/panel-install.sh) --git-token "$TOKEN"
```

Der Installer fragt nach Domain, E-Mail, Admin-Benutzer und Zeitzone und richtet dann ein:

- PHP-FPM (Distributionsversion ≥ 8.2, auf Ubuntu 22.04 PHP 8.3 aus dem PPA), MariaDB, Redis, Composer, Node.js 22 + Yarn
- Caddy mit automatischem HTTPS (Let's Encrypt). Bei einer IP-Adresse statt Domain läuft das Panel über HTTP.
- Datenbank `hydra` mit zufälligem Passwort, `.env`, `APP_KEY`, Migrationen
- Queue-Worker (`hydra-queue.service`) und Laravel-Cronjob (`/etc/cron.d/hydra-panel`)
- den ersten Admin. Die Zugangsdaten landen in `/root/hydra-panel-credentials.txt`.

Das Panel liegt unter `/var/www/hydra`, die Installer-Einstellungen unter `/etc/hydra/panel.conf`.

Nicht-interaktiv:

```bash
sudo HYDRA_ADMIN_PASSWORD='…' bash installer/panel-install.sh --yes \
  --domain panel.example.com --email admin@example.com --admin admin
```

**Update:** `sudo hydra-panel update`, oder den Installer erneut ausführen. `.env`, `storage/` und hochgeladene Logos bleiben erhalten. Wurde aus einem Git-Repository installiert (`--source <url> --ref <branch>`), zieht das Update von dort den neuesten Stand.

### 3. Node anlegen

Im Panel unter **Admin › Locations** eine Location anlegen, danach unter **Admin › Nodes** einen Node:

- FQDN: `node1.example.com`
- Communicate Over SSL: *Use SSL Connection* (wenn das Panel HTTPS nutzt)
- Daemon Port `8080`, SFTP Port `2022`
- Daemon Server File Directory: `/var/lib/hydra/volumes` (Standard)

Danach unter **Configuration › Generate Token** den Token erzeugen.

### 4. Wings

Als root auf dem Node-Server:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/WyvernLabs/hydra/main/installer/wings-install.sh) \
  --fqdn node1.example.com --email admin@example.com \
  --panel-url https://panel.example.com --token <token> --node <id>
```

Für ein privates Repository wie beim Panel `-H "Authorization: Bearer $TOKEN"` an curl und `--git-token "$TOKEN"` an den Installer anhängen. Aus einem Repository-Verzeichnis heraus geht auch `sudo bash installer/wings-install.sh …`.

Das installiert Docker, baut Hydra Wings aus `wings/` mit der passenden Go-Version (nach `/opt/hydra/go`), holt ein Let's-Encrypt-Zertifikat, verbindet den Node mit dem Panel und startet `hydra-wings.service`.

Ohne `--panel-url/--token/--node` wird Wings nur installiert. Verbinden kannst du ihn später so:

```bash
sudo hydra-wings configure --panel-url https://panel.example.com --token <token> --node <id>
sudo systemctl restart hydra-wings
```

Laufen Panel und Wings auf **demselben Server**, hält der Installer Caddy für die Zertifikatsausstellung (und jede Erneuerung) kurz an, weil certbot Port 80 braucht.

Der Installer baut außerdem lokale Laufzeit-Images, die es nicht fertig gibt (derzeit `hydra/yolks:java_25_graalvm` für GraalVM). Eggs verweisen mit `~hydra/yolks:…` darauf.

**Update:** `sudo hydra-node update`

| | Pterodactyl | Hydra |
|---|---|---|
| Binary | `/usr/local/bin/wings` | `/usr/local/bin/hydra-wings` |
| Dienst | `wings.service` | `hydra-wings.service` |
| Konfiguration | `/etc/pterodactyl/config.yml` | `/etc/hydra/config.yml` |
| Daten | `/var/lib/pterodactyl` | `/var/lib/hydra` |
| Logs | `/var/log/pterodactyl` | `/var/log/hydra` |
| Systembenutzer | `pterodactyl` | `hydra` |
| Docker-Netzwerk | `pterodactyl_nw` / `pterodactyl0` | `hydra_nw` / `hydra0` |

---

## Theme & Branding

Kundenbereich (React) und Adminbereich (Blade) teilen sich dieselben Design-Dateien:

| Datei | Inhalt |
|-------|--------|
| `panel/public/hydra/tokens.css` | Farben, Radien, Abstände als CSS-Variablen (`--hydra-*`) |
| `panel/public/hydra/layout.css` | Seitengerüst: schwebende Sidebar, mobiles Menü, Karten |
| `panel/public/hydra/admin.css` | Umstyling der AdminLTE/Bootstrap-Komponenten im Adminbereich |
| `panel/tailwind.config.js` | Tailwind-Farben `gray`/`neutral` und `primary`/`blue`/`cyan`/`accent` zeigen auf die CSS-Variablen |

**Akzentfarbe, Logo und Favicon** werden unter **Admin › Settings › Appearance** eingestellt (Standard: Grün `#74c236`). Aus der Akzentfarbe berechnet `BrandingService` (`panel/app/Services/Hydra/`) die Skala 50–900 und schreibt sie in jede Seite. Ein neuer Frontend-Build ist dafür nicht nötig. Hochgeladene Bilder liegen in `storage/app/hydra/branding/` und werden über `/branding/logo` bzw. `/branding/favicon` ausgeliefert.

Wo Farben auf ein Canvas gezeichnet werden (Charts, Terminal), liefert `hydraColor('accent-400')` aus `resources/scripts/lib/helpers.ts` den konkreten Wert.

Die Sidebar im Kundenbereich entsteht aus `resources/scripts/routers/routes.ts`. Jede Route mit `name` und `icon` erscheint automatisch im Menü. Neue Seiten oder Addons tragen sich dort ein.

---

## Sprachen

Das Panel ist vollständig zweisprachig (Englisch/Deutsch): Kundenbereich, Adminbereich, Fehlermeldungen und E-Mails.

- **Welche Sprache gilt?** Die Sprache des Benutzers (Konto › Sprache), sonst die Browsersprache, sonst die Standardsprache aus Admin › Settings. E-Mails gehen in der Sprache des Empfängers raus.
- **Wo liegen die Texte?** In `panel/resources/lang/<sprache>/`:

| Datei | Verwendet von |
|-------|---------------|
| `client/common.php`, `client/auth.php`, `client/dashboard.php`, `client/account.php`, `client/server.php` | Kundenbereich (React, i18next-Namespaces `client/…`) |
| `admin/*.php` | Adminbereich (Blade, `__('admin/…')`) |
| `server/permissions.php` | Bezeichnungen der Unterbenutzer-Rechte |
| `mail.php` | E-Mails |
| `validation.php`, `auth.php`, `exceptions.php`, … | Laravel und Upstream |

- **Neue Texte** kommen immer als Schlüssel in die englische **und** deutsche Datei, nie fest in den Code. Im React-Code: `const { t } = useTranslation('client/server'); t('files.title')`, für andere Namespaces `t('client/common:actions.save')`. In Blade: `{{ __('admin/nodes.title') }}`, in JavaScript innerhalb von Blade: `@json(__('…'))`.
- **Prüfen:** `php panel/resources/lang/check.php` meldet fehlende Übersetzungen und Schlüssel, die im Code verwendet werden, aber nirgends definiert sind.
- **Weitere Sprache:** Ordner `resources/lang/en` nach z. B. `resources/lang/fr` kopieren und übersetzen. Die Sprache erscheint dann automatisch in allen Auswahllisten. Für Datumsformate im Kundenbereich die date-fns-Locale in `resources/scripts/lib/date.ts` ergänzen.

## Entwicklung

### Panel-Frontend

```bash
cd panel
npx yarn@1.22.22 install
npx tsc --noEmit                                         # Typen prüfen
npx eslint "resources/scripts/**/*.{ts,tsx}"             # Lint
npx jest                                                 # Tests
NODE_ENV=production ./node_modules/.bin/webpack --mode production   # Build nach public/assets
```

### Wings

```bash
cd wings
make build VERSION=1.0.0-dev     # build/hydra-wings_linux_amd64 und _arm64
```

Wings läuft nur unter Linux. Unter Windows geht nur das Cross-Compilen mit `GOOS=linux GOARCH=amd64 go build .`.

### Was bewusst *nicht* umbenannt wurde

Damit sich Sicherheitsfixes von Upstream weiterhin sauber übernehmen lassen, bleiben interne Bezeichner unverändert:

- PHP-Namespace `Pterodactyl\…`, `config/pterodactyl.php`, Artisan-Befehle `p:…`
- Go-Modulpfad `github.com/pterodactyl/wings`
- API-Header `application/vnd.pterodactyl.v1+json` (Protokoll zwischen Panel und Wings)
- `window.PterodactylUser` im Frontend

### Upstream-Fixes übernehmen

```bash
# Beispiel: Panel-Fix von v1.15.1 auf v1.15.2 übernehmen
git clone https://github.com/pterodactyl/panel /tmp/ptero-panel
git -C /tmp/ptero-panel diff v1.15.1 v1.15.2 > /tmp/panel.patch
git apply --3way --directory=panel /tmp/panel.patch
```

Für Wings funktioniert das genauso mit `--directory=wings`. Danach `config/hydra.php` (`upstream`) anpassen.

### Abweichungen von Upstream

- Telemetrie an pterodactyl.io ist standardmäßig **aus** (`PTERODACTYL_TELEMETRY_ENABLED=false`).
- Die Versionsprüfung gegen das Pterodactyl-CDN ist abgeschaltet. Mit `HYDRA_VERSION_URL` (JSON `{"panel": "…", "wings": "…"}`) kann ein eigener Update-Server eingetragen werden.

## Lizenz

Pterodactyl Panel und Wings stehen unter der MIT-Lizenz (`panel/LICENSE.md`, `wings/LICENSE`). Die Copyright-Hinweise der Originalautoren müssen erhalten bleiben.
