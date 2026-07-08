# RC RaceMap – Kanonisches Renn-Datenmodell

Referenz für die spätere API `GET /api/clubs/{club-id}` und für die Ausrichtung des Plugin-Datenmodells (`class-race.php`).

**Quelle:** Schwesterprojekt `myrcm-rc-map`, Datei `races.json` (Stand: 804 Rennen). Dort gibt es **keinen API-Server** – MyRCM wird per `import-myrcm.js` (cheerio, pdf-parse) in statische JSON-Dateien gescrapet; das Frontend liest diese. Die künftige RC-RaceMap-API wird sehr wahrscheinlich diese Struktur ausliefern.

## Felder pro Rennen

| Feld | Typ | Bedeutung / Hinweis |
|---|---|---|
| `id` | string | Stabiler Slug, z. B. `rcsf-singen-e-v-2025-06-14-myrcm-event-85896`. **Sichtbarkeits-Schlüssel im Plugin.** |
| `hostId` | string | **Veranstalter-Slug** (z. B. `rcsf-singen-e-v`) → das ist die „club-id" |
| `hostName` | string | Veranstalter-Anzeigename |
| `venueId` | string | Strecken-Slug |
| `venueName` | string | Streckenname |
| `venueLocation` | string | Ort |
| `name` | string | Renntitel |
| `from` | date (`YYYY-MM-DD`) | Startdatum |
| `to` | date (`YYYY-MM-DD`) | Enddatum (Mehrtagesveranstaltungen) |
| `series` | string[] | Rennserien, z. B. `["Alpencup"]` |
| `registrationStatus` | enum | `open` \| `closed` \| `upcoming` |
| `registrationOpens` | date\|null | Öffnungsdatum der Nennung |
| `registrationRequiresLogin` | bool | |
| `registrationCount` | int | **Teilnehmerzahl** gesamt |
| `registrationDisplay` | string | vorformatierte Anzeige der Teilnehmerzahl |
| `note` | string | Menschlicher Statustext, z. B. „Nennung geschlossen." |
| `classes` | array | **Gemischt:** Strings **oder** Objekte `{name, entries}` (Teilnehmer je Klasse) |
| `documents` | object[] | `{type, label, sourceLabel, fileName, url}`; `type` z. B. `announcement` |
| `url` | string | MyRCM-Event-URL |
| `detailUrl` | string | Detailseite |
| `registrationListUrl` | string | **Teilnehmerliste** |
| `source` | string | Herkunft, z. B. `myrcm` |
| `firstSeen` | date | Erstmals importiert |

## Beispiel (gekürzt)

```json
{
  "id": "rcsf-singen-e-v-2025-06-14-myrcm-event-85896",
  "hostId": "rcsf-singen-e-v",
  "hostName": "RCSF-Singen e.V.",
  "venueName": "RCSF-Singen e.V.",
  "venueLocation": "Singen",
  "name": "3. Alpencup Wertungslauf 2025",
  "from": "2025-06-14",
  "to": "2025-06-15",
  "series": ["Alpencup"],
  "registrationStatus": "closed",
  "registrationCount": 41,
  "note": "Nennung geschlossen.",
  "classes": [
    "Monster 2WD AC",
    { "name": "2WD Buggy", "entries": 5 },
    { "name": "4WD Buggy", "entries": 13 }
  ],
  "documents": [
    { "type": "announcement", "label": "Ausschreibung", "url": "https://…/Ausschreibung.pdf" }
  ],
  "url": "https://www.myrcm.ch/myrcm/main?…",
  "registrationListUrl": "https://www.myrcm.ch/myrcm/main?…&lType=rList",
  "source": "myrcm"
}
```

## Mapping auf das Plugin (`class-race.php`)

| Plugin | Realmodell |
|---|---|
| `id` | `id` |
| `title` | `name` |
| `organizer` | `hostName` |
| `track` | `venueName` (+ `venueLocation`) |
| `date` (Einzel) | **`from` / `to`** (Bereich) |
| `status` | `registrationStatus` + `note` |
| `participant_count` | `registrationCount` |
| `classes[]` (Strings) | `classes[]` (**Strings + Objekte `{name, entries}`**) |
| `links.participants` | `registrationListUrl` |
| `links.announcement` / `.regulations` | `documents[]` nach `type` |
| `links.registration` | `url` / `detailUrl` |
| — (neu) | `series[]` → künftiger Serien-Filter |
| club-id (numerisch angenommen) | **`hostId` (Slug)** |
