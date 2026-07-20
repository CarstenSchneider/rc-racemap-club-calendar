# RC RaceMap Club Calendar — Projektkontext für Claude Code

WordPress-Plugin, das die Renntermine **eines** RC-Modellbauvereins per Shortcode
auf dessen Seite anzeigt. Zielgefühl: schlicht, schnell, wartungsarm, fügt sich
vollständig ins aktive Theme ein.

> Verbindlicher Einstieg für jede Claude-Code-Sitzung. Repo = einzige Quelle der
> Wahrheit.

**Repo:** `CarstenSchneider/rc-racemap-club-calendar` (öffentlich, Branch `main`)
**Stand:** v1.0.41
**Im Einsatz:** tsvm-racing.de/rcracemap (`18244`), rcspeedracer.de/rcracemap (`45925`)
**Anleitung für Vereine:** [`docs/anleitung.md`](docs/anleitung.md)

---

## Was das Plugin kann

Rennen aus MyRCM, RCK und DMC automatisch; je Rennen ausblenden, umbenennen und
eigene PDFs hinterlegen; eigene Termine für Rennen, die in keiner Quelle stehen;
dauerhaftes Archiv; Historie als Tabelle oder JSON einspielen.

Zwei Tabs — **Aktuelle Rennen** / **Vergangene Rennen** — je mit Jahres-Navigation,
Umschaltung per Vanilla-JS ohne Reload.

Die Renn-Zeile ist ein **Fünf-Spalten-Raster**: Datum · Rennen+Klassen ·
Teilnehmer · Dokumente · Aktion (`templates/race-item.php`).

## Aufbau

```
rc-racemap-club-calendar.php   Bootstrap, Version, Konstanten
includes/
  class-plugin.php             Singleton, Options-Konstanten, Defaults
  class-api.php                HTTP-Abruf, Cache, Rohdaten (last_rows())
  class-cache.php              Transients + Index
  class-calendar.php           Zusammenführung aller Quellen
  class-race.php               Wertobjekt, normalisiert die Rohdaten
  class-shortcode.php          Rendering-Kontext, Inline-Icons, Template-Lookup
  class-admin.php              Einstellungen, Rennverwaltung, Import
  class-updater.php            plugin-update-checker (GitHub Releases)
  views/manage-races.php       Backend: Rennliste, eigene Termine, Import
templates/                     calendar.php · year-groups.php · race-item.php
docs/                          anleitung.md · api-contract.md · rc-racemap-data-model.md
.github/workflows/release-zip.yml
```

**`Calendar::all_races()` ist die zentrale Stelle.** Die Reihenfolge ist relevant:

1. API abrufen
2. archivieren — **nur bei fehlerfreiem Abruf**
3. archivierte Rennen ergänzen, die die API nicht mehr liefert
4. Titel-Überschreibungen anwenden
5. DMC-Schatten unterdrücken
6. eigene Termine anhängen
7. eigene Dokumente anhängen

## Speicherung

Alles in `wp_options` der Vereins-Installation, **ohne Autoload** außer den
Settings. Hochgeladene PDFs liegen als normale Medien-Anhänge.

| Option | Inhalt |
|---|---|
| `rc_rcc_settings` | club_id, cache_ttl, accent_color, auto_update |
| `rc_rcc_visibility` | Event-ID → bool |
| `rc_rcc_titles` | Event-ID → eigener Titel |
| `rc_rcc_documents` | Event-ID → `[{label, url}]`, max. 5 |
| `rc_rcc_custom_races` | selbst angelegte Termine, max. 50 |
| `rc_rcc_archive` | Event-ID → Rohdatensatz, wächst dauerhaft (~900 B/Rennen) |
| `rc_rcc_cache_index` | Schlüssel der gesetzten Transients |

Zuordnung **immer über die stabile Event-ID**, nie über den Titel. Neue Rennen
sind automatisch sichtbar; nur ein explizites `false` blendet aus.
`uninstall.php` räumt alle Optionen ab, die PDFs bleiben (gehören dem Verein).

## Datenquelle

`GET https://rcracemap.com/api/clubs/{myrcmOrgId}` — geliefert vom
Schwesterprojekt `myrcm-rc-map` (Endpoint `api/clubs.php`), das MyRCM, RCK und
DMC zusammenführt. Eingabe im Plugin ist die **MyRCM-Organisator-ID** (numerisch);
`hostId` ist ein davon getrenntes Feld der Antwort, keine Nutzereingabe.

Vertrag: [`docs/api-contract.md`](docs/api-contract.md) — dort stehen die
`source`-Werte (`myrcm`, `rck-kleinserie`, `rck-challenge`, `dmc`, `myrcm+rck`),
die Merge-Regeln und die Klassen-Union.

`sample-data.json` ist Opt-in für lokale Entwicklung (Konstante
`RC_RCC_USE_SAMPLE_DATA` / Filter `rc_rcc_use_sample_data`), wird aber weiterhin
mit ausgeliefert — `class-api.php` lädt es als Rückfallebene.

**Filter:** `rc_rcc_api_base_url` · `rc_rcc_runtime_club_id` ·
`rc_rcc_plugin_page_url` · `rc_rcc_use_sample_data` · `rc_rcc_update_token`

---

## Entscheidungen und ihre Gründe

**Kein eigenes Design.** Schriftart *und* Schriftgrößen kommen vom Theme
(`font-family: inherit`, `font-size: inherit`, Abstufungen in `em`). Links erben
die Linkfarbe. Bis v1.0.36 brachte das Plugin Inter mit (self-hosted) — entfernt,
weil es auf Seiten mit eigener Hausschrift als Fremdkörper stand und 133 KB
kostete.

**Feste Spaltenbreiten in `em`**, bemessen am längsten real vorkommenden Inhalt.
Engste Spalte ist die Aktion — der Hinweistext „Nennung ab 30. September 2026"
füllt sie aus. Wer dort kürzt, erzeugt Umbrüche.

**Aufteilung nach Renndatum, nicht nach Kalenderjahr.** Sonst leert sich der Tab
„Vergangene Rennen" mitten in der Saison.

**Dauerhaftes Archiv.** MyRCM/RCK-Rennen fallen nach rund **sieben Wochen** aus
`races.json` (DMC führt den vollen Jahrgang — der Rückblick wirkt dadurch
länger, als er ist). Frische API-Daten gewinnen über archivierte, damit spätere
Korrekturen ankommen.

**DMC-Schatten unterdrücken.** Vereine melden Rennen bei DMC vor allem für die
Versicherung; ausgeschrieben wird über MyRCM/RCK. DMC-Titel sind deshalb
administrativ („Sportkreismeisterschaft"). Fällt das MyRCM-Rennen aus den Daten,
stünde der DMC-Eintrag neben dem archivierten. `suppress_shadowed_dmc()` läuft
über die **komplette** Liste inkl. Archiv — sonst greift es nicht mehr, sobald
auch der DMC-Eintrag archiviert ist.

**Eigenes Dokument schlägt gleichnamiges der Quelle.** Wer etwas hinterlegt,
meint es so, womöglich als korrigierte Fassung. Andersherum hätte der Verein
keine Möglichkeit zu übersteuern.

**Datum wird nicht im Plugin korrigiert.** MyRCM führt manche Rennen eintägig,
obwohl sie übers Wochenende gehen. Gehört an die Quelle — sonst pflegt jeder
Verein Korrekturen lokal und die Karte bleibt falsch.

**Vier Einstellungen.** club_id, Aktualisierungsintervall, Akzentfarbe,
automatische Updates. Das GitHub-Token ist entfallen: das Repo ist öffentlich,
der Updater kommt ohne aus, und der Erklärtext dazu war schlicht falsch.

## Konventionen

- **Deutsch als Quellsprache**, aber immer i18n-Funktionen (Textdomain
  `rc-racemap-club-calendar`). Es gibt kein `de_DE.mo`; übersetzt werden `en_US`
  und `en_GB`.
- **PHP 7.4+**: kein `match`, keine Union-Types, keine Constructor-Promotion,
  kein `str_contains`/`str_starts_with`.
- **Sicherheit:** Ausgaben escapen, Eingaben sanitisieren, Nonces für
  Admin-Aktionen, `manage_options`.
- **CSS nur Layout.** Templates unter `templates/` sind theme-überschreibbar
  (`wp-content/themes/DEIN-THEME/rc-racemap-club-calendar/`).
- **Release-Notes richten sich an Vereine**, nicht an Entwickler.

---

## Release

1. Version an **drei** Stellen: Header und `RC_RCC_VERSION` in
   `rc-racemap-club-calendar.php`, `Stable tag` in `readme.txt`. Header und
   Konstante sind schon einmal auseinandergelaufen — der Workflow prüft es jetzt.
2. `git commit` + `git push origin main`
3. `gh release create vX.Y.Z --title "…" --notes-file …`

`.github/workflows/release-zip.yml` baut daraufhin ein installierbares Paket und
hängt es als **`rc-racemap-club-calendar.zip`** an — fester Name, deshalb
funktioniert `…/releases/latest/download/rc-racemap-club-calendar.zip` dauerhaft.
Der Workflow prüft Versionsgleichheit, Vollständigkeit (inkl. `includes/lib` und
`sample-data.json`) und dass keine Entwicklungsdateien mitgehen. Per
`workflow_dispatch` lässt sich das ZIP an ein älteres Release nachreichen.

`class-updater.php` ruft `enableReleaseAssets()` — ohne das installieren die
automatischen Updates GitHubs „Source code"-Archiv, also den rohen Repo-Inhalt.
`PREFER_` statt `REQUIRE_`, damit ältere Releases installierbar bleiben.
Auto-Install ist strikt auf `RC_RCC_BASENAME` begrenzt; Verbreitung im
wp-cron-Fenster (~12 h).

### Übersetzungen

```bash
xgettext … -o languages/rc-racemap-club-calendar.pot -f <dateiliste>
msgmerge --no-fuzzy-matching --update <lang>.po <pot>
msgfmt --check -o <lang>.mo <lang>.po
msgcmp <lang>.po <pot>          # muss still bleiben
```

Mehrzeilige `msgid` beim Prüfen nicht per `grep "^msgid"` suchen — umgebrochene
Strings fallen sonst durch.

---

## Stolperfallen

**CSS-Spezifität.** Reset auf `.rc-rcc.rc-rcc li` (0,2,1) schlägt Theme-Regeln
(0,1,1); Komponenten auf (0,3,0) schlagen den Reset; **Media Queries brauchen
dieselbe Spezifität wie die Basisregel**, sonst greift das Desktop-Raster nicht.

**Leere Grid-Zellen.** `:empty` greift nicht bei Whitespace. Das Template gibt
leere Zellen deshalb gar nicht aus — sonst belegen sie auf Mobil eine eigene
Zeile.

**`str_getcsv()`** braucht alle vier Parameter, sonst warnt PHP 8.4 bei jedem
Import ins Fehlerprotokoll.

**Die MyRCM-Veranstalterseite ist nicht nach Verein gefiltert.** `dId[O]=…`
liefert auch fremde Events — beim Zuordnen von Event-IDs immer den Veranstalter
gegenprüfen.

**Query-Strings an Assets.** Manche Performance-Plugins entfernen `?ver=`; dann
behalten Browser und Caches alte CSS. Beim Prüfen mit `Cache-Control: no-cache`
arbeiten, sonst misst man den Cache.

**Sprache.** `wp_date()` folgt WordPress. Fehlen die deutschen Sprachdateien des
Kerns, bleiben Monatsnamen englisch, obwohl die Sprache auf Deutsch steht.
Erkennungszeichen: „Howdy" statt „Hallo" in der Adminleiste.

**Regex über PHP-Templates.** `</li>` trifft zuerst die Klassen-Pillen, nicht das
Listenelement. Hat in dieser Sitzung zweimal Dateien zerschnitten.

---

## Umgebung

- `gh` CLI unter `~/.local/bin/gh`.
- **PHP-CLI** unter `~/.local/bin/php` (statischer Build von static-php.dev).
- **Vor jedem Release:** `php -l` auf alle geänderten Dateien, `node --check` für
  JS, Klammerbilanz der CSS, `msgcmp` für beide Sprachen — und ein Stub-Test der
  Datenlogik (WP-Funktionen stubben, `RC_RCC_Race::from_array()` gegen echte
  API-Daten laufen lassen).
- Voll ausführen lässt sich das Plugin nur auf echten WP-Seiten. Für Layout gibt
  es eine lokale Vorschau (`dev-preview.html`, gitignored) mit dem echten CSS und
  einem bewusst abweichenden Test-Theme.

## Offen

- `docs/anleitung.md` auf `rcracemap.com/#wordpress-plugin` übernehmen.
- **Free/Paid + Ads:** „free" zeigt RC-RaceMap-Ads, „paid" nicht. Die API soll
  perspektivisch `tier`/`ads` je Verein liefern.
- Teilnehmerzahlen fehlen bei reinen RCK-Rennen — die Nennung läuft über RCK,
  MyRCM führt keine Liste, RCK hält keine Historie vor.
- Ideen, nicht beauftragt: iCal-Export, Serien-Filter, Gutenberg-Block, volle
  Adresse (sobald die API ein Feld dafür hat).
