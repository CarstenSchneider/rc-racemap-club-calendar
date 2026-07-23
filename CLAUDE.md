# RC RaceMap Club Calendar — Projektkontext für Claude Code

WordPress-Plugin, das die Renntermine **eines** RC-Modellbauvereins per Shortcode
auf dessen Seite anzeigt. Zielgefühl: schlicht, schnell, wartungsarm, fügt sich
vollständig ins aktive Theme ein.

> Verbindlicher Einstieg für jede Claude-Code-Sitzung. Repo = einzige Quelle der
> Wahrheit.

**Repo:** `CarstenSchneider/rc-racemap-club-calendar` (öffentlich, Branch `main`)
**Stand:** v1.0.64
**Im Einsatz:** tsvm-racing.de/rcracemap (`18244`), rcspeedracer.de/rcracemap (`45925`)
**Anleitung für Vereine:** [`docs/anleitung.md`](docs/anleitung.md)
**Sprachen:** de (Quelle), en, fr, nl, it, es, cs, pl — folgt `get_locale()`

---

## Was das Plugin kann

Rennen aus MyRCM, RCK und DMC automatisch; je Rennen ausblenden, umbenennen und
eigene PDFs hinterlegen; eigene Termine für Rennen, die in keiner Quelle stehen;
dauerhaftes Archiv; Historie als Tabelle (CSV, achtsprachig) oder JSON
einspielen. Achtsprachig, folgt der WordPress-Sprache.

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
2. **feldweise anreichern** (`enrich_rows()`) — vor Anzeige *und* Archivierung
3. archivieren — **nur bei fehlerfreiem Abruf**
4. archivierte Rennen ergänzen, die die API nicht mehr liefert
5. Titel-Überschreibungen anwenden
6. DMC-Schatten unterdrücken
7. eigene Termine anhängen
8. eigene Dokumente anhängen

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

## Auto-Archiv & Teilnehmer-Links (Klassen-Pillen)

**Auto-Archiv:** `RC_RCC_Calendar::archive_rows()` schreibt bei jedem
erfolgreichen API-Abruf **jede gelieferte Zeile** nach `rc_rcc_archive`, keyed
nach der **API-`id`** (`<clubSlug>-<from>-myrcm-event-<eventId>` bzw.
`…-rck-…` / `dmc-…`). `archived_races()` rendert daraus alles, was die aktuelle
API-Antwort nicht mehr enthält (Fenster ~6 Wochen). **Es gibt keine Dedup nach
Titel/Datum** — nur die id entscheidet. Ein Datei-Import mit abweichender id
überschreibt einen auto-archivierten Datensatz daher **nicht**, sondern legt
einen Parallel-Datensatz an (beide werden gerendert).

**Klassen-Pillen sind klickbar**, wenn die Klasse eine per-Klasse
`participantsUrl` trägt (`…/report/<eventId>/<classId>?reportKey=100&reportType=participants`)
UND `entries > 0`. Die API liefert sie seit **2026-07-21**. Problem: die API
lieferte bis **2026-07-10** die MyRCM-**Vollhistorie**, d. h. alle Alt-Events
wurden bereits **ohne** `participantsUrl` auto-archiviert → ihre Pillen sind
stumm. Ein Datei-Import konnte das wegen des id-Konflikts nicht heilen.

**Lösung — Admin-Button „Teilnehmer-Links nachtragen"** (v1.0.61+,
*Rennen verwalten*, `handle_enrich_participants`): geht `rc_rcc_archive` durch,
zieht je Event die **eventId** (aus id `myrcm-event-<n>` oder aus `url`
`dId[E]=`/`/report/`/`/live/`), holt das **KLASSE-`<option value>`-Dropdown** von
`myrcm.ch/de/report/<eventId>` (`fetch_myrcm_classmap`, Transient
`rc_rcc_cmap_<eid>`), matcht Klassennamen (normalisiert + Levenshtein ≤2) und
schreibt `participantsUrl` **in place** in `rc_rcc_archive` (id bleibt, kein
Import). Einmalig pro Vereins-WP; neue Events kommen ohnehin schon verlinkt.
**v1.0.63:** Fallback über `fetch_myrcm_org_datemap` — eventId per `from`-Datum
aus `/de/organizers/<clubId>` (für Datensätze, deren `url` ein Ergebnis-PDF ist
und deren id kein `myrcm-event-<n>` trägt).

**Bekannte, offen gelassene Grenze (auf Wunsch hier gestoppt, 2026-07-23):**
Ein Event bleibt stumm, wenn **alle drei** zutreffen: (1) gespeicherte id ohne
`myrcm-event-<n>` (Alt-`archive-…`-Form), (2) `url` ist ein Ergebnis-PDF statt
der MyRCM-URL, (3) Event ist **älter als ~12 Monate** → nicht mehr im
Organizer-Fenster. Dann ist die eventId automatisch **nicht** ableitbar.
Belegtes Beispiel: TSV Mariendorf, „TEC – Tamiya Euro Cup" 05.07.2025
(eventId 88293). **Reine RCK-Events** (`rck-kleinserie`/`rck-challenge`) haben
gar keine Klassen/Teilnehmerliste auf MyRCM → korrekt stumm, kein Bug.

Möglicher Weg für die Restfälle (nicht umgesetzt): Datei-Import der
angereicherten Archiv-JSON. Skripte dafür liegen im **Map-Repo**
(`scripts/enrich-archive-participants.js` = participantsUrl je Klasse via Report-
Dropdown; `scripts/fix-archive-ids.js` = ids aufs Auto-Archiv-Schema
`<slug>-<from>-myrcm-event-<eid>` + `remove`-Liste für Alt-Waisen). **Aber:** die
Datei-Importe dieses Vereins blieben in dieser Sitzung wirkungslos, obwohl der
Import-Code (per CLI-Stub) korrekt speichert und der Button-POST funktioniert —
**Verdacht: eine WAF/Security-Schicht blockt den großen Multi-URL-POST des
Textarea-Imports still**, während der winzige Button-POST durchgeht. Nicht
verifiziert.

**Diagnose-Technik (falls wieder „Import wirkt nicht"):** sichtbaren
Versions-Marker + HTML-Kommentar mit `participantsUrl`-Zählung ins Rendering
bauen (`templates/calendar.php`), Seite per `curl` mit Cache-Buster holen und den
Kommentar lesen → trennt Code-/Cache-Problem (läuft neuer Code?) von
Daten-Problem (steht die URL wirklich gespeichert?). In v1.0.62 wieder entfernt;
die Version bleibt als `data-rc-rcc-version` am `.rc-rcc`-Wrapper.

---

## Drei Vereinsmuster — und was jedes braucht (v1.0.64)

Die Logik muss für **alle** Vereine tragen, nicht nur für MyRCM-Vereine:

| Muster | Klassen / Teilnehmer | Ergebnisse |
|---|---|---|
| Nennung über **MyRCM** | von MyRCM, Pillen verlinkt | MyRCM-Ergebnisseite (`results_url` aus der eventId) |
| Nennung über **RCK** (der Normalfall) | RCK liefert die Zahl; MyRCM führt das Event oft mit Klassen, aber 0 Nennungen | **eigenes PDF**, Bezeichnung „Ergebnisse" |
| nur **DMC** gemeldet | keine | eigenes PDF |

**Feldweise Anreicherung (`enrich_rows` / `keep_richer`).** RCK führt nur
*kommende* Rennen. Nach dem Renntag fällt der Merge-Partner weg, übrig bleibt die
MyRCM-Sicht mit 0 Nennungen und ohne Teilnehmerlisten — ein späterer Abruf ist
also **ärmer** als ein früherer. Deshalb wird jede frische Zeile vor Anzeige *und*
Archivierung gegen das Archiv gehalten: `registrationCount` 0/fehlend
überschreibt kein gespeichertes >0, leere `url`/`registrationListUrl`/`documents`
überschreiben nichts, und `keep_richer_classes()` bewahrt je Klasse `entries` und
`participantsUrl` (Zuordnung über `normalise_label()` des Klassennamens).
**Datum, Titel und Nennstatus bleiben bewusst frisch** — Korrekturen an der Quelle
sollen ankommen. Das repariert rückwirkend nichts, verhindert aber jeden weiteren
Verlust.

**Eigenes Dokument „Ergebnisse" füllt den Knopf.** In `attach_custom_documents()`
setzt eine passende Bezeichnung `results_url` und landet **nicht** in der
Dokumentspalte. So zeigen RCK-Vereine ihr Ergebnis-PDF genauso prominent wie
MyRCM-Vereine ihre Ergebnisseite. Erkannt werden die **übersetzte** Bezeichnung
der Seitensprache sowie „Ergebnisse", „Ergebnis" und „Results"; der Vergleich
läuft über `fold_label()` (kleingeschrieben, transliteriert, nur a–z0–9), damit
auch „Resultats" ohne Akzent trifft. `normalise_label()` bleibt für die
Dokument-Dedup zeichengetreu — dort ist die Bezeichnung freier Text und soll nicht
versehentlich verschmelzen. Der Sonderfall steht als Hinweis am Eingabefeld und in
`docs/anleitung.md`, sonst findet ihn niemand.

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

- **Deutsch als Quellsprache** (die `msgid` sind deutsch), aber immer
  i18n-Funktionen (Textdomain `rc-racemap-club-calendar`). Es gibt kein
  `de_DE.mo`; übersetzt sind `en_US`, `en_GB`, `fr_FR`, `nl_NL`, `it_IT`,
  `es_ES`, `cs_CZ`, `pl_PL`. Terminologie **konsistent mit der Web-App** — die
  Frontend-Labels stammen aus deren `TRANSLATIONS`. cs/pl haben 3 Plural-Formen.
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

Der Workflow schließt `*.po`/`*.pot` aus dem Paket aus (nur `.mo` wirkt zur
Laufzeit) und bricht ab, wenn doch welche drin sind. `docs/`, `CLAUDE.md` und
`dev-preview.html` bleiben ebenfalls draußen. Quelltexte der Übersetzung bleiben
im Repo.

### Übersetzungen

```bash
xgettext … -o languages/rc-racemap-club-calendar.pot -f <dateiliste>
msgmerge --no-fuzzy-matching --update <lang>.po <pot>
msgfmt --check -o <lang>.mo <lang>.po
msgcmp <lang>.po <pot>          # muss still bleiben
```

Mehrzeilige `msgid` beim Prüfen nicht per `grep "^msgid"` suchen — umgebrochene
Strings fallen sonst durch.

Die sechs nicht-englischen Übersetzungen liefert die **App-Session** (konsistente
Terminologie), das Plugin legt sie ab, kompiliert und committet. **Keine `msgid`
ändern, nachdem Übersetzungen geliefert sind** — das bricht den betroffenen
String in allen Sprachen (in dieser Sitzung um Haaresbreite passiert). Wer den
deutschen Quelltext ändert, muss alle Sprachen nachziehen lassen.

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

**CSV-Header-Transliteration.** Der Import (`parse_csv` in `class-admin.php`)
kennt die Spaltennamen in allen acht Sprachen. `normalize_header()`
transliteriert diakritische Zeichen (é→e, ł→l, á→a …) statt sie zu entfernen —
sonst würde „Résultats" zu „rsultats" und der Alias griffe nicht. Neue
Spaltennamen als **transliterierten** ASCII-Schlüssel in die `$alias`-Map.

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
  JS, Klammerbilanz der CSS, `msgfmt --check` + `msgcmp` für **alle acht**
  Sprachen — und ein Stub-Test der Datenlogik (WP-Funktionen stubben,
  `RC_RCC_Race::from_array()` gegen echte API-Daten laufen lassen).
- Voll ausführen lässt sich das Plugin nur auf echten WP-Seiten. Für Layout gibt
  es eine lokale Vorschau (`dev-preview.html`, gitignored) mit dem echten CSS und
  einem bewusst abweichenden Test-Theme.

## Offen

- `docs/anleitung.md` auf `rcracemap.com/#wordpress-plugin` übernehmen.
- **Free/Paid + Ads:** „free" zeigt RC-RaceMap-Ads, „paid" nicht. Die API soll
  perspektivisch `tier`/`ads` je Verein liefern.
- **Map/API briefen:** Klassen, Nennzahlen und per-Klasse `participantsUrl` für
  *jedes* Event, das auf MyRCM existiert — auch für `myrcm+rck`-Merges und reine
  RCK-Events. Belege: TSV 25.07.2026 hat 37 Nennungen und 4 Klassen, aber keine
  `participantsUrl`; das RCK-Rennen vom 21.06.2026 steht auf der
  MyRCM-Veranstalterseite. Ein Scrape zentral in der Map hilft allen Vereinen —
  im Plugin wäre es 400× dasselbe.
- Teilnehmerzahlen reiner RCK-Rennen hält RCK nur für kommende Rennen vor. Seit
  v1.0.64 bleiben sie im Archiv erhalten; **vor** v1.0.64 verlorene Zahlen sind
  weg (einmaliges Nachtragen wäre denkbar, nicht beauftragt).
- **Archiv-Teilnehmer-Links Restfälle** (>12 Mon. + PDF-`url` + id ohne
  `myrcm-event-<n>`) bleiben stumm — siehe „Auto-Archiv & Teilnehmer-Links".
  Bewusst offen gelassen (2026-07-23). Kandidaten: Datei-Import robuster machen
  bzw. WAF-Verdacht beim Textarea-Import prüfen.
- Ideen, nicht beauftragt: iCal-Export, Serien-Filter, Gutenberg-Block, volle
  Adresse (sobald die API ein Feld dafür hat).
