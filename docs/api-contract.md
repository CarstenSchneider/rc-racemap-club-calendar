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
| `id` | string | stabil, eindeutig (Sichtbarkeits-Schlüssel im Plugin) |
| `name` | string | Renntitel |
| `hostName` | string | Veranstalter – **kanonischer** Vereinsname (= `hosts.json`), siehe Fix unten |
| `venueName` | string | Streckenname (= `venues.json`) |
| `venueLocation` | string | Ort |
| `from` / `to` | `YYYY-MM-DD` | Datumsbereich (mehrtägig unterstützt) |
| `series` | string[] | Rennserien |
| `registrationStatus` | `open`\|`closed`\|`upcoming` | |
| `registrationCount` | int | Teilnehmerzahl |
| `note` | string | Statustext (z. B. „Nennung geschlossen.") |
| `classes` | array | Strings **oder** `{name, entries}` |
| `documents` | object[] | `{type, label, url}`; `type` u. a. `announcement`, `rules`, sonst generisch |
| `url` / `detailUrl` | string | Event-Seite (bei MyRCM zugleich **Ergebnis**-Seite für vergangene Rennen) |
| `registrationListUrl` | string | Teilnehmerliste |
| `source` | string | **`myrcm`** oder **`rck`** – das Plugin unterscheidet darüber (RCK zeigt „Zum Rennen" statt „Ergebnisse") |

Das Plugin normalisiert diese Felder in `class-race.php` (`from_array`). Zusätzliche Felder sind erlaubt und werden ignoriert.

## Ableitung (Anbieterseite)

1. `myrcmOrgId` → `hostId` via `hosts.json` (`myrcmOrgId`-Feld, 177/177 vorhanden).
2. `races.json` **und** `rck-races.json` nach `hostId` filtern, mergen, per `id` deduplizieren.
3. Vereinsmeta aus `hosts.json` (name/website) + `venues.json` (lat/lng/city, Match über `hostIds`/`myrcmOrgId`).
4. Als `{ club, events }` ausliefern.

Referenz-Implementierung dieses Formats: die Snapshots in `rc-racemap-data` (`api/clubs/18244`, `api/clubs/45925`).

## Später (nicht blockierend)

Für das geplante Free/Paid-Modell sollte die API pro Verein perspektivisch **`tier`** (`free`/`paid`) und optionale **`ads`** mitliefern – die Response-Struktur ist dafür offen (zusätzliche Top-Level-Keys).
