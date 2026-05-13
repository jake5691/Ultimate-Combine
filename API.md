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
```

Liefert Rohdaten für ein Combine:

- `combine`
- `players`
- `disciplines`
- `category_weights`
- `results`

Die API berechnet aktuell keine Rankings oder Punkte. Externe Clients können damit auf stabilen Rohdaten arbeiten; ein späterer Rankings-Endpunkt sollte einen eigenen Contract bekommen.

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
