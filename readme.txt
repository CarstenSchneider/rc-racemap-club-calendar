=== RC RaceMap Club Calendar ===
Contributors: rcracemap
Tags: rc, racing, calendar, myrcm, motorsport
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.18
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Zeigt die Renntermine eines einzelnen RC-Modellbauvereins automatisch auf dessen WordPress-Seite an – schlicht, schnell und im Stil des aktiven Themes.

== Description ==

RC RaceMap Club Calendar richtet sich an RC-Modellbauvereine, die ihre kommenden und vergangenen Rennen unkompliziert auf der eigenen Webseite anzeigen möchten.

Das Plugin bringt bewusst **kein eigenes Design** mit, sondern übernimmt Schriftarten, Farben und Linkfarben vom aktiven Theme. Das CSS kümmert sich ausschließlich um Layout (Abstände, responsives Verhalten, Tabs, Listenansicht).

= Funktionen =

* Zwei Tabs: **Kommende Rennen** und **Archiv** – Wechsel ohne Seitenreload
* Pro Rennen: Datum, Titel, Veranstalter, Ort, Rennklassen, Status, Teilnehmerzahl
* Aktionslinks: Nennung, Teilnehmerliste, Ausschreibung, Reglement
* Rennen im Backend gezielt ein-/ausblenden (gespeichert pro Event-ID)
* Neue Rennen sind automatisch sichtbar
* Zwischenspeicherung über WordPress-Transients für hohe Performance
* Vollständig übersetzbar (Textdomain: rc-racemap-club-calendar)
* Mobile First, keine externen Bibliotheken

= Datenquelle =

Die Renndaten werden nicht direkt von MyRCM gescraped, sondern später über eine
RC-RaceMap-API bezogen (`GET /api/clubs/{club-id}`). Solange die API noch nicht
verfügbar ist, arbeitet das Plugin mit mitgelieferten Beispieldaten. Die echte
API lässt sich per Konstante `RC_RCC_API_BASE_URL` oder über den Filter
`rc_rcc_api_base_url` aktivieren.

== Installation ==

1. Den Ordner `rc-racemap-club-calendar` nach `/wp-content/plugins/` hochladen.
2. Das Plugin im Menü „Plugins" aktivieren.
3. Unter **RC RaceMap → Einstellungen** die MyRCM Organisator-ID eintragen.
4. Den Shortcode `[rc_racemap_club_calendar]` auf einer beliebigen Seite einfügen.

== Frequently Asked Questions ==

= Wie blende ich einzelne Rennen aus? =

Unter **RC RaceMap → Rennen verwalten** lässt sich für jedes Rennen der Haken
entfernen. Die Auswahl wird dauerhaft pro Event-ID gespeichert.

= Kann ich das Aussehen anpassen? =

Ja. Alle Templates unter `templates/` lassen sich vom Theme überschreiben,
indem sie unter `wp-content/themes/DEIN-THEME/rc-racemap-club-calendar/`
abgelegt werden. Zusätzlich stehen CSS-Custom-Properties (`--rc-rcc-*`) bereit.

== Changelog ==

= 1.0.12 =
* Neue Aufteilung: „Aktuelle Termine" (dieses Jahr + Zukunft, aufsteigend) und „Archiv" (frühere Jahre, absteigend) – jeweils mit Jahres-Navigation (ohne Seitenreload).
* Aktions-Link und Status richten sich jetzt pro Rennen nach dem Datum: vergangene zeigen „Ergebnisse", kommende „Nennung".
* Aufgeräumt: die nicht mehr benötigten Einstellungen „Anzahl kommender/Archiv-Rennen" entfallen (es werden alle Rennen je Jahr gezeigt).

= 1.0.11 =
* Ort wird wieder angezeigt: Das Feld `city` der API wird jetzt als Quelle für den Ort berücksichtigt (zusätzlich zu `venueLocation`).

= 1.0.10 =
* Archiv-Link „Ergebnisse" führt jetzt korrekt auf die MyRCM-Ergebnisansicht (statt auf die Nennseite).
* MyRCM-Links werden in der Seitensprache geöffnet (pLa) – auf deutschen Seiten also auf Deutsch statt Englisch.

= 1.0.9 =
* Umstellung auf die Live-Datenquelle (rcracemap.com): Renndaten kommen jetzt direkt aus der RC-RaceMap-API statt aus statischen Snapshots – aktueller und vollständiger.

= 1.0.8 =
* Archiv jetzt nach Jahr gruppiert (neuestes Jahr zuerst); Standard zeigt alle vergangenen Rennen.
* Kontextabhängige Links: im Archiv „Ergebnisse" (MyRCM) statt „Nennung"; RCK erhält „Zum Rennen".
* Aufgeräumte Meta-Zeile: Veranstalter/Strecke werden bei Gleichheit nur einmal angezeigt; die Nennungs-Status-Zeile entfällt im Archiv.

= 1.0.7 =
* Echte Renndaten: Das Plugin bezieht die Rennen eines Vereins jetzt anhand seiner MyRCM-Organisator-ID aus der RC-RaceMap-Datenquelle (MyRCM + RCK zusammengeführt). Die Beispieldaten sind nur noch optional für die lokale Entwicklung.
* Generische Renn-Dokumente (neben Ausschreibung/Reglement) werden jetzt ebenfalls verlinkt.

= 1.0.6 =
* Automatische Updates: Neue Versionen können jetzt selbstständig installiert werden (Standard: an, abschaltbar unter Einstellungen → Automatische Updates). Wirkt ausschließlich auf dieses Plugin – andere Plugins, Themes und der WordPress-Core bleiben unberührt.

= 1.0.5 =
* Darstellungsfehler behoben: Bei mehrtägigen Rennen überlappte der lange Datumsbereich in der Desktop-Ansicht den Titel. Die Datumsspalte bricht jetzt sauber um.

= 1.0.4 =
* Fehlerbehebung: Der Kalender zeigte ohne eingetragene Club-ID gar keine Rennen. Im Beispieldaten-Modus wird jetzt immer gerendert; der „Keine Club-ID"-Fehler greift nur noch beim echten API-Zugriff.
* Beispieldaten erhalten datumsrelative Termine (immer einige kommende und vergangene Rennen, unabhängig vom Serverdatum).

= 1.0.3 =
* Einstellungsfeld korrekt als „MyRCM Organisator-ID" beschriftet (Vereine kennen ihre MyRCM-ID, keine RaceMap-Slugs). Beschreibung mit Beispiel ergänzt.

= 1.0.2 =
* Mindestanforderung auf PHP 7.4 gesenkt (Code war bereits kompatibel), damit das Plugin auch auf Servern mit PHP 7.4 installiert werden kann. Empfehlung bleibt PHP 8.x.

= 1.0.1 =
* Datenmodell an die kanonische RC-RaceMap-Struktur (races.json) angeglichen: Datumsbereiche (from/to), Veranstalter/Strecke getrennt, Rennklassen mit Teilnehmerzahl, Dokumente (Ausschreibung/Reglement) und Rennserien.

= 1.0.0 =
* Erste Veröffentlichung.
