<?php
require_once __DIR__ . "/bootstrap.php";

$baseUrl = uc_base_url($env);
$apiBaseUrl = rtrim($baseUrl, "/") . "/api/v1";
$tokenPlaceholder = "uc_read_...";

$endpointGroups = [
  [
    "title" => t("api.docs.players.title", "Players"),
    "description" => t("api.docs.players.body", "Liefert Spieler des authentifizierten Teams."),
    "requests" => [
      "GET /api/v1/players.php",
      "GET /api/v1/players.php?id=123",
    ],
    "parameters" => [
      ["name" => "id", "required" => false, "description" => t("api.docs.param.id", "Optional. Liefert genau einen Datensatz mit dieser ID.")],
    ],
    "fields" => [
      "id",
      "first_name",
      "last_name",
      "jersey_number",
      "gender",
      "position_handler",
      "position_cutter",
      "created_at",
    ],
  ],
  [
    "title" => t("api.docs.disciplines.title", "Disciplines"),
    "description" => t("api.docs.disciplines.body", "Liefert globale Disziplinen und team-eigene Disziplinen. Das Feld scope ist global oder team."),
    "requests" => [
      "GET /api/v1/disciplines.php",
      "GET /api/v1/disciplines.php?id=123",
    ],
    "parameters" => [
      ["name" => "id", "required" => false, "description" => t("api.docs.param.id", "Optional. Liefert genau einen Datensatz mit dieser ID.")],
    ],
    "fields" => [
      "id",
      "team_id",
      "discipline_name",
      "description",
      "unit",
      "category",
      "rating_direction",
      "expected_min",
      "expected_max",
      "bonus_relative",
      "bonus_absolute",
      "created_at",
      "scope",
    ],
  ],
  [
    "title" => t("api.docs.combines.title", "Combines"),
    "description" => t("api.docs.combines.body", "Liefert Combines. Mit id enthält die Antwort zusätzlich zugewiesene Spieler, Disziplinen und Kategorie-Gewichte."),
    "requests" => [
      "GET /api/v1/combines.php",
      "GET /api/v1/combines.php?id=123",
    ],
    "parameters" => [
      ["name" => "id", "required" => false, "description" => t("api.docs.param.id", "Optional. Liefert genau einen Datensatz mit dieser ID.")],
    ],
    "fields" => [
      "id",
      "combine_name",
      "event_date",
      "combine_location",
      "combine_notes",
      "created_at",
      "player_ids",
      "disciplines[].discipline_id",
      "disciplines[].weight",
      "category_weights[].category",
      "category_weights[].weight",
    ],
  ],
  [
    "title" => t("api.docs.results.title", "Results"),
    "description" => t("api.docs.results.body", "Liefert Rohdaten, relatives Ranking oder absolutes Ranking für ein Combine."),
    "requests" => [
      "GET /api/v1/results_raw.php?combine_id=123",
      "GET /api/v1/results_relative.php?combine_id=123",
      "GET /api/v1/results_absolut.php?combine_id=123",
    ],
    "parameters" => [
      ["name" => "combine_id", "required" => true, "description" => t("api.docs.param.combine_id", "Pflicht. ID des Combines im authentifizierten Team.")],
    ],
    "fields" => [
      "combine",
      "players",
      "disciplines",
      "category_weights",
      "results[].discipline_id",
      "results[].player_id",
      "results[].result_value",
      "results[].updated_at",
      "ranking.overall",
      "ranking.disciplines",
    ],
  ],
  [
    "title" => t("api.docs.radar.title", "Radar"),
    "description" => t("api.docs.radar.body", "Liefert Radargraph-Werte pro Spieler oder als H2H-Vergleich. Jeder Radar-Eintrag enthält den Teamwert."),
    "requests" => [
      "GET /api/v1/radar.php?combine_id=123",
      "GET /api/v1/radar.php?combine_id=123&player_id=456&overall=avg",
      "GET /api/v1/radar.php?combine_id=123&player_id=456&compare_player_id=789",
    ],
    "parameters" => [
      ["name" => "combine_id", "required" => true, "description" => t("api.docs.param.combine_id", "Pflicht. ID des Combines im authentifizierten Team.")],
      ["name" => "player_id", "required" => false, "description" => t("api.docs.param.player_id", "Optional. Filtert auf einen Spieler. Pflicht, wenn compare_player_id gesetzt ist.")],
      ["name" => "compare_player_id", "required" => false, "description" => t("api.docs.param.compare_player_id", "Optional. Liefert einen H2H-Radarvergleich gegen player_id.")],
      ["name" => "overall", "required" => false, "description" => t("api.docs.param.overall", "Optional. sum, avg oder abs. Standard ist sum.")],
    ],
    "fields" => [
      "combine",
      "mode",
      "items[].player_id",
      "items[].player_name",
      "items[].radar[].label",
      "items[].radar[].player",
      "items[].radar[].team",
      "player_id",
      "compare_player_id",
      "radar[].label",
      "radar[].player",
      "radar[].playerB",
      "radar[].team",
    ],
  ],
];

$pageTitle = t("api.docs.page_title", "Ultimate Combine - API Dokumentation");
$pageLang = $lang;
require __DIR__ . "/partials/head.php";
$brandText = t("site.title", "Ultimate Combine");
$brandSuffix = t("api.docs.brand", "API Dokumentation");
$showBack = true;
$backOnclick = "history.back()";
require __DIR__ . "/partials/header-brand.php";
?>

  <main class="team api-docs">
    <section class="auth-card api-docs-hero">
      <h1><?php echo htmlspecialchars(t("api.docs.heading", "API Dokumentation"), ENT_QUOTES, "UTF-8"); ?></h1>
      <p class="lead"><?php echo htmlspecialchars(t("api.docs.lead", "Die Ultimate Combine API ist read-only und erlaubt externen Clients sicheren Zugriff auf Teamdaten, Combines, Rankings und Radargraph-Werte."), ENT_QUOTES, "UTF-8"); ?></p>
      <div class="api-docs-base">
        <span><?php echo htmlspecialchars(t("api.docs.base_url", "Basis-URL"), ENT_QUOTES, "UTF-8"); ?></span>
        <code><?php echo htmlspecialchars($apiBaseUrl, ENT_QUOTES, "UTF-8"); ?></code>
      </div>
    </section>

    <section class="auth-card api-docs-section">
      <h2><?php echo htmlspecialchars(t("api.docs.auth.title", "Authentifizierung"), ENT_QUOTES, "UTF-8"); ?></h2>
      <p><?php echo htmlspecialchars(t("api.docs.auth.body", "Daten-Endpunkte benötigen einen read-only API Token. Erstelle den Token als eingeloggter Nutzer im Team-Bearbeiten-Modus und sende ihn als Bearer Token."), ENT_QUOTES, "UTF-8"); ?></p>
      <pre><code>Authorization: Bearer <?php echo htmlspecialchars($tokenPlaceholder, ENT_QUOTES, "UTF-8"); ?></code></pre>
      <p class="help"><?php echo htmlspecialchars(t("api.docs.auth.fallback", "Falls dein Hosting den Authorization Header nicht an PHP weiterreicht, funktioniert alternativ X-API-Token."), ENT_QUOTES, "UTF-8"); ?></p>
      <pre><code>X-API-Token: <?php echo htmlspecialchars($tokenPlaceholder, ENT_QUOTES, "UTF-8"); ?></code></pre>
    </section>

    <section class="auth-card api-docs-section">
      <h2><?php echo htmlspecialchars(t("api.docs.quickstart.title", "Quickstart"), ENT_QUOTES, "UTF-8"); ?></h2>
      <pre><code>curl -H "Authorization: Bearer <?php echo htmlspecialchars($tokenPlaceholder, ENT_QUOTES, "UTF-8"); ?>" \
  <?php echo htmlspecialchars($apiBaseUrl, ENT_QUOTES, "UTF-8"); ?>/players.php</code></pre>
      <pre><code>curl -H "Authorization: Bearer <?php echo htmlspecialchars($tokenPlaceholder, ENT_QUOTES, "UTF-8"); ?>" \
  "<?php echo htmlspecialchars($apiBaseUrl, ENT_QUOTES, "UTF-8"); ?>/radar.php?combine_id=123&player_id=456&compare_player_id=789"</code></pre>
    </section>

    <section class="auth-card api-docs-section">
      <h2><?php echo htmlspecialchars(t("api.docs.response.title", "Response-Format"), ENT_QUOTES, "UTF-8"); ?></h2>
      <div class="api-docs-grid">
        <div>
          <h3><?php echo htmlspecialchars(t("api.docs.response.success", "Erfolgreich"), ENT_QUOTES, "UTF-8"); ?></h3>
          <pre><code>{
  "data": [],
  "meta": {
    "team_id": 1,
    "count": 0
  }
}</code></pre>
        </div>
        <div>
          <h3><?php echo htmlspecialchars(t("api.docs.response.error", "Fehler"), ENT_QUOTES, "UTF-8"); ?></h3>
          <pre><code>{
  "error": {
    "code": "unauthorized",
    "message": "Invalid bearer token."
  }
}</code></pre>
        </div>
      </div>
    </section>

    <section class="auth-card api-docs-section">
      <h2><?php echo htmlspecialchars(t("api.docs.endpoints.title", "Endpunkte"), ENT_QUOTES, "UTF-8"); ?></h2>
      <div class="api-endpoint-list">
        <?php foreach ($endpointGroups as $group): ?>
          <article class="api-endpoint">
            <header class="api-endpoint-header">
              <h3><?php echo htmlspecialchars($group["title"], ENT_QUOTES, "UTF-8"); ?></h3>
              <p><?php echo htmlspecialchars($group["description"], ENT_QUOTES, "UTF-8"); ?></p>
            </header>
            <div class="api-subsection">
              <h4><?php echo htmlspecialchars(t("api.docs.requests", "Requests"), ENT_QUOTES, "UTF-8"); ?></h4>
              <pre><code><?php echo htmlspecialchars(implode("\n", $group["requests"]), ENT_QUOTES, "UTF-8"); ?></code></pre>
            </div>
            <div class="api-reference-grid">
              <div class="api-subsection">
                <h4><?php echo htmlspecialchars(t("api.docs.parameters", "Parameter"), ENT_QUOTES, "UTF-8"); ?></h4>
                <?php if (empty($group["parameters"])): ?>
                  <p class="help"><?php echo htmlspecialchars(t("api.docs.no_parameters", "Keine Query-Parameter."), ENT_QUOTES, "UTF-8"); ?></p>
                <?php else: ?>
                  <dl class="api-field-list">
                    <?php foreach ($group["parameters"] as $parameter): ?>
                      <div>
                        <dt>
                          <code><?php echo htmlspecialchars($parameter["name"], ENT_QUOTES, "UTF-8"); ?></code>
                          <span><?php echo htmlspecialchars($parameter["required"] ? t("api.docs.required", "Pflicht") : t("api.docs.optional", "Optional"), ENT_QUOTES, "UTF-8"); ?></span>
                        </dt>
                        <dd><?php echo htmlspecialchars($parameter["description"], ENT_QUOTES, "UTF-8"); ?></dd>
                      </div>
                    <?php endforeach; ?>
                  </dl>
                <?php endif; ?>
              </div>
              <div class="api-subsection">
                <h4><?php echo htmlspecialchars(t("api.docs.response_fields", "Antwortfelder"), ENT_QUOTES, "UTF-8"); ?></h4>
                <ul class="api-field-list is-compact">
                  <?php foreach ($group["fields"] as $field): ?>
                    <li><code><?php echo htmlspecialchars($field, ENT_QUOTES, "UTF-8"); ?></code></li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="auth-card api-docs-section">
      <h2><?php echo htmlspecialchars(t("api.docs.status.title", "Statuscodes"), ENT_QUOTES, "UTF-8"); ?></h2>
      <div class="api-status-grid">
        <span><strong>200</strong> OK</span>
        <span><strong>400</strong> <?php echo htmlspecialchars(t("api.docs.status.400", "Ungültige Parameter"), ENT_QUOTES, "UTF-8"); ?></span>
        <span><strong>401</strong> <?php echo htmlspecialchars(t("api.docs.status.401", "Fehlender oder ungültiger Token"), ENT_QUOTES, "UTF-8"); ?></span>
        <span><strong>403</strong> <?php echo htmlspecialchars(t("api.docs.status.403", "Token ohne read Scope"), ENT_QUOTES, "UTF-8"); ?></span>
        <span><strong>404</strong> <?php echo htmlspecialchars(t("api.docs.status.404", "Datensatz nicht gefunden"), ENT_QUOTES, "UTF-8"); ?></span>
        <span><strong>405</strong> <?php echo htmlspecialchars(t("api.docs.status.405", "Methode nicht erlaubt"), ENT_QUOTES, "UTF-8"); ?></span>
        <span><strong>503</strong> <?php echo htmlspecialchars(t("api.docs.status.503", "Datenbank nicht erreichbar"), ENT_QUOTES, "UTF-8"); ?></span>
      </div>
    </section>
  </main>

  <?php require __DIR__ . "/partials/footer.php"; ?>
  <?php require __DIR__ . "/partials/foot.php"; ?>
