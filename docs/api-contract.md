# RC RaceMap API – Vertrag für das Club-Calendar-Plugin

Dies ist der **maßgebliche Vertrag** zwischen dem WordPress-Plugin *RC RaceMap Club Calendar* (Konsument) und der RC-RaceMap-Datenquelle (Anbieter, Projekt `myrcm-rc-map`).

Das Plugin spricht diesen Endpoint bereits. Solange die Live-API fehlt, wird er durch statische Snapshots im Repo [rc-racemap-data](https://github.com/CarstenSchneider/rc-racemap-data) erfüllt (`api/clubs/{id}`). Die Live-API muss **exakt dieselbe Form** liefern, dann genügt im Plugin ein Umstellen der Basis-URL (Konstante `RC_RCC_API_BASE_URL` / Filter `rc_rcc_api_base_url`) – **keine Codeänderung**.

## Endpoint

```
GET {base}/api/clubs/{myrcmOrgId}
```

- `{myrcmOrgId}` = **numerische MyRCM-Organisator-ID** (das ist die Nutzereingabe im Plugin, z. B. `18244`). **Nicht** der Slug.
- Auth: aktuell keine (öffentlich). Antwort: `application/json` (Content-Type unkritisch, das Plugin dekodiert den Body).
- Unbekannte ID: HTTP `404` (das Plugin zeigt dann einen leeren Zustand/Hinweis).

## Antwort-Format

```json
{
  "club": {
    "myrcmOrgId": "18244",
    "hostId": "tsv-mariendorf",
    "name": "TSV Mariendorf 1897 RCCR",
    "website": "http://www.msv06-rccar.de/",
    "location": "Berlin",
    "lat": 52.410703,
    "lng": 13.321052,
    "venueName": "…",
    "venueCity": "…"
  },
  "events": [ /* siehe unten */ ]
}
```

`club` liefert die Vereinsmeta (für Branding/Kartenlink im Plugin). `events` ist die nach Datum sortierte Liste der Rennen dieses Vereins, **MyRCM + RCK zusammengeführt**.

### Event-Felder (kanonisch, siehe auch `rc-racemap-data-model.md`)

| Feld | Typ | Hinweis |
|---|---|---|
| `id` | string | stabil, eindeutig (Sichtbarkeits-Schlüssel im Plugin). Bei MyRCM-Herkunft enthält sie `myrcm-event-<N>`; das Plugin leitet daraus die Ergebnis-URL ab. |
| `name` | string | Renntitel |
| `hostName` | string | Veranstalter – **kanonischer** Vereinsname (= `hosts.json`), siehe Fix unten |
| `venueName` | string | Streckenname (= `venues.json`) |
| `venueLocation` | string | Ort |
| `from` / `to` | `YYYY-MM-DD` | Datumsbereich (mehrtägig unterstützt) |
| `series` | string[] | Rennserien |
| `registrationStatus` | `open`\|`closed`\|`upcoming`\|`login_required` | `login_required` wird tatsächlich geliefert (MyRCM zeigt die Nennung nur eingeloggt), war bisher nicht dokumentiert |
| `registrationCount` | int | Teilnehmerzahl |
| `note` | string | Statustext (z. B. „Nennung geschlossen.") |
| `classes` | array | Strings **oder** `{name, entries}`; `entries` kann fehlen oder `null` sein (dann zeigt das Plugin die Klasse ohne Zahl). Mischformen in einem Array sind erlaubt. |
| `documents` | object[] | `{type, label, url}`; `type` u. a. `announcement`, `rules`, sonst generisch |
| `url` / `detailUrl` | string | Event-Seite (bei MyRCM zugleich **Ergebnis**-Seite für vergangene Rennen). Bei `myrcm+rck` zeigt sie auf **RCK**; die Ergebnisse liegen dann weiterhin auf MyRCM. Das Plugin leitet sie aus der `id` ab (`…-myrcm-event-<N>`), nicht aus einer URL – die Ableitung bleibt damit gültig, falls sich die URL-Belegung des Events ändert. |
| `registrationListUrl` | string | Teilnehmerliste |
| `source` | string | Eine **oder mehrere** Quellen, `+`-getrennt. Geliefert werden u. a. `myrcm`, `rck-kleinserie`, `rck-challenge`, **`dmc`** und – für zusammengeführte Cross-Listings – **`myrcm+rck`**. Kein Enum mit genau einem Wert: das Plugin prüft per Teilstring (`stripos` auf `rck`), nicht per `===`. |

Das Plugin normalisiert diese Felder in `class-race.php` (`from_array`). Zusätzliche Felder sind erlaubt und werden ignoriert.

## Ableitung (Anbieterseite)

1. `myrcmOrgId` → `hostId` via `hosts.json` (`myrcmOrgId`-Feld, 177/177 vorhanden).
2. `races.json`, `rck-races.json` **und** `dmc-races.json` nach `hostId` filtern
   und mergen.
   Deduplizierung per `id` reicht **nicht**: die IDs werden pro Quelle gebildet und
   kollidieren fuer dieselbe Veranstaltung nie. Cross-Listings (der Verein schreibt
   ein RCK-Rennen zusaetzlich auf MyRCM aus) werden ueber **gleichen `hostId` +
   RCK-Renntag innerhalb der MyRCM-Spanne `from`…`to`** erkannt und zu **einem**
   Event zusammengefuehrt:
   - von **MyRCM**: `documents`, `from`/`to`, `venueId`/`venueName`
   - von **RCK**: `title`, `registrationStatus`, `registrationCount`,
     `registrationOpens`, `registrationDeadline`, `registrationRequiresLogin`,
     `url`, `note`
   - `classes`: **Union** beider Quellen; wo ein Klassenname in beiden vorkommt,
     gewinnen die **RCK-`entries`**. Hat RCK für die Veranstaltung noch keine
     Nennungen, fehlt `entries` – die Klasse bleibt in der Liste, nur ohne Zahl.
   - `registrationListUrl` bleibt **MyRCM** (dort liegen Teilnehmerliste und
     Ergebnisse, auch wenn über RCK genannt wird)
   - `source` = `myrcm+rck`

   Gruende: Bei RCK-Serienrennen ist RCK die Nennplattform und traegt den
   korrekten Nennstatus. Der **Titel** kommt von RCK, weil er dort kanonisch ist
   (`RCK Challenge Ost - Zwickau`), waehrend der MyRCM-Titel vom Verein frei
   eingetragen und teils nicht identifizierend ist (`RCK-Challenge` steht bei
   mehreren Vereinen). Der **Zeitraum** kommt von MyRCM, weil er den Trainingstag
   mit umfasst; RCK kennt nur den Wertungstag. Dokumente sind bei MyRCM
   vollstaendiger.

   **DMC** (`source: dmc`) wird zuletzt angehaengt, sofern kein MyRCM-/RCK-Rennen
   auf derselben Venue im ueberlappenden Zeitraum liegt. DMC-Rennen liefern
   typischerweise Titel, Datum, Klassen und ein Ausschreibungs-PDF, aber **kein**
   `url`, keinen `registrationStatus` und keine Teilnehmerzahl. Das Plugin stellt
   sie deshalb ohne Aktion in Spalte 5 dar – es gibt nichts zu verlinken.
3. Vereinsmeta aus `hosts.json` (name/website) + `venues.json` (lat/lng/city, Match über `hostIds`/`myrcmOrgId`).
4. Als `{ club, events }` ausliefern.

Referenz-Implementierung dieses Formats: die Snapshots in `rc-racemap-data` (`api/clubs/18244`, `api/clubs/45925`).

## Später (nicht blockierend)

Für das geplante Free/Paid-Modell sollte die API pro Verein perspektivisch **`tier`** (`free`/`paid`) und optionale **`ads`** mitliefern – die Response-Struktur ist dafür offen (zusätzliche Top-Level-Keys).
