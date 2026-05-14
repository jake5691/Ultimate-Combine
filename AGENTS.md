# AGENTS.md

## Projektüberblick

Ultimate Combine ist eine klassische PHP-Webanwendung zur Verwaltung von Ultimate-Combine-Events. Teams können Spieler, Disziplinen und Combines pflegen, Ergebnisse erfassen, Rankings auswerten und Daten bzw. Ergebnisgrafiken teilen.

Es gibt aktuell keinen Composer-, npm- oder Framework-Build-Schritt. Die Anwendung besteht aus PHP-Seiten, gemeinsamen Partials, statischen CSS/JS-Dateien und einer MySQL-Datenbank, deren Schema beim Bootstrap erzeugt bzw. erweitert wird.

## Struktur

- `README.md`: Kurze Produktbeschreibung.
- `Webspace/bootstrap.php`: Gemeinsamer Startpunkt für Sessions, `.secrets/.env`, Sprache, Übersetzungen, PDO-Verbindung, SMTP-Helfer und Datenbankschema.
- `Webspace/index.php`: Login, Team-Registrierung und Admin-Login.
- `Webspace/api/v1/`: Read-only JSON API für externe Clients mit Bearer-Token-Authentifizierung.
- `Webspace/team.php`: Team-Dashboard für Spieler, Combines, Disziplinen und Teamdaten.
- `Webspace/combine.php`: Combine-Detailseite, Ergebnis-Erfassung, Auswertung, CSV-Import/Export und Sharing-Logik.
- `Webspace/admin.php`: Adminbereich für globale Disziplinen, Einheiten, Teams, Feedback und Broadcast-Mails.
- `Webspace/reset-request.php`, `Webspace/reset.php`: Passwort-Reset-Flow für Teams.
- `Webspace/feedback.php`: Feedbackformular.
- `Webspace/impressum.php`: Impressum.
- `Webspace/partials/`: Wiederverwendbares Markup für Head, Header, Footer, Navigation und Formular-/Section-Teile.
- `Webspace/partials/team/`: Formulare und Edit-Views des Team-Dashboards.
- `Webspace/partials/combine/`: Combine-Sections für Start, Edit, Results und Head-to-Head.
- `Webspace/lib/`: Hilfsdateien, Backend-Services und Share-Exports.
- `Webspace/lib/api-auth.php`, `Webspace/lib/api-response.php`: API-Authentifizierung und einheitliche JSON-Antworten.
- `Webspace/lib/api-results.php`: API-Adapter für Combine-Ergebnisdaten; lädt Daten und formt API-Metadaten, enthält aber keine Ranking-Fachlogik.
- `Webspace/lib/ranking-service.php`: Einstiegspunkt für gemeinsame Rankinglogik. Bindet `ranking-core.php`, `ranking-relative.php` und `ranking-absolute.php` ein.
- `Webspace/i18n/de.php`, `Webspace/i18n/en.php`: Übersetzungstabellen.
- `Webspace/js/`: Seitenbezogene Vanilla-JS-Dateien.
- `Webspace/ui.css`: Zentrale Styles, Theme-Variablen und responsive Layouts.
- `Webspace/assets/`: Bilder, Fonts und Webmanifest.

## Lokale Ausführung

Die App erwartet ihre Konfiguration in `.secrets/.env` im Repository-Root. Typische Variablen sind:

```env
DB_HOST=127.0.0.1
DB_NAME=ultimate_combine
DB_USER=root
DB_PASSWORD=
APP_URL=http://127.0.0.1:8000
SMTP_HOST=
SMTP_PORT=587
SMTP_USER=
SMTP_PASS=
SMTP_FROM=
SMTP_FROM_NAME=Ultimate Combine
SMTP_SECURE=tls
```

Für lokale Tests kann die App ohne Build-Schritt mit dem PHP-Built-in-Server gestartet werden:

```sh
php -S 127.0.0.1:8000 -t Webspace
```

Die wichtigsten URLs sind:

- `http://127.0.0.1:8000/index.php`
- `http://127.0.0.1:8000/team.php`
- `http://127.0.0.1:8000/admin.php`

## Entwicklungsregeln

- Halte Änderungen nah am bestehenden Stil: pro Seite ein PHP-Controller mit direkt anschließendem Markup und ausgelagerten Partials für wiederverwendbare Blöcke.
- Lade auf jeder PHP-Seite zuerst `require_once __DIR__ . "/bootstrap.php";`, sofern die Seite Datenbank, Session oder Übersetzungen benötigt.
- Nutze bestehende Helper wie `t()`, `uc_env()`, `uc_base_url()`, `uc_filter_players()` und die Formatierungsfunktionen in `combine.php`, bevor neue Helfer eingeführt werden.
- Verwende PDO Prepared Statements für alle SQL-Queries mit Benutzereingaben.
- Gib HTML-Ausgaben mit `htmlspecialchars(..., ENT_QUOTES, "UTF-8")` aus.
- Behalte Redirects nach Session- oder Schreibaktionen mit `header("Location: ..."); exit;` bei.
- Validierung erfolgt serverseitig. Clientseitiges JS ist nur Komfort und darf keine Sicherheitsannahme ersetzen.
- Neue sichtbare Texte gehören in `Webspace/i18n/de.php` und `Webspace/i18n/en.php`; im PHP immer `t("key", "Fallback")` verwenden.
- Admin- und Team-Zugriffe müssen über Session-Prüfungen geschützt bleiben. Teamdaten immer über `team_id` einschränken.
- API-Zugriffe verwenden keine Sessions. Externe Clients authentifizieren sich über Bearer Tokens aus `api_tokens`; jede Datenquery bleibt strikt auf das Token-Team begrenzt.
- Gemeinsame Backendlogik gehört in `Webspace/lib/` und wird von HTML-Seiten und API-Endpunkten direkt eingebunden. Die HTML-Seiten sollen nicht per HTTP die eigene API aufrufen.
- API-Dateien bleiben dünne JSON-Controller. Datenladen, Ranking und andere Fachlogik gehören in separate Lib-Dateien.
- Halte Lib-Dateien fokussiert. Wenn eine Datei deutlich über ca. 300-400 Zeilen wächst oder zwei Verantwortlichkeiten enthält, splitte sie in kleinere Dateien mit klarem Einstiegspunkt.
- Passwörter und Tokens nur gehasht speichern bzw. vergleichen (`password_hash`, `password_verify`, Token-Hash).
- Keine Secrets, Zugangsdaten oder produktiven Dumps committen. `.secrets/.env` bleibt lokal.

## Datenmodell und Migrationen

Das Schema wird in `uc_ensure_schema(PDO $pdo)` in `Webspace/bootstrap.php` verwaltet. Neue Tabellen oder Spalten sollten dort idempotent ergänzt werden:

- `CREATE TABLE IF NOT EXISTS` für neue Tabellen.
- Vor `ALTER TABLE` vorhandene Spalten über `information_schema.columns` prüfen.
- Doppelte-Spalten-Fehler (`1060`) nur dort abfangen, wo parallele oder alte Zustände realistisch sind.
- Foreign Keys mit passenden `ON DELETE`-Regeln anlegen, analog zu bestehenden Tabellen.

Für Datenänderungen in bestehenden Features immer prüfen, ob abhängige Tabellen betroffen sind:

- `teams`
- `players`
- `combines`
- `disciplines`
- `units`
- `combine_players`
- `combine_disciplines`
- `combine_category_weights`
- `combine_results`
- `feedback`
- `password_resets`
- `api_tokens`
- `admins`

## Frontend-Konventionen

- Es gibt keinen Bundler. JavaScript bleibt in `Webspace/js/*.js` und wird direkt von den PHP-Seiten eingebunden.
- Bestehende JS-Konventionen nutzen `data-*` Attribute, `js-*` Klassen und Vanilla DOM APIs.
- CSS gehört zentral in `Webspace/ui.css`. Nutze vorhandene CSS Custom Properties wie `--bg`, `--surface`, `--ink`, `--muted`, `--accent` und `--accent-2`.
- Theme-Unterstützung für Light/Dark/System nicht brechen. Neue Farben über Variablen oder bestehende Muster ergänzen.
- UI-Änderungen sollen responsive bleiben und mit den bestehenden Klassen (`auth-card`, `panel`, `primary-button`, `segmented`, `field`, `is-hidden`, `is-muted`) harmonieren.
- Barrierefreiheit beibehalten: `aria-expanded`, `aria-pressed`, Labels und sinnvolle Button-Texte weiterführen.

## Qualitätssicherung

Es gibt aktuell keine automatisierte Testsuite. Mindestens ausführen:

```sh
find Webspace -name '*.php' -print0 | xargs -0 -n1 php -l
```

Bei UI- oder Flow-Änderungen zusätzlich manuell im Browser prüfen:

- Registrierung und Login.
- Team-Dashboard mit Spieler-, Combine- und Disziplin-CRUD.
- Combine-Start, Ergebnis-Erfassung, Auswertung und H2H.
- Adminbereich, falls Admin-Logik betroffen ist.
- Sprachwechsel `?lang=de` und `?lang=en`.
- Light/Dark/System-Theme.

## Typische Änderungspfade

- Neuer Team-Text oder Label: `Webspace/i18n/*.php` ergänzen und PHP mit `t()` aktualisieren.
- Neues Formularfeld: serverseitige Validierung, Prepared Statement, Ausgabe mit `htmlspecialchars`, optional JS-Komfort und CSS ergänzen.
- Neue Datenbankspalte: `uc_ensure_schema()` idempotent erweitern, Lese-/Schreibqueries aktualisieren und bestehende NULL-/Default-Fälle berücksichtigen.
- Neue Team-Partial: unter `Webspace/partials/team/` ablegen und aus `team.php` einbinden.
- Neue Combine-Section: unter `Webspace/partials/combine/` ablegen und aus `combine.php` einbinden.
- Neue Frontend-Interaktion: Markup mit `data-*` versehen, Logik in der passenden Datei unter `Webspace/js/` ergänzen.
- Neuer API-Endpunkt: unter `Webspace/api/v1/` anlegen, `api-response.php` und `api-auth.php` verwenden, nur JSON senden und keine HTML-Partials einbinden.
- Neue Ranking- oder Bewertungslogik: in den Ranking-Service unter `Webspace/lib/` einbauen und von API/HTML gemeinsam nutzen, statt Logik in Endpunkten oder Partials zu duplizieren.

## Vorsichtspunkte

- `combine.php` enthält noch viel Auswertungs- und Grafiklogik. Neue Punkteberechnung oder Gewichtungslogik gehört bevorzugt in den Ranking-Service; bestehende HTML-Logik schrittweise dorthin migrieren.
- `bootstrap.php` läuft auf jeder relevanten Seite. Teure oder riskante Schemaänderungen dort besonders konservativ halten.
- `team_id` ist die zentrale Mandantengrenze. Keine Queries ohne Teamfilter einführen, außer bewusst globale Admin-/Disziplin-/Einheitenlogik.
- CSV-, Share- und Bildausgaben dürfen keine vorherige HTML-Ausgabe senden, wenn Header gesetzt werden.
- SMTP wird ohne externe Bibliothek per Socket versendet. Fehlerfälle defensiv behandeln und keine SMTP-Credentials ausgeben.
