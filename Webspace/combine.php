<?php
require_once __DIR__ . "/bootstrap.php";

function uc_normalize_value($value) {
  $value = trim((string)$value);
  if ($value === "") {
    return null;
  }
  return str_replace(",", ".", $value);
}

function uc_value_to_float($value) {
  $value = uc_normalize_value($value);
  if ($value === null || !is_numeric($value)) {
    return null;
  }
  return (float)$value;
}

function uc_display_value($value, $empty = "") {
  if ($value === null || $value === "") {
    return $empty;
  }
  return str_replace(".", ",", (string)$value);
}

function uc_format_points($points) {
  if ($points === null) {
    return "0";
  }
  $rounded = round($points, 2);
  if (abs($rounded - round($rounded)) < 0.005) {
    return (string)(int)round($rounded);
  }
  return number_format($rounded, 2, ",", "");
}

if (!$pdo) {
  $pageError = $dbError ?? "Datenbank ist nicht erreichbar.";
} else {
  $pageError = null;
}

$teamId = $_SESSION["team_id"] ?? null;
$teamName = $_SESSION["team_name"] ?? "";

if (!$teamId) {
  header("Location: index.php");
  exit;
}

$combineId = filter_var($_GET["id"] ?? null, FILTER_VALIDATE_INT);
if (!$combineId) {
  header("Location: team.php");
  exit;
}

$editMode = isset($_GET["edit"]);
$mode = $_GET["mode"] ?? "view";
if (!in_array($mode, ["view", "start", "results"], true)) {
  $mode = "view";
}
if ($editMode) {
  $mode = "view";
}

$activeDisciplineId = filter_var($_GET["discipline_id"] ?? null, FILTER_VALIDATE_INT);

$combine = null;
$combineFeedback = null;
$combineError = null;
$players = [];
$disciplines = [];
$assignedPlayerIds = [];
$assignedDisciplineIds = [];
$assignedDisciplinesByCategory = [];
$assignedPlayers = [];
$assignedDisciplines = [];
$resultsByDiscipline = [];
$resultValues = [];
$conflicts = [];
$needsConfirmation = false;
$saveNotice = null;
$startError = null;
$filterGender = "";
$filterPosition = "";
$genderOptions = [
  "m" => "Männlich",
  "w" => "Weiblich",
  "d" => "Divers",
];

$filterGender = $_GET["gender"] ?? "";
if (!isset($genderOptions[$filterGender])) {
  $filterGender = "";
}
$filterPosition = $_GET["position"] ?? "";
if (!in_array($filterPosition, ["handler", "cutter"], true)) {
  $filterPosition = "";
}

$formCombineName = "";
$formEventDate = "";
$formPlayerIds = [];
$formDisciplineIds = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$pageError) {
  $action = $_POST["action"] ?? "";

  if ($action === "logout") {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
  }
}

if (!$pageError) {
  $stmt = $pdo->prepare(
    "SELECT id, combine_name, event_date
     FROM combines
     WHERE id = :id AND team_id = :team_id"
  );
  $stmt->execute([
    ":id" => $combineId,
    ":team_id" => $teamId,
  ]);
  $combine = $stmt->fetch();

  if (!$combine) {
    $combineError = "Combine wurde nicht gefunden.";
  }

  $stmt = $pdo->prepare(
    "SELECT id, first_name, last_name, jersey_number, gender, position_handler, position_cutter
     FROM players
     WHERE team_id = :team_id
     ORDER BY created_at DESC"
  );
  $stmt->execute([":team_id" => $teamId]);
  $players = $stmt->fetchAll();

  $stmt = $pdo->prepare(
    "SELECT id, discipline_name, description, category, unit, rating_direction
     FROM disciplines
     WHERE team_id = :team_id
     ORDER BY created_at DESC"
  );
  $stmt->execute([":team_id" => $teamId]);
  $disciplines = $stmt->fetchAll();

  if (!$combineError) {
    try {
      $stmt = $pdo->prepare(
        "SELECT player_id
         FROM combine_players
         WHERE combine_id = :combine_id"
      );
      $stmt->execute([":combine_id" => $combineId]);
      $assignedPlayerIds = array_map("intval", array_column($stmt->fetchAll(), "player_id"));

      $stmt = $pdo->prepare(
        "SELECT discipline_id
         FROM combine_disciplines
         WHERE combine_id = :combine_id"
      );
      $stmt->execute([":combine_id" => $combineId]);
      $assignedDisciplineIds = array_map("intval", array_column($stmt->fetchAll(), "discipline_id"));
    } catch (Throwable $e) {
      $combineError = "Zuordnungen konnten nicht geladen werden.";
    }
  }

  foreach ($players as $player) {
    if (in_array((int)$player["id"], $assignedPlayerIds, true)) {
      $assignedPlayers[] = $player;
    }
  }

  foreach ($disciplines as $discipline) {
    if (!in_array((int)$discipline["id"], $assignedDisciplineIds, true)) {
      continue;
    }
    $assignedDisciplines[] = $discipline;
    $category = trim((string)$discipline["category"]);
    if ($category === "") {
      $category = "Ohne Kategorie";
    }
    $assignedDisciplinesByCategory[$category][] = $discipline;
  }
  ksort($assignedDisciplinesByCategory, SORT_NATURAL | SORT_FLAG_CASE);
  $orderedPlayers = $assignedPlayers;
  $activeDisciplineDescription = "";
  $activeDisciplineUnit = "";
  foreach ($assignedDisciplines as $discipline) {
    if ((int)$discipline["id"] === (int)$activeDisciplineId) {
      $activeDisciplineDescription = $discipline["description"] ?? "";
      $activeDisciplineUnit = $discipline["unit"] ?? "";
      break;
    }
  }

  $formCombineName = $combine["combine_name"] ?? "";
  $formEventDate = $combine["event_date"] ?? "";
  $formPlayerIds = $assignedPlayerIds;
  $formDisciplineIds = $assignedDisciplineIds;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$pageError) {
  $action = $_POST["action"] ?? "";

  if ($action === "update_combine" && !$combineError) {
    $combineName = trim($_POST["combine_name"] ?? "");
    $eventDate = trim($_POST["event_date"] ?? "");
    $selectedPlayers = (array)($_POST["players"] ?? []);
    $selectedDisciplines = (array)($_POST["disciplines"] ?? []);

    $selectedPlayers = array_values(array_unique(array_filter(array_map(function ($value) {
      $id = filter_var($value, FILTER_VALIDATE_INT);
      return $id ? (int)$id : null;
    }, $selectedPlayers))));

    $selectedDisciplines = array_values(array_unique(array_filter(array_map(function ($value) {
      $id = filter_var($value, FILTER_VALIDATE_INT);
      return $id ? (int)$id : null;
    }, $selectedDisciplines))));

    $playerMap = [];
    foreach ($players as $player) {
      $playerMap[(int)$player["id"]] = true;
    }

    $disciplineMap = [];
    foreach ($disciplines as $discipline) {
      $disciplineMap[(int)$discipline["id"]] = true;
    }

    $invalidPlayers = array_diff($selectedPlayers, array_keys($playerMap));
    $invalidDisciplines = array_diff($selectedDisciplines, array_keys($disciplineMap));

    if ($combineName === "" || $eventDate === "") {
      $combineFeedback = "Bitte Name und Datum fuer das Combine angeben.";
    } elseif (!empty($invalidPlayers) || !empty($invalidDisciplines)) {
      $combineFeedback = "Ungueltige Spieler- oder Disziplin-Auswahl.";
    } else {
      try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
          "UPDATE combines
           SET combine_name = :combine_name,
               event_date = :event_date
           WHERE id = :id AND team_id = :team_id"
        );
        $stmt->execute([
          ":combine_name" => $combineName,
          ":event_date" => $eventDate,
          ":id" => $combineId,
          ":team_id" => $teamId,
        ]);

        $stmt = $pdo->prepare("DELETE FROM combine_players WHERE combine_id = :combine_id");
        $stmt->execute([":combine_id" => $combineId]);

        if (!empty($selectedPlayers)) {
          $stmt = $pdo->prepare(
            "INSERT INTO combine_players (combine_id, player_id)
             VALUES (:combine_id, :player_id)"
          );
          foreach ($selectedPlayers as $playerId) {
            $stmt->execute([
              ":combine_id" => $combineId,
              ":player_id" => $playerId,
            ]);
          }
        }

        $stmt = $pdo->prepare("DELETE FROM combine_disciplines WHERE combine_id = :combine_id");
        $stmt->execute([":combine_id" => $combineId]);

        if (!empty($selectedDisciplines)) {
          $stmt = $pdo->prepare(
            "INSERT INTO combine_disciplines (combine_id, discipline_id)
             VALUES (:combine_id, :discipline_id)"
          );
          foreach ($selectedDisciplines as $disciplineId) {
            $stmt->execute([
              ":combine_id" => $combineId,
              ":discipline_id" => $disciplineId,
            ]);
          }
        }

        $pdo->commit();
        header("Location: combine.php?id=" . (int)$combineId);
        exit;
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
          $pdo->rollBack();
        }
        $combineFeedback = "Combine konnte nicht gespeichert werden.";
      }
    }

    $formCombineName = $combineName;
    $formEventDate = $eventDate;
    $formPlayerIds = $selectedPlayers;
    $formDisciplineIds = $selectedDisciplines;
  }

  if (($action === "save_results" || $action === "confirm_save_results") && !$combineError) {
    $mode = "start";
    $activeDisciplineId = filter_var($_POST["discipline_id"] ?? null, FILTER_VALIDATE_INT);

    $disciplineAllowed = false;
    foreach ($assignedDisciplines as $discipline) {
      if ((int)$discipline["id"] === (int)$activeDisciplineId) {
        $disciplineAllowed = true;
        break;
      }
    }

    if (!$activeDisciplineId || !$disciplineAllowed) {
      $startError = "Disziplin ist ungueltig.";
    } else {
      $submitted = (array)($_POST["result"] ?? []);
      $original = (array)($_POST["original"] ?? []);

      $newValues = [];
      $originalValues = [];
      foreach ($assignedPlayers as $player) {
        $playerId = (int)$player["id"];
        $newValues[$playerId] = uc_normalize_value($submitted[$playerId] ?? null);
        $originalValues[$playerId] = uc_normalize_value($original[$playerId] ?? null);
      }

      $currentValues = [];
      try {
        $stmt = $pdo->prepare(
          "SELECT player_id, result_value
           FROM combine_results
           WHERE combine_id = :combine_id AND discipline_id = :discipline_id"
        );
        $stmt->execute([
          ":combine_id" => $combineId,
          ":discipline_id" => $activeDisciplineId,
        ]);
        foreach ($stmt->fetchAll() as $row) {
          $currentValues[(int)$row["player_id"]] = uc_normalize_value($row["result_value"]);
        }
      } catch (Throwable $e) {
        $startError = "Ergebnisse konnten nicht geladen werden.";
      }

      if (!$startError && $action === "save_results") {
        foreach ($assignedPlayers as $player) {
          $playerId = (int)$player["id"];
          $current = $currentValues[$playerId] ?? null;
          if ($current !== $originalValues[$playerId]) {
            $conflicts[$playerId] = [
              "current" => $current,
              "new" => $newValues[$playerId],
              "original" => $originalValues[$playerId],
            ];
          }
        }

        if (!empty($conflicts)) {
          $needsConfirmation = true;
          $resultValues = $newValues;
        }
      }

      if (!$startError && ($action === "confirm_save_results" || empty($conflicts))) {
        try {
          $pdo->beginTransaction();
          $deleteStmt = $pdo->prepare(
            "DELETE FROM combine_results
             WHERE combine_id = :combine_id AND discipline_id = :discipline_id AND player_id = :player_id"
          );
          $upsertStmt = $pdo->prepare(
            "INSERT INTO combine_results (combine_id, discipline_id, player_id, result_value)
             VALUES (:combine_id, :discipline_id, :player_id, :result_value)
             ON DUPLICATE KEY UPDATE result_value = VALUES(result_value), updated_at = CURRENT_TIMESTAMP"
          );

          foreach ($assignedPlayers as $player) {
            $playerId = (int)$player["id"];
            $value = $newValues[$playerId];
            if ($value === null) {
              $deleteStmt->execute([
                ":combine_id" => $combineId,
                ":discipline_id" => $activeDisciplineId,
                ":player_id" => $playerId,
              ]);
            } else {
              $upsertStmt->execute([
                ":combine_id" => $combineId,
                ":discipline_id" => $activeDisciplineId,
                ":player_id" => $playerId,
                ":result_value" => $value,
              ]);
            }
          }

          $pdo->commit();
          $saveNotice = "Ergebnisse gespeichert.";
          $resultValues = $newValues;
          $needsConfirmation = false;
        } catch (Throwable $e) {
          if ($pdo->inTransaction()) {
            $pdo->rollBack();
          }
          $startError = "Ergebnisse konnten nicht gespeichert werden.";
        }
      }
    }
  }
}

if (!$pageError && !$combineError && $mode === "start" && !$needsConfirmation && !$startError) {
  if (empty($assignedDisciplines) || empty($assignedPlayers)) {
    $startError = "Bitte zuerst Spieler und Disziplinen zuordnen.";
  } else {
    if (!$activeDisciplineId && !empty($assignedDisciplines)) {
      $activeDisciplineId = (int)$assignedDisciplines[0]["id"];
    }

    $activeDisciplineDescription = "";
    $activeDisciplineUnit = "";
    foreach ($assignedDisciplines as $discipline) {
      if ((int)$discipline["id"] === (int)$activeDisciplineId) {
        $activeDisciplineDescription = $discipline["description"] ?? "";
        $activeDisciplineUnit = $discipline["unit"] ?? "";
        break;
      }
    }

    $disciplineAllowed = false;
    foreach ($assignedDisciplines as $discipline) {
      if ((int)$discipline["id"] === (int)$activeDisciplineId) {
        $disciplineAllowed = true;
        break;
      }
    }

    if (!$activeDisciplineId || !$disciplineAllowed) {
      $startError = "Disziplin ist ungültig.";
    } else {
      try {
        $stmt = $pdo->prepare(
          "SELECT player_id, result_value
           FROM combine_results
           WHERE combine_id = :combine_id AND discipline_id = :discipline_id"
        );
        $stmt->execute([
          ":combine_id" => $combineId,
          ":discipline_id" => $activeDisciplineId,
        ]);
        foreach ($stmt->fetchAll() as $row) {
          $resultValues[(int)$row["player_id"]] = uc_normalize_value($row["result_value"]);
        }
      } catch (Throwable $e) {
        $startError = "Ergebnisse konnten nicht geladen werden.";
      }
    }
  }
}

if (!$pageError && !$combineError && $mode === "results") {
  try {
    $stmt = $pdo->prepare(
      "SELECT discipline_id, player_id, result_value
       FROM combine_results
       WHERE combine_id = :combine_id"
    );
    $stmt->execute([":combine_id" => $combineId]);
    foreach ($stmt->fetchAll() as $row) {
      $discId = (int)$row["discipline_id"];
      $playerId = (int)$row["player_id"];
      $resultsByDiscipline[$discId][$playerId] = $row["result_value"];
    }
  } catch (Throwable $e) {
    $combineError = "Ergebnisse konnten nicht geladen werden.";
  }
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ultimate Combine - Combine</title>
  <link rel="stylesheet" href="ui.css">
</head>
<body>
  <div class="bg-grid"></div>

  <header class="topbar">
    <a class="pill-button" href="team.php">Zurück</a>
    <div class="brand">
      <span class="brand-text">Ultimate Combine</span>
    </div>
    <form method="post" action="">
      <input type="hidden" name="action" value="logout">
      <button class="pill-button" type="submit">Logout</button>
    </form>
  </header>

  <main class="team">
    <section class="auth-card">
      <?php if ($pageError): ?>
        <h1>Combine</h1>
        <p class="help"><?php echo htmlspecialchars($pageError, ENT_QUOTES, "UTF-8"); ?></p>
      <?php elseif ($combineError): ?>
        <h1>Combine</h1>
        <p class="help"><?php echo htmlspecialchars($combineError, ENT_QUOTES, "UTF-8"); ?></p>
      <?php else: ?>
        <div class="card-header">
          <h1><?php echo htmlspecialchars($combine["combine_name"], ENT_QUOTES, "UTF-8"); ?></h1>
          <?php if (!$editMode): ?>
            <a class="pill-button" href="combine.php?id=<?php echo (int)$combineId; ?>&edit=1">Edit</a>
          <?php endif; ?>
        </div>
        <p class="lead">Datum: <?php echo htmlspecialchars($combine["event_date"], ENT_QUOTES, "UTF-8"); ?></p>
        <?php if (!$editMode): ?>
          <div class="action-row">
            <a class="pill-button" href="combine.php?id=<?php echo (int)$combineId; ?>">Setup</a>
            <a class="pill-button" href="combine.php?id=<?php echo (int)$combineId; ?>&mode=start">Eintragen</a>
            <a class="pill-button" href="combine.php?id=<?php echo (int)$combineId; ?>&mode=results">Ergebnisse</a>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <?php if (!$pageError && !$combineError && $mode === "view"): ?>
      <section class="info">
        <h2>Übersicht</h2>
        <div class="info-grid">
          <div class="info-card">
            <h3>Spieler</h3>
            <?php if (empty($assignedPlayerIds)): ?>
              <p class="help">Keine Spieler zugeordnet.</p>
            <?php else: ?>
              <ul class="list">
                <?php foreach ($orderedPlayers as $player): ?>
                  <li class="list-item">
                    <div>
                      <strong>
                        <?php echo htmlspecialchars($player["first_name"], ENT_QUOTES, "UTF-8"); ?>
                        <?php echo " " . htmlspecialchars($player["last_name"], ENT_QUOTES, "UTF-8"); ?>
                      </strong>
                    </div>
                    <?php if ($player["jersey_number"] !== null): ?>
                      <span class="badge">#<?php echo (int)$player["jersey_number"]; ?></span>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
          <div class="info-card">
            <h3>Disziplinen</h3>
            <?php if (empty($assignedDisciplineIds)): ?>
              <p class="help">Keine Disziplinen zugeordnet.</p>
            <?php else: ?>
              <?php foreach ($assignedDisciplinesByCategory as $category => $categoryDisciplines): ?>
                <div class="category-block">
                  <h4 class="category-title"><?php echo htmlspecialchars($category, ENT_QUOTES, "UTF-8"); ?></h4>
                  <ul class="list">
                    <?php foreach ($categoryDisciplines as $discipline): ?>
                      <li class="list-item">
                        <div>
                          <strong><?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?></strong>
                          <span class="meta"><?php echo htmlspecialchars($discipline["unit"], ENT_QUOTES, "UTF-8"); ?></span>
                        </div>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <?php if (!$pageError && !$combineError && $mode === "start"): ?>
      <section class="auth-card">
        <h2>Eintragen</h2>
        <?php if ($startError): ?>
          <p class="help"><?php echo htmlspecialchars($startError, ENT_QUOTES, "UTF-8"); ?></p>
        <?php endif; ?>
        <?php if (!empty($assignedDisciplines) && !empty($assignedPlayers)): ?>
          <form class="form" method="get" action="combine.php">
            <input type="hidden" name="id" value="<?php echo (int)$combineId; ?>">
            <input type="hidden" name="mode" value="start">
            <label class="field">
              <span>Disziplin</span>
              <select name="discipline_id" required data-discipline-select data-combine-id="<?php echo (int)$combineId; ?>">
                <?php foreach ($assignedDisciplines as $discipline): ?>
                  <option value="<?php echo (int)$discipline["id"]; ?>"<?php echo (int)$discipline["id"] === (int)$activeDisciplineId ? " selected" : ""; ?>>
                    <?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <?php if (!empty($activeDisciplineDescription)): ?>
              <p class="help"><?php echo htmlspecialchars($activeDisciplineDescription, ENT_QUOTES, "UTF-8"); ?></p>
            <?php endif; ?>
          </form>
        <?php endif; ?>
      </section>

      <?php if ($needsConfirmation): ?>
        <section class="auth-card">
          <h3>Bestätigung nötig</h3>
          <p class="help">Es gab zwischenzeitliche Änderungen. Bitte bestätige das Überschreiben.</p>
          <div class="conflict-list">
            <?php foreach ($conflicts as $playerId => $conflict): ?>
              <?php
                $playerName = "Spieler #" . (int)$playerId;
                foreach ($assignedPlayers as $player) {
                  if ((int)$player["id"] === (int)$playerId) {
                    $playerName = $player["first_name"] . " " . $player["last_name"];
                    break;
                  }
                }
                $currentValue = uc_display_value($conflict["current"], "-");
                $newValue = uc_display_value($conflict["new"], "-");
              ?>
              <div class="conflict-row">
                <span><?php echo htmlspecialchars($playerName, ENT_QUOTES, "UTF-8"); ?></span>
                <span>Aktuell: <?php echo htmlspecialchars($currentValue, ENT_QUOTES, "UTF-8"); ?></span>
                <span>Neu: <?php echo htmlspecialchars($newValue, ENT_QUOTES, "UTF-8"); ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <form method="post" action="" class="form">
            <input type="hidden" name="action" value="confirm_save_results">
            <input type="hidden" name="discipline_id" value="<?php echo (int)$activeDisciplineId; ?>">
            <?php foreach ($orderedPlayers as $player): ?>
              <?php $playerId = (int)$player["id"]; ?>
              <input type="hidden" name="result[<?php echo $playerId; ?>]" value="<?php echo htmlspecialchars($resultValues[$playerId] ?? "", ENT_QUOTES, "UTF-8"); ?>">
            <?php endforeach; ?>
            <div class="form-actions">
              <button class="primary-button" type="submit">Bestätigen und speichern</button>
              <a class="text-link" href="combine.php?id=<?php echo (int)$combineId; ?>&mode=start&discipline_id=<?php echo (int)$activeDisciplineId; ?>">Abbrechen</a>
            </div>
          </form>
        </section>
      <?php endif; ?>

      <?php if (!$startError && !empty($assignedDisciplines) && !empty($assignedPlayers) && $activeDisciplineId): ?>
        <section class="auth-card">
          <h3>Ergebnisse erfassen</h3>
          <form class="form" method="post" action="">
            <input type="hidden" name="action" value="save_results">
            <input type="hidden" name="discipline_id" value="<?php echo (int)$activeDisciplineId; ?>">
            <div class="result-grid">
              <?php foreach ($orderedPlayers as $player): ?>
                <?php $playerId = (int)$player["id"]; ?>
                <label class="result-item">
                  <span>
                    <?php echo htmlspecialchars($player["first_name"], ENT_QUOTES, "UTF-8"); ?>
                    <?php echo " " . htmlspecialchars($player["last_name"], ENT_QUOTES, "UTF-8"); ?>
                  </span>
                  <span class="result-value">
                    <input class="result-input" type="text" name="result[<?php echo $playerId; ?>]" value="<?php echo htmlspecialchars(uc_display_value($resultValues[$playerId] ?? ""), ENT_QUOTES, "UTF-8"); ?>">
                    <?php if (!empty($activeDisciplineUnit)): ?>
                      <span class="unit-tag"><?php echo htmlspecialchars($activeDisciplineUnit, ENT_QUOTES, "UTF-8"); ?></span>
                    <?php endif; ?>
                  </span>
                  <input type="hidden" name="original[<?php echo $playerId; ?>]" value="<?php echo htmlspecialchars($resultValues[$playerId] ?? "", ENT_QUOTES, "UTF-8"); ?>">
                </label>
              <?php endforeach; ?>
            </div>
            <button class="primary-button" type="submit">Speichern</button>
            <?php if ($saveNotice): ?>
              <p class="help"><?php echo htmlspecialchars($saveNotice, ENT_QUOTES, "UTF-8"); ?></p>
            <?php endif; ?>
          </form>
        </section>
      <?php endif; ?>
    <?php endif; ?>

    <?php if (!$pageError && !$combineError && $mode === "results"): ?>
      <section class="info">
        <h2>Ergebnisse</h2>
        <?php
          $filteredPlayers = array_values(array_filter($assignedPlayers, function ($player) use ($filterGender, $filterPosition) {
            if ($filterGender !== "" && ($player["gender"] ?? "") !== $filterGender) {
              return false;
            }
            if ($filterPosition === "handler" && empty($player["position_handler"])) {
              return false;
            }
            if ($filterPosition === "cutter" && empty($player["position_cutter"])) {
              return false;
            }
            return true;
          }));
          $overallScores = [];
          foreach ($filteredPlayers as $player) {
            $overallScores[(int)$player["id"]] = 0;
          }
          foreach ($assignedDisciplinesByCategory as $category => $categoryDisciplines) {
            $disciplineCount = 0;
            $categoryTotals = [];
            foreach ($filteredPlayers as $player) {
              $categoryTotals[(int)$player["id"]] = 0;
            }
            foreach ($categoryDisciplines as $discipline) {
              $discId = (int)$discipline["id"];
              $direction = $discipline["rating_direction"] ?? "more";
              if ($direction !== "less" && $direction !== "more") {
                $direction = "more";
              }
              $rankValues = [];
              foreach ($filteredPlayers as $player) {
                $playerId = (int)$player["id"];
                $value = $resultsByDiscipline[$discId][$playerId] ?? null;
                $numeric = uc_value_to_float($value);
                if ($numeric === null) {
                  continue;
                }
                $rankValues[$playerId] = $numeric;
              }
              $bestValue = null;
              $worstValue = null;
              if (!empty($rankValues)) {
                $disciplineCount++;
                $values = array_values($rankValues);
                if ($direction === "less") {
                  $bestValue = min($values);
                  $worstValue = max($values);
                } else {
                  $bestValue = max($values);
                  $worstValue = min($values);
                }
              }
              foreach ($filteredPlayers as $player) {
                $playerId = (int)$player["id"];
                $numericValue = $rankValues[$playerId] ?? null;
                if ($numericValue === null || $bestValue === null || $worstValue === null) {
                  $points = 0;
                } elseif ($bestValue == $worstValue) {
                  $points = 2;
                } else {
                  $ratio = ($numericValue - $worstValue) / ($bestValue - $worstValue);
                  $points = 1 + $ratio;
                }
                $categoryTotals[$playerId] += $points;
              }
            }
            if ($disciplineCount === 0) {
              continue;
            }
            foreach ($filteredPlayers as $player) {
              $playerId = (int)$player["id"];
              $categoryAverage = $categoryTotals[$playerId] / $disciplineCount;
              $overallScores[$playerId] += $categoryAverage;
            }
          }
          $overallRankValues = $overallScores;
          arsort($overallRankValues, SORT_NUMERIC);
          $overallRanks = [];
          $pos = 0;
          $rank = 0;
          $prev = null;
          foreach ($overallRankValues as $playerId => $val) {
            $pos++;
            if ($prev === null || $val != $prev) {
              $rank = $pos;
              $prev = $val;
            }
            $overallRanks[$playerId] = $rank;
          }
        ?>
        <div class="info-card">
          <h3>Filter</h3>
          <form class="form" method="get" action="combine.php">
            <input type="hidden" name="id" value="<?php echo (int)$combineId; ?>">
            <input type="hidden" name="mode" value="results">
            <label class="field">
              <span>Geschlecht</span>
              <select name="gender">
                <option value="">Alle</option>
                <?php foreach ($genderOptions as $key => $label): ?>
                  <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, "UTF-8"); ?>"<?php echo $filterGender === $key ? " selected" : ""; ?>>
                    <?php echo htmlspecialchars($label, ENT_QUOTES, "UTF-8"); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="field">
              <span>Spielposition</span>
              <select name="position">
                <option value="">Alle</option>
                <option value="handler"<?php echo $filterPosition === "handler" ? " selected" : ""; ?>>Handler</option>
                <option value="cutter"<?php echo $filterPosition === "cutter" ? " selected" : ""; ?>>Cutter</option>
              </select>
            </label>
            <div class="form-actions">
              <button class="primary-button" type="submit">Filter anwenden</button>
              <?php if ($filterGender !== "" || $filterPosition !== ""): ?>
                <a class="text-link" href="combine.php?id=<?php echo (int)$combineId; ?>&mode=results">Zurücksetzen</a>
              <?php endif; ?>
            </div>
          </form>
        </div>
        <div class="info-card">
          <h3>Overall Ranking</h3>
          <?php if (empty($filteredPlayers)): ?>
            <p class="help">Keine Spieler für den gewählten Filter.</p>
          <?php else: ?>
            <?php
              $overallOrderedPlayers = $filteredPlayers;
              usort($overallOrderedPlayers, function ($a, $b) use ($overallScores) {
                $scoreA = $overallScores[(int)$a["id"]] ?? 0;
                $scoreB = $overallScores[(int)$b["id"]] ?? 0;
                if ($scoreA == $scoreB) {
                  $lastCompare = strcmp((string)$a["last_name"], (string)$b["last_name"]);
                  if ($lastCompare === 0) {
                    return strcmp((string)$a["first_name"], (string)$b["first_name"]);
                  }
                  return $lastCompare;
                }
                return $scoreA < $scoreB ? 1 : -1;
              });
            ?>
            <ul class="list">
              <?php foreach ($overallOrderedPlayers as $player): ?>
                <?php $playerId = (int)$player["id"]; ?>
                <?php $overallPoints = $overallScores[$playerId] ?? 0; ?>
                <?php $rankLabel = isset($overallRanks[$playerId]) ? (string)$overallRanks[$playerId] : "-"; ?>
                <li class="list-item">
                  <div class="result-name">
                    <span class="rank-pill">Platz <?php echo htmlspecialchars($rankLabel, ENT_QUOTES, "UTF-8"); ?></span>
                    <strong>
                      <?php echo htmlspecialchars($player["first_name"], ENT_QUOTES, "UTF-8"); ?>
                      <?php echo " " . htmlspecialchars($player["last_name"], ENT_QUOTES, "UTF-8"); ?>
                    </strong>
                  </div>
                  <span class="badge"><?php echo htmlspecialchars(uc_format_points($overallPoints) . " P", ENT_QUOTES, "UTF-8"); ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
        <?php if (empty($assignedDisciplines)): ?>
          <p class="help">Keine Disziplinen zugeordnet.</p>
        <?php else: ?>
          <?php foreach ($assignedDisciplinesByCategory as $category => $categoryDisciplines): ?>
            <div class="category-block">
              <h3 class="category-title"><?php echo htmlspecialchars($category, ENT_QUOTES, "UTF-8"); ?></h3>
              <?php foreach ($categoryDisciplines as $discipline): ?>
                <?php
                  $discId = (int)$discipline["id"];
                  $direction = $discipline["rating_direction"] ?? "more";
                  if ($direction !== "less" && $direction !== "more") {
                    $direction = "more";
                  }
                  $unit = trim((string)($discipline["unit"] ?? ""));
                  $rankValues = [];
                  foreach ($filteredPlayers as $player) {
                    $playerId = (int)$player["id"];
                    $value = $resultsByDiscipline[$discId][$playerId] ?? null;
                    $numeric = uc_value_to_float($value);
                    if ($numeric === null) {
                      continue;
                    }
                    $rankValues[$playerId] = $numeric;
                  }
                  if ($direction === "less") {
                    asort($rankValues, SORT_NUMERIC);
                  } else {
                    arsort($rankValues, SORT_NUMERIC);
                  }
                  $ranks = [];
                  $pos = 0;
                  $rank = 0;
                  $prev = null;
                  foreach ($rankValues as $playerId => $val) {
                    $pos++;
                    if ($prev === null || $val != $prev) {
                      $rank = $pos;
                      $prev = $val;
                    }
                    $ranks[$playerId] = $rank;
                  }
                  $bestValue = null;
                  $worstValue = null;
                  if (!empty($rankValues)) {
                    $values = array_values($rankValues);
                    if ($direction === "less") {
                      $bestValue = min($values);
                      $worstValue = max($values);
                    } else {
                      $bestValue = max($values);
                      $worstValue = min($values);
                    }
                  }
                ?>
                <div class="info-card">
                  <h4><?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?></h4>
                  <?php if (empty($filteredPlayers)): ?>
                    <p class="help">Keine Spieler für den gewählten Filter.</p>
                  <?php else: ?>
                    <?php
                      $orderedPlayers = [];
                      $rankedIds = array_keys($rankValues);
                      foreach ($rankedIds as $playerId) {
                        foreach ($filteredPlayers as $player) {
                          if ((int)$player["id"] === (int)$playerId) {
                            $orderedPlayers[] = $player;
                            break;
                          }
                        }
                      }
                      foreach ($filteredPlayers as $player) {
                        if (!in_array((int)$player["id"], $rankedIds, true)) {
                          $orderedPlayers[] = $player;
                        }
                      }
                    ?>
                    <ul class="list">
                      <?php foreach ($orderedPlayers as $player): ?>
                        <?php $playerId = (int)$player["id"]; ?>
                        <?php $value = $resultsByDiscipline[$discId][$playerId] ?? null; ?>
                        <?php $display = uc_display_value($value, "-"); ?>
                        <?php if ($display !== "-" && $unit !== "") { $display .= " " . $unit; } ?>
                        <?php
                          $numericValue = $rankValues[$playerId] ?? null;
                          if ($numericValue === null || $bestValue === null || $worstValue === null) {
                            $points = 0;
                          } elseif ($bestValue == $worstValue) {
                            $points = 2;
                          } else {
                            $ratio = ($numericValue - $worstValue) / ($bestValue - $worstValue);
                            $points = 1 + $ratio;
                          }
                          $pointsLabel = uc_format_points($points) . " P";
                        ?>
                        <?php $rankLabel = isset($ranks[$playerId]) ? (string)$ranks[$playerId] : "-"; ?>
                        <li class="list-item">
                          <div class="result-name">
                            <span class="rank-pill">
                              Platz <?php echo htmlspecialchars($rankLabel, ENT_QUOTES, "UTF-8"); ?>
                              &middot;
                              <?php echo htmlspecialchars($pointsLabel, ENT_QUOTES, "UTF-8"); ?>
                            </span>
                            <strong>
                              <?php echo htmlspecialchars($player["first_name"], ENT_QUOTES, "UTF-8"); ?>
                              <?php echo " " . htmlspecialchars($player["last_name"], ENT_QUOTES, "UTF-8"); ?>
                            </strong>
                          </div>
                          <span class="badge">
                            <?php echo htmlspecialchars($display, ENT_QUOTES, "UTF-8"); ?>
                          </span>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($editMode && !$pageError && !$combineError): ?>
      <section class="auth-card" id="edit">
        <h2>Combine bearbeiten</h2>
        <form class="form" method="post" action="">
          <input type="hidden" name="action" value="update_combine">
          <label class="field">
            <span>Name</span>
            <input type="text" name="combine_name" value="<?php echo htmlspecialchars($formCombineName, ENT_QUOTES, "UTF-8"); ?>" required>
          </label>
          <label class="field">
            <span>Datum</span>
            <input type="date" name="event_date" value="<?php echo htmlspecialchars($formEventDate, ENT_QUOTES, "UTF-8"); ?>" required>
          </label>

          <div class="field">
            <span>Spieler</span>
            <?php if (empty($players)): ?>
              <p class="help">Noch keine Spieler angelegt.</p>
            <?php else: ?>
              <div class="check-grid">
                <?php foreach ($players as $player): ?>
                  <label class="check-item">
                    <input type="checkbox" name="players[]" value="<?php echo (int)$player["id"]; ?>"<?php echo in_array((int)$player["id"], $formPlayerIds, true) ? " checked" : ""; ?>>
                    <span>
                      <?php echo htmlspecialchars($player["first_name"], ENT_QUOTES, "UTF-8"); ?>
                      <?php echo " " . htmlspecialchars($player["last_name"], ENT_QUOTES, "UTF-8"); ?>
                      <?php if ($player["jersey_number"] !== null): ?>
                        <span class="meta">#<?php echo (int)$player["jersey_number"]; ?></span>
                      <?php endif; ?>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="field">
            <span>Disziplinen</span>
            <?php if (empty($disciplines)): ?>
              <p class="help">Noch keine Disziplinen angelegt.</p>
            <?php else: ?>
              <div class="check-grid">
                <?php foreach ($disciplines as $discipline): ?>
                  <label class="check-item">
                    <input type="checkbox" name="disciplines[]" value="<?php echo (int)$discipline["id"]; ?>"<?php echo in_array((int)$discipline["id"], $formDisciplineIds, true) ? " checked" : ""; ?>>
                    <span>
                      <?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?>
                      <span class="meta">
                        <?php echo htmlspecialchars($discipline["category"], ENT_QUOTES, "UTF-8"); ?>
                        &middot;
                        <?php echo htmlspecialchars($discipline["unit"], ENT_QUOTES, "UTF-8"); ?>
                      </span>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="form-actions">
            <button class="primary-button" type="submit">Speichern</button>
            <a class="text-link" href="combine.php?id=<?php echo (int)$combineId; ?>">Abbrechen</a>
          </div>
          <?php if ($combineFeedback): ?>
            <p class="help"><?php echo htmlspecialchars($combineFeedback, ENT_QUOTES, "UTF-8"); ?></p>
          <?php endif; ?>
        </form>
      </section>
    <?php endif; ?>
  </main>
  <script>
    const disciplineSelect = document.querySelector("[data-discipline-select]");
    if (disciplineSelect) {
      let isDirty = false;
      let lastValue = disciplineSelect.value;
      const resultInputs = document.querySelectorAll(".result-input");
      resultInputs.forEach((input) => {
        input.addEventListener("input", () => {
          isDirty = true;
        });
      });

      disciplineSelect.addEventListener("change", () => {
        const nextValue = disciplineSelect.value;
        if (isDirty) {
          const ok = window.confirm("Ungesicherte Aenderungen gehen verloren. Trotzdem wechseln?");
          if (!ok) {
            disciplineSelect.value = lastValue;
            return;
          }
        }
        const combineId = disciplineSelect.dataset.combineId;
        window.location.href = `combine.php?id=${combineId}&mode=start&discipline_id=${encodeURIComponent(nextValue)}`;
      });

      disciplineSelect.addEventListener("focus", () => {
        lastValue = disciplineSelect.value;
      });
    }
  </script></body>
</html>
