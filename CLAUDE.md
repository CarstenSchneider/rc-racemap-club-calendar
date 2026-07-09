# RC RaceMap Club Calendar — Projektkontext für Claude Code

WordPress-Plugin, das die kommenden und vergangenen Renntermine **eines** RC-Modellbauvereins per Shortcode auf dessen Seite anzeigt. Zielgefühl: schlicht, schnell, wartungsarm, fügt sich vollständig ins aktive Theme ein.

> Diese Datei ist der **verbindliche Einstieg** für jede Claude-Code-Sitzung (lokal oder Cloud). Repo = einzige Quelle der Wahrheit; Arbeit soll nahtlos über Rechner/Umgebungen hinweg weiterlaufen.

## Aktueller Stand (Handoff · v1.0.12)

**Live & fertig:** Echte Daten über die MyRCM-Org-ID (MyRCM + RCK, Endpoint `https://rcracemap.com/api/clubs/{id}`); zweistufige Navigation (Haupt-Tabs „Aktuelle Termine"/„Archiv" + Jahres-Pills, ohne Reload); Kontext-Links pro Rennen (vergangen → Ergebnisse, kommend → Nennung); MyRCM-Links in Seitensprache (`pLa=de`); Rennen-Sichtbarkeit im Backend; Auto-Updates (Default an, öffentliches Repo, kein Token nötig); PHP 7.4-kompatibel. Getestet mit **TSV Mariendorf `18244`** und **RC Speedracer `45925`**.

**Roadmap – offen:**
- **Schritt 1 – Branding + Kartenlink** (Design zugesagt, Umsetzung offen): Footer-Logo auf den eigenen Verein verlinken → `https://rcracemap.com/?club=<myrcmOrgId>`. Dabei **Domain-Fix** `rc-racemap.com` → `rcracemap.com` (steht noch im Footer von `templates/calendar.php` sowie in den Plugin-Header-URIs). Karten-Basis-URL als Filter/Konstante vorsehen. Logo/Optik-Feinschliff.
- **Schritt 2 – Free/Paid + Ads:** „free" zeigt RC-RaceMap-Ads, „paid" nicht. Zum Testen faken: RC Speedracer = free, TSV Mariendorf = paid. Die API soll später `tier`/`ads` je Verein liefern.

**Externe Abhängigkeiten** (Projekt `myrcm-rc-map`, gebrieft in dessen `BRIEF-rc-racemap-plugin.md`, Branch `dev`):
- **RCK-`hostName`-Fix:** Code vorhanden, aber `rck-races.json` noch nicht neu importiert → RCK zeigt vorerst leicht abweichende Vereins-/Venue-Namen (kein Plugin-Fehler).
- **Volle Adresse:** API liefert nur `city` (Stadt), keine Straße → Plugin zeigt „Verein, Ort". Volle Adresse bräuchte ein API-Feld.
- **`?club=`-Deeplink (Task C):** auf der Karte noch nicht gebaut. Ein unbekannter `?club=`-Parameter wird ignoriert → der Link degradiert sauber auf die allgemeine Karte, springt automatisch auf den Verein, sobald Task C live ist.

## Repo & Verteilung

- GitHub (**öffentlich**): `https://github.com/CarstenSchneider/rc-racemap-club-calendar` (Account `CarstenSchneider`, Default-Branch `main`). Öffentlich, damit die Auto-Updates ohne Token laufen.
- Installierbare ZIP bauen (aus dem **Elternordner** des Plugins):
  ```bash
  zip -rq rc-racemap-club-calendar.zip rc-racemap-club-calendar \
    -x "rc-racemap-club-calendar/.git/*" -x "rc-racemap-club-calendar/.gitignore" -x "*/.DS_Store"
  ```

## Konventionen

- **Sprache:** Deutsch als **Basissprache** im Quelltext, aber **immer** i18n-Funktionen (`__()`, `esc_html__()` …), Textdomain `rc-racemap-club-calendar`.
- **PHP 7.4+** (Mindestanforderung wegen Zielserver mit PHP 7.4; Ziel/Empfehlung bleibt 8.x). Daher **keine** PHP-8-only-Syntax verwenden: kein `match`, keine Union-Types/`mixed` in Signaturen, keine Constructor-Promotion, keine nachgestellten Kommas in Funktions-Parameterlisten, keine `str_contains`/`str_starts_with`/`str_ends_with`. WordPress Coding Standards, OOP mit klarer Schichtentrennung, keine God-Class.
- **Sicherheit:** alle Ausgaben escapen, alle Eingaben sanitisieren, Nonces für Admin-Aktionen, minimale Rechte (`manage_options`).
- **Design:** CSS nur Layout; Farben/Fonts vom Theme (`inherit`/`currentColor`). Keine externen Libs im Frontend. Templates unter `templates/` sind theme-überschreibbar.

## Architektur (`includes/`)

| Datei | Aufgabe |
|---|---|
| `class-plugin.php` | Orchestrator (Singleton), verdrahtet Komponenten |
| `class-race.php` | Wertobjekt: normalisiert Rohdaten → typisiertes Rennen |
| `class-api.php` | **Einzige** Datenquelle: `GET {base}/api/clubs/{club-id}` + Sample-Fallback |
| `class-cache.php` | Transients-Wrapper |
| `class-calendar.php` | Businesslogik: Jahres-Gruppierung (`current_groups()`/`archive_groups()`), Sichtbarkeit, Memo |
| `class-admin.php` (+ `views/`) | Einstellungen + Rennen-Sichtbarkeit |
| `class-shortcode.php` | Frontend-Rendering über `templates/` |
| `class-updater.php` (+ `lib/plugin-update-checker/`) | GitHub-Auto-Updates |

Shortcode: `[rc_racemap_club_calendar]` (optionaler künftiger Parameter `club="…"` bereits vorbereitet). **Zweistufige Navigation** (serverseitig gerendert, Umschaltung per Vanilla-JS ohne Reload): Haupt-Tabs **„Aktuelle Termine"** (dieses Jahr + Zukunft, aufsteigend) / **„Archiv"** (frühere Jahre, absteigend); innerhalb jedes Tabs eine **Jahres-Navigation** (Pills, `templates/year-groups.php`). Aktions-Link/Status richten sich **pro Rennen am Datum** (vergangen → Ergebnisse, kommend → Nennung), nicht am Tab. Kalender-Logik: `current_groups()` / `archive_groups()` (nach Jahr gruppiert).

## Sichtbarkeit von Rennen

Immer über **stabile Event-ID** (nie Titel). Neue Rennen sind automatisch sichtbar; nur ein explizites `false` in `rc_rcc_visibility` blendet aus.

## Datenquelle & echtes Datenmodell

**Datenquelle (ab v1.0.9): Live-API.** `class-api.php` lädt `GET {base}/api/clubs/{myrcmOrgId}`; `base` = **`https://rcracemap.com`** (Endpoint `api/clubs.php` im Projekt `myrcm-rc-map`, deployt auf Hetzner, CORS offen). Eingabe = **MyRCM-Org-ID** (numerisch, z. B. 18244/45925). Antwort `{club, events}` (MyRCM+RCK gemerged, `source`-Feld je Event, Vereinsmeta name/website/lat/lng). **Domain ist `rcracemap.com` – NICHT `rc-racemap.com`** (der alte Footer-Link war falsch, für Schritt 1 relevant). Die statischen Snapshots (Repo rc-racemap-data) waren die Zwischenlösung und sind durch die Live-API abgelöst. Vertrag: [`docs/api-contract.md`](docs/api-contract.md).

Offen (myrcm-rc-map-Seite): Der RCK-`hostName`-Fix ist im Code, aber der deployte `rck-races.json`-Stand ist noch nicht neu importiert → RCK zeigt vorerst weiter leicht abweichende Namen.

Sample-Daten (`sample-data.json`) sind jetzt **Opt-in** für lokale Entwicklung: Konstante `RC_RCC_USE_SAMPLE_DATA` oder Filter `rc_rcc_use_sample_data`. Basis-URL überschreibbar via Konstante `RC_RCC_API_BASE_URL` / Filter `rc_rcc_api_base_url`.

Das kanonische Renn-Datenmodell stammt aus dem Schwesterprojekt `myrcm-rc-map` (`races.json`) → dokumentiert in **[`docs/rc-racemap-data-model.md`](docs/rc-racemap-data-model.md)**. Der **API-Vertrag** (Endpoint + Antwortform, den die Live-API erfüllen muss) steht in **[`docs/api-contract.md`](docs/api-contract.md)**; das Briefing an `myrcm-rc-map` (RCK-Namensfix + API bereitstellen) liegt dort als `BRIEF-rc-racemap-plugin.md`. Wichtige Abweichungen des aktuellen Plugin-Modells vom Realmodell (offener TODO):
- Einzel-`date` → **`from`/`to`-Bereich**
- `organizer`/`track` flach → `hostId`(Slug)/`hostName` + `venueName`/`venueLocation`
- `classes` als Strings → auch **Objekte `{name, entries}`**
- fester `links{}`-Satz → **`documents[]`** mit `{type, label, url}`
- **Eingabe im Plugin = MyRCM-Organisator-ID** (numerisch, z. B. `16961`) – Vereine kennen nur ihre MyRCM-ID, keine RaceMap-Slugs. Diese ID geht als `{club-id}` an die API; die RaceMap-Seite mappt sie auf den Verein. Das Datenfeld `hostId` (Slug wie `rcsf-singen-e-v`) ist davon getrennt und nur Teil der Renn-Antwort, **nicht** die Nutzereingabe.

## Auto-Update-Workflow (GitHub Releases)

1. Änderung umsetzen, **Versionsnummer** im Header von `rc-racemap-club-calendar.php` erhöhen (SemVer).
2. `git commit` + `git push origin main`.
3. `gh release create vX.Y.Z --title "vX.Y.Z" --notes "…" --latest` und die gebaute ZIP als Asset anhängen (`gh release upload`).
4. Seiten zeigen das Update im Backend. **Kein Token nötig** – das Repo ist öffentlich, PUC liest Releases anonym. Das Token-Feld/​die Konstante `RC_RCC_UPDATE_TOKEN` bleiben optional (Altlast); ein auf einer Seite eingetragenes **ungültiges** Token führt aber zu 401 – dann Feld leeren.
5. **Auto-Install:** Setting `auto_update` (Default **an**) installiert neue Releases selbstständig. Umgesetzt über den `auto_update_plugin`-Filter in `class-updater.php::filter_auto_update()`, **strikt auf `RC_RCC_BASENAME` begrenzt** – andere Plugins/Themes/Core bleiben unberührt. Pull-Modell: kein aktiver Push möglich; Verbreitung erfolgt automatisch im wp-cron-Fenster (~12 h).

## Nächste Schritte & Ideen

Aktuelle Roadmap + offene Abhängigkeiten siehe **[Aktueller Stand](#aktueller-stand-handoff--v1012)** oben (Schritt 1 Branding/Kartenlink, Schritt 2 Free/Paid+Ads).

Ideen für später (nicht beauftragt): iCal/Export, Serien-Filter, Countdown bis zum nächsten Rennen, Gutenberg-Block, volle Adresse (sobald API-Feld vorhanden).

## Umgebung / Tooling-Hinweise

- `gh` CLI wird genutzt (lokal unter `~/.local/bin/gh`). In einer neuen Umgebung ggf. `gh auth login` bzw. Git-Zugang neu herstellen.
- **PHP-CLI** liegt unter `~/.local/bin/php` (statischer Build von static-php.dev; in neuer Umgebung ggf. neu holen: `curl -sL https://dl.static-php.dev/static-php-cli/common/php-8.4.8-cli-macos-aarch64.tar.gz | tar xz`). **Vor jedem Release nutzen:** `php -l` auf alle geänderten Dateien **und** ein Stub-Test der Datenlogik (WP-Funktionen stubben, `RC_RCC_Race::from_array` + `RC_RCC_Calendar` gegen `sample-data.json` laufen lassen, prüfen dass beide Tabs Rennen erhalten). Voll ausführen lässt sich das Plugin nur auf echten WP-Seiten (TSV Mariendorf, RC Speedracer).
