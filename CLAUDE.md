# RC RaceMap Club Calendar — Projektkontext für Claude Code

WordPress-Plugin, das die kommenden und vergangenen Renntermine **eines** RC-Modellbauvereins per Shortcode auf dessen Seite anzeigt. Zielgefühl: schlicht, schnell, wartungsarm, fügt sich vollständig ins aktive Theme ein.

> Diese Datei ist der **verbindliche Einstieg** für jede Claude-Code-Sitzung (lokal oder Cloud). Repo = einzige Quelle der Wahrheit; Arbeit soll nahtlos über Rechner/Umgebungen hinweg weiterlaufen.

## Repo & Verteilung

- GitHub (privat): `https://github.com/CarstenSchneider/rc-racemap-club-calendar` (Account `CarstenSchneider`, Default-Branch `main`).
- Installierbare ZIP bauen (aus dem **Elternordner** des Plugins):
  ```bash
  zip -rq rc-racemap-club-calendar.zip rc-racemap-club-calendar \
    -x "rc-racemap-club-calendar/.git/*" -x "rc-racemap-club-calendar/.gitignore" -x "*/.DS_Store"
  ```

## Konventionen

- **Sprache:** Deutsch als **Basissprache** im Quelltext, aber **immer** i18n-Funktionen (`__()`, `esc_html__()` …), Textdomain `rc-racemap-club-calendar`.
- **PHP 8+**, WordPress Coding Standards, OOP mit klarer Schichtentrennung, keine God-Class.
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
- club-id ist ein **Slug** (z. B. `rcsf-singen-e-v`), nicht numerisch

## Auto-Update-Workflow (GitHub Releases)

1. Änderung umsetzen, **Versionsnummer** im Header von `rc-racemap-club-calendar.php` erhöhen (SemVer).
2. `git commit` + `git push origin main`.
3. `gh release create vX.Y.Z --title "vX.Y.Z" --notes "…" --latest` und die gebaute ZIP als Asset anhängen (`gh release upload`).
4. Seiten zeigen das Update im Backend. Token-Quelle (Reihenfolge): Konstante `RC_RCC_UPDATE_TOKEN` in `wp-config.php` > Filter `rc_rcc_update_token` > Admin-Feld. Für das private Repo genügt ein fein granuliertes Token mit **Contents: Read**.

## Offene TODOs

- [ ] Datenmodell (`class-race.php`, `class-api.php`, `sample-data.json`, Templates) an `docs/rc-racemap-data-model.md` angleichen → **v1.0.1**.
- [ ] Auto-Update-Ablauf einmal real testen (Token + v1.0.1).
- [ ] Später (nicht jetzt): iCal/Export, Serien-Filter, Ergebnisse, Countdown, Gutenberg-Block.

## Umgebung / Tooling-Hinweise

- `gh` CLI wird genutzt (lokal unter `~/.local/bin/gh`). In einer neuen Umgebung ggf. `gh auth login` bzw. Git-Zugang neu herstellen.
- Kein PHP/WP in der Entwicklungsumgebung → Plugin wird auf echten WP-Seiten (TSV Mariendorf, RC Speedracer) getestet, nicht lokal ausgeführt.
