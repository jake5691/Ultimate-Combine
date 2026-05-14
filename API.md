# Ultimate Combine API v1

Die API ist aktuell read-only und liegt unter `Webspace/api/v1/`. Sie nutzt keine PHP-Session, sondern Bearer Tokens im `Authorization` Header.

## Authentifizierung

Requests auf Daten-Endpunkte benötigen:

```http
Authorization: Bearer <token>
```

Falls ein Hosting-Setup den `Authorization` Header nicht an PHP weiterreicht, kann alternativ dieser Header verwendet werden:

```http
X-API-Token: <token>
```

Tokens werden in der Tabelle `api_tokens` nur als SHA-256-Hash gespeichert. Ein Token sollte ausreichend zufällig sein, zum Beispiel:

Eingeloggte Teams können read-only Tokens direkt auf `team.php` im Bereich `API Zugriff` erstellen und widerrufen. Der Klartext-Token wird nur direkt nach dem Erstellen angezeigt.

Produktiv sollte die App nur über HTTPS erreichbar sein. Tokens dürfen nicht in URLs verwendet werden.

## Response-Format

Erfolgreiche Antworten:

```json
{
  "data": [],
  "meta": {
    "team_id": 1,
    "count": 0
  }
}
```

Fehler:

```json
{
  "error": {
    "code": "unauthorized",
    "message": "Invalid bearer token."
  }
}
```

## Endpunkte

### API Info

```http
GET /api/v1/index.php
```

Öffentlicher Status-Endpunkt ohne Teamdaten.

### Players

```http
GET /api/v1/players.php
GET /api/v1/players.php?id=123
```

Liefert Spieler des authentifizierten Teams.

### Disciplines

```http
GET /api/v1/disciplines.php
GET /api/v1/disciplines.php?id=123
```

Liefert globale Disziplinen und team-eigene Disziplinen. Das Feld `scope` ist `global` oder `team`.

### Combines

```http
GET /api/v1/combines.php
GET /api/v1/combines.php?id=123
```

Ohne `id` wird die Combine-Liste geliefert. Mit `id` enthält die Antwort zusätzlich zu den Combine-Daten die zugewiesenen Spieler-IDs, Disziplinen und Kategorie-Gewichte.

### Results

```http
GET /api/v1/results.php?combine_id=123
GET /api/v1/results_raw.php?combine_id=123
GET /api/v1/results_relative.php?combine_id=123
GET /api/v1/results_absolut.php?combine_id=123
```

`results.php` bleibt aus Kompatibilitätsgründen als Alias für `results_raw.php` bestehen.

`results_raw.php` liefert Rohdaten für ein Combine:

- `combine`
- `players`
- `disciplines`
- `category_weights`
- `results`

`results_relative.php` liefert nur das relative Ranking:

- `combine`
- `ranking.overall`
- `ranking.disciplines`

Das relative Ranking folgt der Ergebnisansicht: pro Disziplin erhalten die besten Werte 2 Punkte, die schlechtesten Werte 1 Punkt, fehlende Werte 0 Punkte. Das Overall-Ranking nutzt Disziplin- und Kategorie-Gewichte.

`results_absolut.php` liefert nur das absolute Ranking:

- `combine`
- `ranking.overall`
- `ranking.disciplines`

Das absolute Ranking nutzt die erwarteten Min-/Max-Werte der Disziplinen. Disziplinen ohne absolute Skala werden im Overall übersprungen und in der Disziplinliste mit `has_absolute_scale: false` markiert. Zusätzlich existiert `results_absolute.php` als englischer Alias.

### Radar

```http
GET /api/v1/radar.php?combine_id=123
GET /api/v1/radar.php?combine_id=123&player_id=456
GET /api/v1/radar.php?combine_id=123&player_id=456&overall=avg
GET /api/v1/radar.php?combine_id=123&player_id=456&compare_player_id=789
```

Liefert Radargraph-Werte pro Spieler und Kategorie. Ohne `player_id` enthält `items` alle Spieler des Combines. `overall` kann `sum`, `avg` oder `abs` sein und entspricht den Modi der Ergebnisansicht.

Jedes Item enthält:

- `player_id`
- `player_name`
- `mode`
- `radar` mit Einträgen aus `label`, `player` und `team`

Mit `compare_player_id` liefert der Endpunkt die H2H-Radarwerte direkt in `data.radar`. Jeder Eintrag enthält:

- `label`
- `player`
- `playerB`
- `team`

## Statuscodes

- `200`: OK
- `400`: Ungültige Parameter
- `401`: Fehlender oder ungültiger Token
- `403`: Token ohne `read` Scope
- `404`: Datensatz nicht gefunden oder nicht im Team-Kontext
- `405`: Methode nicht erlaubt
- `503`: Datenbank nicht erreichbar

## Beispiel

```sh
curl -H "Authorization: Bearer uc_read_..." \
  http://127.0.0.1:8000/api/v1/players.php
```

Fallback mit `X-API-Token`:

```sh
curl -H "X-API-Token: uc_read_..." \
  http://127.0.0.1:8000/api/v1/players.php
```
