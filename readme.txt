=== RC RaceMap Club Calendar ===
Contributors: rcracemap
Tags: rc, racing, calendar, myrcm, motorsport
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.64
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Zeigt die Renntermine eines einzelnen RC-Modellbauvereins automatisch auf dessen WordPress-Seite an – schlicht, schnell und im Stil des aktiven Themes.

== Description ==

RC RaceMap Club Calendar richtet sich an RC-Modellbauvereine, die ihre kommenden und vergangenen Rennen unkompliziert auf der eigenen Webseite anzeigen möchten.

Das Plugin bringt bewusst **kein eigenes Design** mit, sondern übernimmt Schriftarten, Farben und Linkfarben vom aktiven Theme. Das CSS kümmert sich ausschließlich um Layout (Abstände, responsives Verhalten, Tabs, Listenansicht).

= Funktionen =

* Zwei Tabs: **Aktuelle Rennen** und **Vergangene Rennen** – Wechsel ohne Seitenreload, je Tab eine Jahres-Navigation
* Pro Rennen: Datum, Titel, Rennklassen mit Nennzahl, Teilnehmerzahl, Dokumente, Aktion (Nennung / Ergebnisse / „Nennung ab …")
* Rennen im Backend ein-/ausblenden, umbenennen und mit eigenen PDFs versehen (gespeichert pro Event-ID)
* Eigene Termine für Rennen, die in keiner Quelle stehen
* Dauerhaftes Archiv: einmal angezeigte Rennen bleiben erhalten, auch wenn die Quelle sie nicht mehr führt
* Neue Rennen sind automatisch sichtbar
* Zwischenspeicherung über WordPress-Transients für hohe Performance
* In acht Sprachen: Deutsch, Englisch, Französisch, Niederländisch, Italienisch, Spanisch, Tschechisch, Polnisch – folgt der WordPress-Sprache
* Mobile First, keine externen Bibliotheken

= Datenquelle =

Die Renndaten kommen über die RC-RaceMap-API (`GET /api/clubs/{club-id}`), die
MyRCM, RCK und DMC zusammenführt. Die Basis-URL lässt sich per Konstante
`RC_RCC_API_BASE_URL` oder über den Filter `rc_rcc_api_base_url` umstellen.

== Installation ==

Ausführliche Schritt-für-Schritt-Anleitung: `docs/anleitung.md`.

1. Die ZIP von der Release-Seite laden und unter **Plugins → Installieren →
   Plugin hochladen** einspielen, dann aktivieren.
2. Unter **RC RaceMap → Einstellungen** die MyRCM Organisator-ID eintragen –
   die Zahl hinter `dId[O]=` in der Adresse eurer MyRCM-Vereinsseite.
3. Den Shortcode `[rc_racemap_club_calendar]` auf einer beliebigen Seite einfügen.
4. Prüfen, dass unter **Einstellungen → Allgemein** Sprache und Datumsformat
   stimmen – der Kalender übernimmt beides von WordPress.

== Frequently Asked Questions ==

= Wie blende ich einzelne Rennen aus? =

Unter **RC RaceMap → Rennen verwalten** lässt sich für jedes Rennen der Haken
entfernen. Die Auswahl wird dauerhaft pro Event-ID gespeichert.

= Warum stehen die Monatsnamen auf Englisch? =

Der Kalender übernimmt Sprache und Datumsformat von WordPress. Fehlen die
deutschen Sprachdateien des Kerns, bleiben die Monatsnamen englisch, obwohl die
Sprache auf Deutsch steht. Erkennbar an der Begrüßung oben rechts: steht dort
„Howdy" statt „Hallo", fehlen sie. Abhilfe: **Dashboard → Aktualisierungen →
Übersetzungen aktualisieren**.

= Kann ich das Aussehen anpassen? =

Ja. Alle Templates unter `templates/` lassen sich vom Theme überschreiben,
indem sie unter `wp-content/themes/DEIN-THEME/rc-racemap-club-calendar/`
abgelegt werden. Zusätzlich stehen CSS-Custom-Properties (`--rc-rcc-*`) bereit.

== Changelog ==

= 1.0.64 =
* Teilnehmerzahlen und Klassen bleiben erhalten, auch wenn eine Quelle sie zwischenzeitlich nicht mehr meldet. Bisher konnte ein Rennen nach dem Renntag seine Nennzahl und die Klassenliste verlieren.
* Ein eigenes Dokument mit dem Namen „Ergebnisse“ füllt jetzt den Ergebnisse-Button. So lässt sich auch für Rennen ohne MyRCM-Ergebnisseite ein Ergebnis-PDF verlinken – es erscheint dann als Button statt in der Dokumentspalte.

= 1.0.56 – 1.0.63 =
* Klassen-Pillen archivierter Rennen verlinken ebenfalls auf die Teilnehmerliste; gedämpfte Pillen sind besser lesbar.
* Neuer Knopf „Teilnehmer-Links nachtragen“ unter „Rennen verwalten“: trägt die Links zu den Teilnehmerlisten für bereits archivierte Rennen einmalig nach.

= 1.0.55 =
* Klassen-Pillen: Link nur noch bei vorhandenen Nennungen (0-Nennungen-Klassen führten zu einer leeren Teilnehmerliste); nicht verlinkte Pillen sind gedämpft. Hover hebt die klickbare Pille per Invertierung hervor.

= 1.0.54 =
* Klassen-Pillen sind jetzt Links zur Teilnehmerliste der jeweiligen Klasse (MyRCM v9 hat keine kombinierte Gesamtliste mehr). Die Gesamt-Teilnehmerzahl wird weiter angezeigt, aber ohne Link. Verlinkte Pillen erscheinen für neu aktualisierte Rennen; Archiv-Rennen zeigen die Pillen wie bisher.

= 1.0.53 =
* Teilnehmer-Links repariert: sie zeigten (v.a. bei älteren Rennen) auf die tote MyRCM-Altroute und landeten auf einer generischen Übersicht. Sie führen jetzt korrekt auf die Nennliste des Events (/report/<id>).

= 1.0.52 =
* Jahres-Umschalter im Admin („Rennen verwalten") repariert: ältere Jahrgänge (2024, 2023 …) waren nicht erreichbar – ein Klick sprang immer aufs neueste Jahr zurück. Damit lassen sich jetzt auch alte Rennen wieder bearbeiten.

= 1.0.51 =
* MyRCM-Links (Nennung, Ergebnisse) öffnen wieder in der Seitensprache. Nach dem MyRCM-Redesign steckt die Sprache im Pfad (/de/…, /en/…); der Kalender biegt sie jetzt korrekt auf die WordPress-Sprache um.

= 1.0.50 =
* Nennung-Button etwas kompakter und mit gleichem Eckenradius wie das Kalenderblatt.
* „Nennung ab …" nutzt ein kürzeres Datum (z. B. „3 Aug 2026") und bricht den Pfeil nicht mehr in eine eigene Zeile um.

= 1.0.49 =
* „Ergebnisse" führt wieder auf die richtige Seite: MyRCM hat die Ergebnis-URL im Redesign geändert (jetzt /report/<id>). Der alte Link landete auf einer Übersicht. Funktioniert mit alten wie neuen Datenständen.

= 1.0.48 =
* „Ergebnisse" erscheint nur noch, wenn es eine echte Ergebnisseite gibt (MyRCM). Sonst kein Button – Ergebnisse stehen auf MyRCM oder als hochgeladenes PDF in der Dokumentspalte.

= 1.0.47 =
* Datum als Kalenderblatt (Tag groß, Monatsband in der Akzentfarbe, Jahr dezent); mehrtägige Rennen zeigen zwei Blätter.
* „Nennung ab <Datum>" ist jetzt ein Link zur MyRCM-Nennseite (unterstrichen, mit Pfeil, in der Linkfarbe).
* Aktion je Rennen klarer: nur noch Nennung, Ergebnisse oder „Nennung ab …" – kein „Zum Rennen" mehr.
* Gefüllter Nennung-Button und Monatsband nutzen bei heller Akzentfarbe automatisch dunkle Schrift (bessere Lesbarkeit).

= 1.0.46 =
* Nach einem Update wird der Datencache einmal geleert – Änderungen an der Datenquelle greifen sofort statt erst nach Cache-Ablauf.

= 1.0.45 =
* „Stand" zeigt jetzt den Import-Zeitpunkt der Datenquelle statt der Abrufzeit des Plugins. Erscheint, sobald die Quelle ihn liefert.

= 1.0.44 =
* Fußbereich zeigt links den Stand der Daten („Stand: 00:00h, 01.01.26"), lokalisiert.

= 1.0.43 =
* Acht Sprachen (de, en, fr, nl, it, es, cs, pl); Sprache folgt der WordPress-Einstellung.
* Der CSV-Import versteht die Spaltennamen in allen acht Sprachen.

= 1.0.39 =
* Schmalere Spalten und engere Klassen-Pillen – mehr Platz für die Rennklassen.

= 1.0.37 – 1.0.38 =
* Der Kalender übernimmt Schrift und Schriftgrößen des Themes; die mitgelieferte Schrift entfällt (133 KB weniger).
* Leere Spalten werden nicht mehr ausgegeben – keine großen Lücken mehr auf dem Handy.

= 1.0.34 – 1.0.36 =
* Dauerhaftes Archiv: einmal angezeigte Rennen bleiben erhalten, auch wenn die Quelle sie nach einigen Wochen nicht mehr führt.
* Jahres-Navigation auch im Backend.
* Historie einspielen (JSON), inkl. Titel, Dokumenten und Teilnehmerzahlen.

= 1.0.30 – 1.0.33 =
* Eigene PDFs je Rennen und eigene Termine für Rennen, die in keiner Quelle stehen.
* Titel je Rennen überschreibbar.
* Jedes Release enthält ein installierbares Plugin-ZIP.

= 1.0.28 – 1.0.29 =
* Zusammengeführte RCK+MyRCM-Rennen; Aufteilung nach Renndatum statt Kalenderjahr.
* Einstellungsseite auf vier Felder reduziert.

= 1.0.22 =
* Teilnehmerzahl wird nur noch unterstrichen, wenn sie tatsächlich zur Teilnehmerliste verlinkt ist (z. B. bei RCK-Rennen ohne Liste ist sie nun nicht mehr fälschlich als Link markiert).

= 1.0.21 =
* Nennung-Button: Schrift und Icon jetzt zuverlässig lesbar (fester heller Farbwert statt Systemfarbe – vermeidet, dass die Schrift auf die Theme-Linkfarbe zurückfällt und unsichtbar wird).
* Mehr Abstand ober- und unterhalb der Jahres-Navigation.

= 1.0.20 =
* Datum in Titelgröße; Datum des kommenden Rennens in voller Textfarbe (vergangene gedämpft).
* Icons vor den Aktionen: Stift (Nennung), Pokal (Ergebnisse), Dokument (Ausschreibung).
* Nennung-Button: Schrift und Icon in der Seitenfarbe, damit sie auf der Akzentfläche gut lesbar sind.

= 1.0.13 – 1.0.19 =
* Neues, ruhiges und theme-unabhängiges Kalender-Design (Zeilen-Layout, Inline-Icons); im Admin einstellbare Akzentfarbe; leere Musterdaten; englische Übersetzung (en_US/en_GB).

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
