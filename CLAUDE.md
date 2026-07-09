# RC RaceMap Club Calendar — Projektkontext für Claude Code

WordPress-Plugin, das die kommenden und vergangenen Renntermine **eines** RC-Modellbauvereins per Shortcode auf dessen Seite anzeigt. Zielgefühl: schlicht, schnell, wartungsarm, fügt sich vollständig ins aktive Theme ein.

> Diese Datei ist der **verbindliche Einstieg** für jede Claude-Code-Sitzung (lokal oder Cloud). Repo = einzige Quelle der Wahrheit; Arbeit soll nahtlos über Rechner/Umgebungen hinweg weiterlaufen.

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
| `class-calendar.php` | Businesslogik: upcoming/archiv, Sichtbarkeit, Sortierung, Memo |
| `class-admin.php` (+ `views/`) | Einstellungen + Rennen-Sichtbarkeit |
| `class-shortcode.php` | Frontend-Rendering über `templates/` |
| `class-updater.php` (+ `lib/plugin-update-checker/`) | GitHub-Auto-Updates |

Shortcode: `[rc_racemap_club_calendar]` (optionaler künftiger Parameter `club="…"` bereits vorbereitet). Zwei Tabs (kommende/Archiv), serverseitig gerendert, Umschaltung per Vanilla-JS ohne Reload.

## Sichtbarkeit von Rennen

Immer über **stabile Event-ID** (nie Titel). Neue Rennen sind automatisch sichtbar; nur ein explizites `false` in `rc_rcc_visibility` blendet aus.

## Datenquelle & echtes Datenmodell

Die RC-RaceMap-API existiert **noch nicht**. Bis dahin: Fallback auf `sample-data.json` (Filter `rc_rcc_use_sample_data`). Echte API aktivieren via Konstante `RC_RCC_API_BASE_URL` oder Filter `rc_rcc_api_base_url`.

Das kanonische Renn-Datenmodell stammt aus dem Schwesterprojekt `myrcm-rc-map` (`races.json`) → dokumentiert in **[`docs/rc-racemap-data-model.md`](docs/rc-racemap-data-model.md)**. Wichtige Abweichungen des aktuellen Plugin-Modells vom Realmodell (offener TODO):
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

## Offene TODOs

- [x] Datenmodell (`class-race.php`, `sample-data.json`, Templates) an `docs/rc-racemap-data-model.md` angleicht → **v1.0.1** (real: `name`/`hostName`/`venueName`+`venueLocation`, `from`/`to`-Bereich, `series[]`, gemischte `classes` mit `entries`, `documents[]`→Links, `registrationStatus`+`note`; Slug-Club-ID; alte Sample-Keys bleiben als Fallback lesbar). `class-api.php` toleriert bereits `{events:[]}` und bare Liste.
- [ ] Auto-Update-Ablauf einmal real testen (Token + v1.0.1).
- [ ] Später (nicht jetzt): iCal/Export, Serien-Filter, Ergebnisse, Countdown, Gutenberg-Block.

## Umgebung / Tooling-Hinweise

- `gh` CLI wird genutzt (lokal unter `~/.local/bin/gh`). In einer neuen Umgebung ggf. `gh auth login` bzw. Git-Zugang neu herstellen.
- **PHP-CLI** liegt unter `~/.local/bin/php` (statischer Build von static-php.dev; in neuer Umgebung ggf. neu holen: `curl -sL https://dl.static-php.dev/static-php-cli/common/php-8.4.8-cli-macos-aarch64.tar.gz | tar xz`). **Vor jedem Release nutzen:** `php -l` auf alle geänderten Dateien **und** ein Stub-Test der Datenlogik (WP-Funktionen stubben, `RC_RCC_Race::from_array` + `RC_RCC_Calendar` gegen `sample-data.json` laufen lassen, prüfen dass beide Tabs Rennen erhalten). Voll ausführen lässt sich das Plugin nur auf echten WP-Seiten (TSV Mariendorf, RC Speedracer).
