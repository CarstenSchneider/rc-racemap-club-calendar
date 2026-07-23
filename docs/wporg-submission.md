# WordPress.org — Einreichung & Pflege

Der GitHub-Kanal (Release-Workflow + Auto-Update der bestehenden Vereine) bleibt
**unverändert**. WP.org ist ein **zweiter, öffentlicher Kanal** aus derselben
Codebasis.

## 1. WP.org-taugliches Paket bauen
```
bash scripts/build-wporg.sh
```
Erzeugt `build-wporg/rc-racemap-club-calendar-wporg-<ver>.zip` — ohne
Selbst-Updater (`class-updater.php` + `includes/lib/plugin-update-checker`), ohne
`Update URI`-Header, mit `Stable tag` = aktueller Version. (Das Script prüft das
selbst.) `class-plugin.php` überspringt den Updater automatisch, wenn er fehlt.

## 2. readme.txt: Pflicht-Abschnitt „External services"
**Vor der Einreichung** in `readme.txt` einfügen (Reviewer verlangen die
Offenlegung aller externen Dienste). Vorlage — bitte in die readme übernehmen und
`Stable tag` auf die aktuelle Version setzen:

```
== External services ==

This plugin connects to external services to display and enrich race data.
No data about your site's visitors is collected or sent.

1) RC RaceMap API — rcracemap.com
   What: fetches the race calendar (dates, classes, documents, results/registration
   links) for the club you configure via the shortcode/settings.
   Data sent: the club's numeric MyRCM organizer ID from the plugin settings.
   No personal or visitor data.
   When: when the calendar is rendered on the front end (cached for the configured
   interval).
   Service: https://rcracemap.com/  ·  Terms/Privacy: https://rcracemap.com/#impressum

2) MyRCM — myrcm.ch (operated by RC-Timing GmbH)
   What: ONLY when a site administrator clicks "Teilnehmer-Links nachtragen"
   (RC RaceMap → Rennen verwalten), the plugin requests public MyRCM report and
   search pages to add per-class participant links and counts to archived events.
   Data sent: public MyRCM event IDs and a search keyword derived from the club
   name. No personal or visitor data.
   When: on demand, only on that admin action; results are cached.
   Service: https://www.myrcm.ch/
```

## 3. readme weiter prüfen
- `Stable tag` == veröffentlichte Version (Trunk). Der Build setzt es im Paket; im
  **Repo/Trunk** ebenfalls mitziehen.
- `Tested up to:` auf die aktuelle WP-Version anheben.
- Durch den [readme-Validator](https://wordpress.org/plugins/developers/readme-validator/)
  jagen (Description, Installation, FAQ, Changelog, Screenshots).
- Naming: „MyRCM"/„RC-Timing" nur beschreibend im Text (Kompatibilität), nicht im
  Plugin-Slug prominent führen (fremde Marke).

## 4. Einreichen
1. WordPress.org-Account.
2. Plugin-ZIP hochladen: https://wordpress.org/plugins/developers/add/
3. **Manuelles Review** (Tage–Wochen). Sie flaggen Probleme → nachbessern.
4. Nach Freigabe: SVN-Repo. Veröffentlichung über `trunk/` + `tags/<ver>/`,
   Store-Assets (Icon/Banner/Screenshots) unter `assets/`.

## Laufende Pflege (nach Freigabe)
- Neue Version: Code + `Stable tag` erhöhen → GitHub-Release (wie gehabt, für die
  Bestandsvereine) UND `bash scripts/build-wporg.sh` → Inhalt nach SVN `trunk/`
  kopieren, `tags/<ver>/` anlegen, `Stable tag` in `trunk/readme.txt` setzen.
- Übersetzungen: WP.org bringt translate.wordpress.org mit; die gebündelten `.mo`
  wirken weiter als Fallback.

## Strategische Notiz
WP.org macht das Plugin für **jeden RC-Verein im WP-Backend auffindbar** — passt
zur Differenzierung „Vereins-Plugin", die MyRCM strukturell nicht besetzt. Der
GitHub-Kanal bleibt für schnelle Iteration + Bestandsvereine.
