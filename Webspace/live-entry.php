<?php
require_once __DIR__ . "/bootstrap.php";
require_once __DIR__ . "/lib/combine-results-service.php";
require_once __DIR__ . "/lib/result-entry-service.php";

function uc_live_entry_url(string $token, array $playerIds = [], ?int $disciplineId = null, array $extra = []): string {
  $query = ["token" => $token];
  if (!empty($playerIds)) {
    $query["player_ids"] = array_values($playerIds);
  }
  if ($disciplineId) {
    $query["discipline_id"] = $disciplineId;
  }
  foreach ($extra as $key => $value) {
    $query[$key] = $value;
  }
  return "live-entry.php?" . http_build_query($query, "", "&", PHP_QUERY_RFC3986);
}

function uc_live_entry_display_value($value, string $empty = ""): string {
  if ($value === null || $value === "") {
    return $empty;
  }
  return str_replace(".", ",", (string)$value);
}

$token = trim((string)($_POST["token"] ?? $_GET["token"] ?? ""));
$pageError = null;
$entryLink = null;
$players = [];
$disciplines = [];
$selectedPlayers = [];
$selectedPlayerIds = [];
$activeDiscipline = null;
$activeDisciplineId = null;
$resultValues = [];
$resultOriginalValues = [];
$conflicts = [];
$needsConfirmation = false;
$activeDisciplineIndex = null;
$completedDisciplineIds = [];
$saveNotice = isset($_GET["saved"])
  ? t("live.feedback.saved", "Ergebnisse gespeichert.")
  : null;

if (!$pdo) {
  $pageError = $dbError ?? t("error.db_unreachable", "Datenbank ist nicht erreichbar.");
} elseif (!preg_match('/\A[a-f0-9]{64}\z/i', $token)) {
  $pageError = t("live.error.invalid_link", "Dieser Live-Link ist ungültig oder nicht mehr aktiv.");
} else {
  try {
    $stmt = $pdo->prepare(
      "SELECT combine_entry_links.id AS link_id,
              combine_entry_links.label,
              combine_entry_links.combine_id,
              combines.team_id,
              combines.combine_name,
              combines.event_date,
              combines.combine_location,
              teams.team_name
       FROM combine_entry_links
       INNER JOIN combines ON combines.id = combine_entry_links.combine_id
       INNER JOIN teams ON teams.id = combines.team_id
       WHERE combine_entry_links.token_hash = :token_hash
         AND combine_entry_links.revoked_at IS NULL
         AND (combine_entry_links.expires_at IS NULL OR combine_entry_links.expires_at > CURRENT_TIMESTAMP)
       LIMIT 1"
    );
    $stmt->execute([":token_hash" => hash("sha256", $token)]);
    $entryLink = $stmt->fetch() ?: null;
  } catch (Throwable $e) {
    $pageError = t("live.error.load_failed", "Die Live-Erfassung konnte nicht geladen werden.");
  }

  if (!$pageError && !$entryLink) {
    $pageError = t("live.error.invalid_link", "Dieser Live-Link ist ungültig oder nicht mehr aktiv.");
  }
}

if ($entryLink && !$pageError) {
  $combineId = (int)$entryLink["combine_id"];
  $teamId = (int)$entryLink["team_id"];

  try {
    $players = uc_results_players($pdo, $teamId, $combineId);
    $disciplines = uc_results_disciplines($pdo, $teamId, $combineId);

    $stmt = $pdo->prepare(
      "UPDATE combine_entry_links
       SET last_used_at = CURRENT_TIMESTAMP
       WHERE id = :id AND revoked_at IS NULL"
    );
    $stmt->execute([":id" => (int)$entryLink["link_id"]]);
  } catch (Throwable $e) {
    $pageError = t("live.error.load_failed", "Die Live-Erfassung konnte nicht geladen werden.");
  }
}

if ($entryLink && !$pageError) {
  $requestedPlayerIds = (array)($_POST["player_ids"] ?? $_GET["player_ids"] ?? []);
  $requestedPlayerMap = [];
  foreach ($requestedPlayerIds as $requestedPlayerId) {
    $playerId = filter_var($requestedPlayerId, FILTER_VALIDATE_INT);
    if ($playerId) {
      $requestedPlayerMap[(int)$playerId] = true;
    }
  }

  foreach ($players as $player) {
    $playerId = (int)$player["id"];
    if (isset($requestedPlayerMap[$playerId])) {
      $selectedPlayers[] = $player;
      $selectedPlayerIds[] = $playerId;
    }
  }

  $activeDisciplineId = filter_var(
    $_POST["discipline_id"] ?? $_GET["discipline_id"] ?? null,
    FILTER_VALIDATE_INT
  );
  if (!$activeDisciplineId && !empty($selectedPlayerIds) && !empty($disciplines)) {
    $activeDisciplineId = (int)$disciplines[0]["id"];
  }
  foreach ($disciplines as $discipline) {
    if ((int)$discipline["id"] === (int)$activeDisciplineId) {
      $activeDiscipline = $discipline;
      break;
    }
  }
  if ($activeDiscipline) {
    foreach ($disciplines as $index => $discipline) {
      if ((int)$discipline["id"] === (int)$activeDisciplineId) {
        $activeDisciplineIndex = $index;
        break;
      }
    }
  }
  if ($activeDisciplineId && !$activeDiscipline) {
    $pageError = t("live.error.invalid_discipline", "Diese Disziplin gehört nicht zum Combine.");
  }
}

$action = $_POST["action"] ?? "";
if (
  !$pageError
  && $entryLink
  && $_SERVER["REQUEST_METHOD"] === "POST"
  && ($action === "save_results" || $action === "confirm_save_results")
) {
  if (empty($selectedPlayerIds)) {
    $pageError = t("live.error.players_required", "Bitte wähle mindestens einen Spieler aus.");
  } elseif (!$activeDiscipline) {
    $pageError = t("live.error.invalid_discipline", "Diese Disziplin gehört nicht zum Combine.");
  } else {
    $submitted = (array)($_POST["result"] ?? []);
    $original = (array)($_POST["original"] ?? []);
    $resultValues = uc_result_entry_prepare_values($selectedPlayerIds, $submitted);
    $resultOriginalValues = uc_result_entry_prepare_values($selectedPlayerIds, $original);

    try {
      $currentValues = uc_result_entry_load_current_values(
        $pdo,
        (int)$entryLink["combine_id"],
        (int)$activeDisciplineId,
        $selectedPlayerIds
      );

      if ($action === "save_results") {
        $conflicts = uc_result_entry_find_conflicts(
          $selectedPlayerIds,
          $resultValues,
          $resultOriginalValues,
          $currentValues
        );
      }

      if (!empty($conflicts)) {
        $needsConfirmation = true;
      } else {
        uc_result_entry_store_values(
          $pdo,
          (int)$entryLink["combine_id"],
          (int)$activeDisciplineId,
          $selectedPlayerIds,
          $resultValues
        );
        header("Location: " . uc_live_entry_url(
          $token,
          $selectedPlayerIds,
          (int)$activeDisciplineId,
          ["saved" => "1"]
        ));
        exit;
      }
    } catch (Throwable $e) {
      $pageError = t("live.error.save_failed", "Ergebnisse konnten nicht gespeichert werden.");
    }
  }
}

if (!$pageError && $entryLink && !empty($selectedPlayerIds) && $activeDiscipline && !$needsConfirmation) {
  try {
    $currentValues = uc_result_entry_load_current_values(
      $pdo,
      (int)$entryLink["combine_id"],
      (int)$activeDisciplineId,
      $selectedPlayerIds
    );
    $resultValues = uc_result_entry_prepare_values($selectedPlayerIds, $currentValues);
    $resultOriginalValues = $resultValues;
  } catch (Throwable $e) {
    $pageError = t("live.error.results_load_failed", "Ergebnisse konnten nicht geladen werden.");
  }
}

if (!$pageError && $entryLink && !empty($selectedPlayerIds) && !empty($disciplines)) {
  try {
    $completedDisciplineIds = uc_result_entry_completed_disciplines(
      $pdo,
      (int)$entryLink["combine_id"],
      array_column($disciplines, "id"),
      $selectedPlayerIds
    );
  } catch (Throwable $e) {
    $completedDisciplineIds = [];
  }
}

$pageTitle = t("live.page_title", "Live-Erfassung") . " - Ultimate Combine";
$pageLang = $lang;
$pageReferrerPolicy = "no-referrer";
$pageRobots = "noindex, nofollow";
$brandText = t("live.brand", "Live-Erfassung");
$showSpacer = false;
require __DIR__ . "/partials/head.php";
require __DIR__ . "/partials/header-simple.php";
?>

  <main class="auth is-wide live-entry-page">
    <?php if ($pageError): ?>
      <section class="auth-card">
        <h1><?php echo htmlspecialchars(t("live.error.title", "Live-Erfassung nicht verfügbar"), ENT_QUOTES, "UTF-8"); ?></h1>
        <p class="help"><?php echo htmlspecialchars($pageError, ENT_QUOTES, "UTF-8"); ?></p>
      </section>
    <?php elseif ($entryLink): ?>
      <section class="auth-card live-entry-overview">
        <div>
          <h1><?php echo htmlspecialchars($entryLink["combine_name"], ENT_QUOTES, "UTF-8"); ?></h1>
          <p class="lead live-combine-meta">
            <?php echo htmlspecialchars($entryLink["team_name"], ENT_QUOTES, "UTF-8"); ?>
            <?php if (!empty($entryLink["label"])): ?>
              &middot; <?php echo htmlspecialchars($entryLink["label"], ENT_QUOTES, "UTF-8"); ?>
            <?php endif; ?>
          </p>
        </div>

        <?php if (empty($selectedPlayerIds)): ?>
          <div>
            <h2><?php echo htmlspecialchars(t("live.players.title", "Spieler auswählen"), ENT_QUOTES, "UTF-8"); ?></h2>
            <p class="help"><?php echo htmlspecialchars(t("live.players.lead", "Wähle die Spieler aus, deren Ergebnisse ihr auf diesem Gerät eintragt."), ENT_QUOTES, "UTF-8"); ?></p>
          </div>
          <?php if (empty($players)): ?>
            <p class="help"><?php echo htmlspecialchars(t("live.players.empty", "Diesem Combine sind keine Spieler zugeordnet."), ENT_QUOTES, "UTF-8"); ?></p>
          <?php elseif (empty($disciplines)): ?>
            <p class="help"><?php echo htmlspecialchars(t("live.disciplines.empty", "Diesem Combine sind keine Disziplinen zugeordnet."), ENT_QUOTES, "UTF-8"); ?></p>
          <?php else: ?>
            <form class="form" method="get" action="live-entry.php" data-live-player-form>
              <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, "UTF-8"); ?>">
              <label class="field live-player-search">
                <span><?php echo htmlspecialchars(t("live.players.search", "Spieler suchen"), ENT_QUOTES, "UTF-8"); ?></span>
                <input type="search" autocomplete="off" placeholder="<?php echo htmlspecialchars(t("live.players.search_placeholder", "Name oder Trikotnummer"), ENT_QUOTES, "UTF-8"); ?>" data-live-player-search>
              </label>
              <div class="live-selection-summary">
                <strong
                  data-live-selection-status
                  data-status-template="<?php echo htmlspecialchars(t("live.players.selected", "%d Spieler ausgewählt"), ENT_QUOTES, "UTF-8"); ?>"
                ><?php echo htmlspecialchars(sprintf(t("live.players.selected", "%d Spieler ausgewählt"), 0), ENT_QUOTES, "UTF-8"); ?></strong>
                <button class="text-button" type="button" data-live-clear-selection><?php echo htmlspecialchars(t("live.players.clear", "Auswahl aufheben"), ENT_QUOTES, "UTF-8"); ?></button>
              </div>
              <div class="selection-list live-player-list">
                <?php foreach ($players as $player): ?>
                  <label class="selection-item live-player-option" data-live-player-option>
                    <input type="checkbox" name="player_ids[]" value="<?php echo (int)$player["id"]; ?>" data-live-player-checkbox>
                    <span>
                      <?php echo htmlspecialchars(trim($player["first_name"] . " " . $player["last_name"]), ENT_QUOTES, "UTF-8"); ?>
                      <?php if (!empty($player["jersey_number"])): ?>
                        <span class="meta">#<?php echo htmlspecialchars($player["jersey_number"], ENT_QUOTES, "UTF-8"); ?></span>
                      <?php endif; ?>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
              <p class="help is-hidden" data-live-player-empty><?php echo htmlspecialchars(t("live.players.search_empty", "Keine passenden Spieler gefunden."), ENT_QUOTES, "UTF-8"); ?></p>
              <button class="primary-button" type="submit" data-live-player-submit><?php echo htmlspecialchars(t("live.players.continue", "Weiter zur Erfassung"), ENT_QUOTES, "UTF-8"); ?></button>
            </form>
          <?php endif; ?>
        <?php else: ?>
          <div class="card-header live-discipline-header">
            <div>
              <h2><?php echo htmlspecialchars(t("live.disciplines.title", "Disziplin auswählen"), ENT_QUOTES, "UTF-8"); ?></h2>
              <p class="help">
                <?php echo htmlspecialchars(sprintf(t("live.players.selected", "%d Spieler ausgewählt"), count($selectedPlayerIds)), ENT_QUOTES, "UTF-8"); ?>
                <?php if ($activeDisciplineIndex !== null): ?>
                  &middot; <?php echo htmlspecialchars(sprintf(t("live.disciplines.progress", "%d von %d"), $activeDisciplineIndex + 1, count($disciplines)), ENT_QUOTES, "UTF-8"); ?>
                <?php endif; ?>
              </p>
            </div>
            <a class="pill-button is-muted" href="<?php echo htmlspecialchars(uc_live_entry_url($token), ENT_QUOTES, "UTF-8"); ?>" data-live-leave><?php echo htmlspecialchars(t("live.players.change", "Spielerauswahl ändern"), ENT_QUOTES, "UTF-8"); ?></a>
          </div>
          <nav class="live-discipline-nav" aria-label="<?php echo htmlspecialchars(t("live.disciplines.title", "Disziplin auswählen"), ENT_QUOTES, "UTF-8"); ?>" data-live-discipline-nav>
            <?php foreach ($disciplines as $discipline): ?>
              <?php
                $disciplineId = (int)$discipline["id"];
                $isActiveDiscipline = $disciplineId === (int)$activeDisciplineId;
                $isCompletedDiscipline = isset($completedDisciplineIds[$disciplineId]);
                $disciplineClass = "pill-button live-discipline-tab";
                if ($isCompletedDiscipline) {
                  $disciplineClass .= " is-complete";
                } elseif (!$isActiveDiscipline) {
                  $disciplineClass .= " is-muted";
                }
                if ($isActiveDiscipline) {
                  $disciplineClass .= " is-active";
                }
              ?>
              <a
                class="<?php echo htmlspecialchars($disciplineClass, ENT_QUOTES, "UTF-8"); ?>"
                href="<?php echo htmlspecialchars(uc_live_entry_url($token, $selectedPlayerIds, $disciplineId), ENT_QUOTES, "UTF-8"); ?>"
                <?php echo $isActiveDiscipline ? ' aria-current="step"' : ""; ?>
                <?php if ($isCompletedDiscipline): ?>
                  title="<?php echo htmlspecialchars(t("live.disciplines.complete", "Abgeschlossen"), ENT_QUOTES, "UTF-8"); ?>"
                <?php endif; ?>
                data-live-leave
              >
                <?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?>
                <?php if ($isCompletedDiscipline): ?>
                  <span class="live-discipline-check" aria-label="<?php echo htmlspecialchars(t("live.disciplines.complete", "Abgeschlossen"), ENT_QUOTES, "UTF-8"); ?>">&#10003;</span>
                <?php endif; ?>
              </a>
            <?php endforeach; ?>
          </nav>
        <?php endif; ?>
      </section>

      <?php if ($needsConfirmation): ?>
        <section class="auth-card">
          <h2><?php echo htmlspecialchars(t("combine.confirm.title", "Bestätigung nötig"), ENT_QUOTES, "UTF-8"); ?></h2>
          <p class="help"><?php echo htmlspecialchars(t("combine.confirm.notice", "Es gab zwischenzeitliche Änderungen. Bitte bestätige das Überschreiben."), ENT_QUOTES, "UTF-8"); ?></p>
          <div class="conflict-list">
            <?php foreach ($conflicts as $playerId => $conflict): ?>
              <?php
                $playerName = t("combine.player_placeholder", "Spieler #") . (int)$playerId;
                foreach ($selectedPlayers as $player) {
                  if ((int)$player["id"] === (int)$playerId) {
                    $playerName = trim($player["first_name"] . " " . $player["last_name"]);
                    break;
                  }
                }
              ?>
              <div class="conflict-row">
                <span><?php echo htmlspecialchars($playerName, ENT_QUOTES, "UTF-8"); ?></span>
                <span><?php echo htmlspecialchars(t("combine.confirm.current", "Aktuell"), ENT_QUOTES, "UTF-8"); ?>: <?php echo htmlspecialchars(uc_live_entry_display_value($conflict["current"], "-"), ENT_QUOTES, "UTF-8"); ?></span>
                <span><?php echo htmlspecialchars(t("combine.confirm.new", "Neu"), ENT_QUOTES, "UTF-8"); ?>: <?php echo htmlspecialchars(uc_live_entry_display_value($conflict["new"], "-"), ENT_QUOTES, "UTF-8"); ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <form class="form" method="post" action="live-entry.php">
            <input type="hidden" name="action" value="confirm_save_results">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, "UTF-8"); ?>">
            <input type="hidden" name="discipline_id" value="<?php echo (int)$activeDisciplineId; ?>">
            <?php foreach ($selectedPlayerIds as $playerId): ?>
              <input type="hidden" name="player_ids[]" value="<?php echo (int)$playerId; ?>">
              <input type="hidden" name="result[<?php echo (int)$playerId; ?>]" value="<?php echo htmlspecialchars($resultValues[$playerId] ?? "", ENT_QUOTES, "UTF-8"); ?>">
            <?php endforeach; ?>
            <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("combine.confirm.save", "Bestätigen und speichern"), ENT_QUOTES, "UTF-8"); ?></button>
          </form>
        </section>
      <?php endif; ?>

      <?php if (!empty($selectedPlayerIds) && $activeDiscipline): ?>
        <section class="auth-card live-results-card">
          <div class="live-results-header">
            <div>
              <h2><?php echo htmlspecialchars($activeDiscipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?></h2>
              <?php if (!empty($activeDiscipline["description"])): ?>
                <p class="help"><?php echo htmlspecialchars($activeDiscipline["description"], ENT_QUOTES, "UTF-8"); ?></p>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($saveNotice): ?>
            <p class="live-save-notice" role="status"><?php echo htmlspecialchars($saveNotice, ENT_QUOTES, "UTF-8"); ?></p>
          <?php endif; ?>
          <form
            class="form live-results-form"
            method="post"
            action="live-entry.php"
            data-live-results-form
            data-unsaved-message="<?php echo htmlspecialchars(t("combine.confirm.unsaved_change", "Ungesicherte Änderungen gehen verloren. Trotzdem wechseln?"), ENT_QUOTES, "UTF-8"); ?>"
          >
            <input type="hidden" name="action" value="save_results">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, "UTF-8"); ?>">
            <input type="hidden" name="discipline_id" value="<?php echo (int)$activeDisciplineId; ?>">
            <div class="result-grid">
              <?php foreach ($selectedPlayers as $playerIndex => $player): ?>
                <?php $playerId = (int)$player["id"]; ?>
                <label class="result-item live-result-item">
                  <span class="live-result-player"><?php echo htmlspecialchars(trim($player["first_name"] . " " . $player["last_name"]), ENT_QUOTES, "UTF-8"); ?></span>
                  <span class="result-value">
                    <input
                      class="result-input"
                      type="text"
                      inputmode="decimal"
                      enterkeyhint="<?php echo $playerIndex === count($selectedPlayers) - 1 ? "done" : "next"; ?>"
                      name="result[<?php echo $playerId; ?>]"
                      value="<?php echo htmlspecialchars(uc_live_entry_display_value($resultValues[$playerId] ?? null), ENT_QUOTES, "UTF-8"); ?>"
                      data-live-result-input
                    >
                    <?php if (!empty($activeDiscipline["unit_abbreviation"])): ?>
                      <span class="unit-tag"><?php echo htmlspecialchars($activeDiscipline["unit_abbreviation"], ENT_QUOTES, "UTF-8"); ?></span>
                    <?php endif; ?>
                  </span>
                  <input type="hidden" name="player_ids[]" value="<?php echo $playerId; ?>">
                  <input type="hidden" name="original[<?php echo $playerId; ?>]" value="<?php echo htmlspecialchars($resultOriginalValues[$playerId] ?? "", ENT_QUOTES, "UTF-8"); ?>">
                </label>
              <?php endforeach; ?>
            </div>
            <div class="live-save-bar">
              <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("common.save", "Speichern"), ENT_QUOTES, "UTF-8"); ?></button>
            </div>
          </form>
        </section>
      <?php endif; ?>
    <?php endif; ?>
  </main>

  <script src="theme.js"></script>
  <script src="js/live-entry.js"></script>
</body>
</html>
