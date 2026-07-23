# RC RaceMap Club Calendar — Anleitung

Der Kalender zeigt die Renntermine deines Vereins auf eurer WordPress-Seite an.
Die Daten kommen automatisch von MyRCM, RCK und DMC — ihr müsst nichts von Hand
pflegen.

Für die Einrichtung brauchst du **15 Minuten** und einen WordPress-Zugang mit
Administrator-Rechten.

---

## 1. Plugin herunterladen

[**rc-racemap-club-calendar.zip herunterladen**](https://github.com/CarstenSchneider/rc-racemap-club-calendar/releases/latest/download/rc-racemap-club-calendar.zip)

Der Link führt immer zur aktuellen Version. Die Datei nicht entpacken —
WordPress erwartet sie als ZIP.

## 2. Installieren

1. In WordPress einloggen.
2. Links im Menü auf **Plugins → Installieren**.
3. Oben auf **Plugin hochladen**.
4. **Datei auswählen**, die heruntergeladene ZIP wählen, **Jetzt installieren**.
5. Danach auf **Plugin aktivieren**.

Im Menü links erscheint jetzt der Punkt **RC RaceMap**.

## 3. Vereins-ID eintragen

Der Kalender muss wissen, welcher Verein ihr seid. Dafür dient die
**Organisator-ID**, die MyRCM jedem Verein zuweist.

**So findest du sie:** Ruf eure Vereinsseite bei MyRCM auf. In der Adresszeile
steht sie hinter `dId[O]=`:

```
myrcm.ch/myrcm/main?dId[O]=18244&hId[1]=org
                            ^^^^^
```

Diese Zahl unter **RC RaceMap → Einstellungen** in das Feld
**MyRCM Organisator-ID** eintragen und speichern.

> Wenn ihr keine MyRCM-ID habt, funktioniert der Kalender trotzdem — dann könnt
> ihr eure Termine von Hand eintragen (siehe „Eigene Termine").

## 4. Kalender auf eine Seite setzen

1. **Seiten → Erstellen**, zum Beispiel mit dem Titel „Termine".
2. Einen Block **Shortcode** einfügen.
3. Hineinschreiben:

   ```
   [rc_racemap_club_calendar]
   ```

4. Seite veröffentlichen.

Fertig — die Rennen erscheinen automatisch.

## 5. Sprache und Datumsformat prüfen

Der Kalender übernimmt Sprache und Datumsformat von WordPress. Ist beides nicht
sauber eingestellt, stehen dort englische Monatsnamen („May" statt „Mai").

- **Einstellungen → Allgemein → Sprache der Website**: Deutsch.
- **Einstellungen → Allgemein → Datumsformat**: `j. F Y` ergibt „3. August 2026".
- Steht dort schon Deutsch und die Monate bleiben englisch, fehlen die
  Sprachdateien: **Dashboard → Aktualisierungen → Übersetzungen aktualisieren**.
  Ein sicheres Erkennungszeichen ist die Begrüßung oben rechts — steht dort
  „Howdy" statt „Hallo", fehlen sie.

---

## Was du sonst noch einstellen kannst

Unter **RC RaceMap → Einstellungen**:

| Einstellung | Bedeutung |
|---|---|
| **Akzentfarbe** | Farbe für Links und den Nennung-Button. Leer lassen = Linkfarbe eures Themes. |
| **Termine aktualisieren** | Wie oft nach neuen Rennen gesucht wird. „Stündlich" ist für alle Vereine passend. |
| **Automatische Updates** | Neue Versionen des Plugins installieren sich selbst. Empfohlen. |

Schrift und Schriftgrößen kommen von eurem Theme — dazu gibt es nichts
einzustellen.

## Rennen verwalten

Unter **RC RaceMap → Rennen verwalten** findest du alle Rennen, nach Jahren
getrennt.

**Ein Rennen ausblenden** — den Haken links entfernen und speichern. Neue Rennen
sind immer automatisch sichtbar.

**Einen Titel ändern** — in das Titelfeld schreiben. Manche Rennen heißen bei
MyRCM anders, als ihr sie ankündigt. Das Feld leer lassen übernimmt den Titel der
Quelle; der steht als blasser Platzhalter darin.

**Eigene PDFs hinterlegen** — in der Spalte „Eigene Dokumente" auf
**Datei wählen**. Es öffnet sich die Medienverwaltung von WordPress, dort lädst
du das PDF hoch oder wählst ein vorhandenes. Bis zu fünf Dokumente pro Rennen.
Sie erscheinen im Kalender hinter denen, die MyRCM oder RCK schon mitliefern.

Gibt es dort bereits ein Dokument mit derselben Bezeichnung — etwa eine
Ausschreibung —, ersetzt deins es. So kannst du eine korrigierte Fassung
nachreichen, ohne dass beide nebeneinander stehen.

**Ein Ergebnis-PDF verlinken** — nenne das Dokument „Ergebnisse". Dann steht es
nicht in der Dokumentspalte, sondern füllt den **Ergebnisse**-Knopf des Rennens.
Das ist der Weg für Rennen, deren Ergebnisse nicht bei MyRCM liegen — etwa weil
die Nennung über RCK lief.

**Eigene Termine** — für Rennen, die weder bei MyRCM noch bei RCK oder DMC
stehen: Clublauf, Vereinsmeisterschaft, Trainingstag. Im Abschnitt „Eigene
Termine" Bezeichnung und Datum eintragen. Sie erscheinen im Kalender zwischen den
übrigen, nach Datum einsortiert. Zum Entfernen die Bezeichnung löschen und
speichern.

## Ältere Rennen nachtragen

Die Datenquellen reichen nur wenige Wochen zurück. Ab der Installation merkt sich
der Kalender alles, was er einmal gesehen hat — ältere Rennen kennt er aber
naturgemäß nicht.

Wenn ihr eure Historie noch auf der alten Vereinsseite habt, lässt sie sich
einmalig einspielen: **Rennen verwalten → Historie einspielen**.

Am einfachsten als Tabelle. Leg sie in Excel, Numbers oder LibreOffice an, kopier
sie und füg sie in das Feld ein:

```
Von;Bis;Titel;Ausschreibung;Reglement;Ergebnisse;Teilnehmer;Klassen
26.07.2025;27.07.2025;RCK Kleinserie;https://…/aus.pdf;https://…/regl.pdf;https://…;42;Stock, Fun
05.10.2025;;Clublauf Herbst;;;;;
```

Pflicht sind nur **Von** und **Titel**, alles andere kannst du weglassen — auch
ganze Spalten. Das Datum darf `26.07.2025` oder `2025-07-26` heißen, mehrere
Klassen trennst du mit Komma. Die Spalte **Ergebnisse** wird zum Button rechts,
**Ausschreibung** und **Reglement** erscheinen als Links daneben.

Ein zweiter Import derselben Tabelle korrigiert die Einträge, statt sie zu
verdoppeln — du kannst also nachbessern und erneut einfügen.

## Updates

Sind die automatischen Updates aktiv, passiert nichts weiter — neue Versionen
installieren sich innerhalb eines Tages von selbst. Sofort geht es über
**Plugins → Jetzt aktualisieren**.

---

## Wenn etwas nicht stimmt

**Es werden gar keine Rennen angezeigt.**
Prüfe die Organisator-ID unter Einstellungen. Danach unter „Rennen verwalten" auf
**Daten aktualisieren** — das umgeht den Zwischenspeicher.

**Ein Rennen fehlt.**
Steht es bei MyRCM, RCK oder DMC? Nur was dort ausgeschrieben ist, findet den Weg
in den Kalender. Alles andere trägst du als eigenen Termin ein.

**Änderungen am Aussehen kommen nicht an.**
Manche Performance-Plugins entfernen die Versionskennung an den CSS-Dateien;
dann behält der Browser die alte Fassung. Einmal hart neu laden — Strg+F5 unter
Windows, ⌘⇧R auf dem Mac.

**Ein Rennen steht doppelt.**
Melde dich mit dem Datum. Das kann passieren, wenn dasselbe Rennen bei zwei
Quellen unterschiedlich eingetragen ist.
