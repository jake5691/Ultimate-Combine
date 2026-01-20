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

function uc_format_unit($unit, array $unitMap): string {
  $unit = trim((string)$unit);
  if ($unit === "") {
    return "";
  }
  if (isset($unitMap[$unit])) {
    return $unitMap[$unit];
  }
  if (preg_match('/\(([^)]+)\)\s*$/', $unit, $matches)) {
    return trim($matches[1]);
  }
  return $unit;
}

if (!$pdo) {
  $pageError = $dbError ?? "Datenbank ist nicht erreichbar.";
} else {
  $pageError = null;
}

$teamId = $_SESSION["team_id"] ?? null;
$teamName = $_SESSION["team_name"] ?? "";

if (!empty($_SESSION["is_admin"])) {
  header("Location: admin.php");
  exit;
}

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
$units = [];
$unitAbbrMap = [];
$assignedPlayerIds = [];
$assignedDisciplineIds = [];
  $assignedDisciplinesByCategory = [];
  $assignedPlayers = [];
  $assignedDisciplines = [];
  $combineDisciplineWeights = [];
  $combineCategoryWeights = [];
  $resultsByDiscipline = [];
  $resultValues = [];
  $formDisciplineWeights = [];
  $formCategoryWeights = [];
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
$overallMode = $_GET["overall"] ?? "sum";
if (!in_array($overallMode, ["sum", "avg"], true)) {
  $overallMode = "sum";
}

$formCombineName = "";
$formEventDate = "";
$formCombineLocation = "";
$formCombineNotes = "";
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
    "SELECT id, combine_name, event_date, combine_location, combine_notes
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
     WHERE team_id = :team_id OR team_id IS NULL
     ORDER BY created_at DESC"
  );
  $stmt->execute([":team_id" => $teamId]);
  $disciplines = $stmt->fetchAll();

  $stmt = $pdo->prepare(
    "SELECT unit_name, unit_abbreviation
     FROM units
     ORDER BY unit_name ASC"
  );
  $stmt->execute();
  $units = $stmt->fetchAll();
  foreach ($units as $unitRow) {
    $unitName = trim((string)($unitRow["unit_name"] ?? ""));
    $unitAbbr = trim((string)($unitRow["unit_abbreviation"] ?? ""));
    if ($unitName === "" || $unitAbbr === "") {
      continue;
    }
    $unitAbbrMap[$unitName] = $unitAbbr;
    $unitAbbrMap[$unitAbbr] = $unitAbbr;
    $unitAbbrMap[$unitName . " (" . $unitAbbr . ")"] = $unitAbbr;
  }

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
        "SELECT discipline_id, weight
         FROM combine_disciplines
         WHERE combine_id = :combine_id"
      );
      $stmt->execute([":combine_id" => $combineId]);
      $combineDisciplineRows = $stmt->fetchAll();
      $assignedDisciplineIds = array_map("intval", array_column($combineDisciplineRows, "discipline_id"));
      foreach ($combineDisciplineRows as $row) {
        $discId = (int)$row["discipline_id"];
        $weight = (float)($row["weight"] ?? 1);
        if ($weight <= 0) {
          $weight = 1;
        }
        $combineDisciplineWeights[$discId] = $weight;
      }

      $stmt = $pdo->prepare(
        "SELECT category, weight
         FROM combine_category_weights
         WHERE combine_id = :combine_id"
      );
      $stmt->execute([":combine_id" => $combineId]);
      foreach ($stmt->fetchAll() as $row) {
        $categoryKey = trim((string)($row["category"] ?? ""));
        if ($categoryKey === "") {
          continue;
        }
        $weight = (float)($row["weight"] ?? 1);
        if ($weight <= 0) {
          $weight = 1;
        }
        $combineCategoryWeights[$categoryKey] = $weight;
      }
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
      $activeDisciplineUnit = uc_format_unit($discipline["unit"] ?? "", $unitAbbrMap);
      break;
    }
  }

  $formCombineName = $combine["combine_name"] ?? "";
  $formEventDate = $combine["event_date"] ?? "";
  $formCombineLocation = $combine["combine_location"] ?? "";
  $formCombineNotes = $combine["combine_notes"] ?? "";
  $formPlayerIds = $assignedPlayerIds;
  $formDisciplineIds = $assignedDisciplineIds;
  $formDisciplineWeights = $combineDisciplineWeights;
  $formCategoryWeights = $combineCategoryWeights;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$pageError) {
  $action = $_POST["action"] ?? "";

  if ($action === "update_combine" && !$combineError) {
    $combineName = trim($_POST["combine_name"] ?? "");
    $eventDate = trim($_POST["event_date"] ?? "");
    $combineLocation = trim($_POST["combine_location"] ?? "");
    $combineNotes = trim($_POST["combine_notes"] ?? "");
    $selectedPlayers = (array)($_POST["players"] ?? []);
    $selectedDisciplines = (array)($_POST["disciplines"] ?? []);
    $disciplineWeightsInput = (array)($_POST["discipline_weight"] ?? []);
    $categoryNamesInput = (array)($_POST["category_name"] ?? []);
    $categoryWeightsInput = (array)($_POST["category_weight"] ?? []);

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
    $disciplineById = [];
    foreach ($disciplines as $discipline) {
      $disciplineMap[(int)$discipline["id"]] = true;
      $disciplineById[(int)$discipline["id"]] = $discipline;
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
               event_date = :event_date,
               combine_location = :combine_location,
               combine_notes = :combine_notes
           WHERE id = :id AND team_id = :team_id"
        );
        $stmt->execute([
          ":combine_name" => $combineName,
          ":event_date" => $eventDate,
          ":combine_location" => $combineLocation !== "" ? $combineLocation : null,
          ":combine_notes" => $combineNotes !== "" ? $combineNotes : null,
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
            "INSERT INTO combine_disciplines (combine_id, discipline_id, weight)
             VALUES (:combine_id, :discipline_id, :weight)"
          );
          foreach ($selectedDisciplines as $disciplineId) {
            $weight = isset($disciplineWeightsInput[$disciplineId])
              ? (float)str_replace(",", ".", (string)$disciplineWeightsInput[$disciplineId])
              : 1.0;
            if ($weight <= 0) {
              $weight = 1.0;
            }
            $stmt->execute([
              ":combine_id" => $combineId,
              ":discipline_id" => $disciplineId,
              ":weight" => $weight,
            ]);
          }
        }

        $stmt = $pdo->prepare("DELETE FROM combine_category_weights WHERE combine_id = :combine_id");
        $stmt->execute([":combine_id" => $combineId]);

        $categoryWeights = [];
        foreach ($categoryNamesInput as $index => $categoryNameRaw) {
          $categoryName = trim((string)$categoryNameRaw);
          if ($categoryName === "") {
            continue;
          }
          $weight = isset($categoryWeightsInput[$index])
            ? (float)str_replace(",", ".", (string)$categoryWeightsInput[$index])
            : 1.0;
          if ($weight <= 0) {
            $weight = 1.0;
          }
          $categoryWeights[$categoryName] = $weight;
        }

        $selectedCategories = [];
        foreach ($selectedDisciplines as $disciplineId) {
          $category = trim((string)($disciplineById[$disciplineId]["category"] ?? ""));
          if ($category === "") {
            $category = "Ohne Kategorie";
          }
          $selectedCategories[$category] = true;
        }

        if (!empty($selectedCategories)) {
          $stmt = $pdo->prepare(
            "INSERT INTO combine_category_weights (combine_id, category, weight)
             VALUES (:combine_id, :category, :weight)"
          );
          foreach (array_keys($selectedCategories) as $category) {
            $weight = $categoryWeights[$category] ?? 1.0;
            $stmt->execute([
              ":combine_id" => $combineId,
              ":category" => $category,
              ":weight" => $weight,
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
    $formCombineLocation = $combineLocation;
    $formCombineNotes = $combineNotes;
    $formPlayerIds = $selectedPlayers;
    $formDisciplineIds = $selectedDisciplines;
    $formDisciplineWeights = $disciplineWeightsInput;
    $formCategoryWeights = [];
    foreach ($categoryNamesInput as $index => $categoryNameRaw) {
      $categoryName = trim((string)$categoryNameRaw);
      if ($categoryName === "") {
        continue;
      }
      $formCategoryWeights[$categoryName] = $categoryWeightsInput[$index] ?? "1";
    }
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
        $activeDisciplineUnit = uc_format_unit($discipline["unit"] ?? "", $unitAbbrMap);
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
  <link rel="icon" href="assets/favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="assets/apple-touch-icon.png">
  <link rel="manifest" href="assets/site.webmanifest">
  <link rel="stylesheet" href="ui.css">
</head>
<body>
  <div class="bg-grid"></div>

  <header class="topbar">
    <button class="pill-button" type="button" onclick="window.location.href='team.php'">Zurück</button>
    <div class="brand">
      <img class="brand-logo" src="assets/FrisbeeCatch.png" alt="Ultimate Combine">
      <span class="brand-text">Ultimate Combine</span>
      <span class="brand-sep">•</span>
      <span class="brand-team"><?php echo htmlspecialchars($teamName, ENT_QUOTES, "UTF-8"); ?></span>
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
        <?php if (!empty($combine["combine_location"])): ?>
          <p class="lead">Ort: <?php echo htmlspecialchars($combine["combine_location"], ENT_QUOTES, "UTF-8"); ?></p>
        <?php endif; ?>
        <?php if (!empty($combine["combine_notes"])): ?>
          <p class="help"><?php echo htmlspecialchars($combine["combine_notes"], ENT_QUOTES, "UTF-8"); ?></p>
        <?php endif; ?>
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
        <div class="info-grid info-grid--two">
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
              <?php
                $showCategoryWeights = false;
                foreach ($assignedDisciplinesByCategory as $categoryKey => $categoryDisciplines) {
                  $weight = $combineCategoryWeights[$categoryKey] ?? 1;
                  if ((float)$weight !== 1.0) {
                    $showCategoryWeights = true;
                    break;
                  }
                }
              ?>
              <?php foreach ($assignedDisciplinesByCategory as $category => $categoryDisciplines): ?>
                <?php
                  $categoryWeight = $combineCategoryWeights[$category] ?? 1;
                  $showDisciplineWeights = false;
                  foreach ($categoryDisciplines as $discipline) {
                    $discId = (int)$discipline["id"];
                    $discWeight = $combineDisciplineWeights[$discId] ?? 1;
                    if ((float)$discWeight !== 1.0) {
                      $showDisciplineWeights = true;
                      break;
                    }
                  }
                ?>
                <div class="category-block">
                  <h4 class="category-title">
                    <?php echo htmlspecialchars($category, ENT_QUOTES, "UTF-8"); ?>
                    <?php if ($showCategoryWeights): ?>
                      <span class="meta">(<?php echo htmlspecialchars($categoryWeight, ENT_QUOTES, "UTF-8"); ?>x)</span>
                    <?php endif; ?>
                  </h4>
                  <ul class="list">
                    <?php foreach ($categoryDisciplines as $discipline): ?>
                      <?php $disciplineWeight = $combineDisciplineWeights[(int)$discipline["id"]] ?? 1; ?>
                      <li class="list-item">
                        <div>
                          <strong><?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?></strong>
                          <?php if ($showDisciplineWeights): ?>
                            <span class="meta">(<?php echo htmlspecialchars($disciplineWeight, ENT_QUOTES, "UTF-8"); ?>x)</span>
                          <?php endif; ?>
                          <span class="meta"><?php echo htmlspecialchars(uc_format_unit($discipline["unit"] ?? "", $unitAbbrMap), ENT_QUOTES, "UTF-8"); ?></span>
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
          $selectedPlayerId = filter_var($_GET["player_id"] ?? null, FILTER_VALIDATE_INT);
          $selectedPlayer = null;
          if ($selectedPlayerId) {
            foreach ($filteredPlayers as $player) {
              if ((int)$player["id"] === (int)$selectedPlayerId) {
                $selectedPlayer = $player;
                break;
              }
            }
            if (!$selectedPlayer) {
              $selectedPlayerId = null;
            }
          }
          $overallScoresSum = [];
          $overallScoresAvg = [];
          $overallCategoryCounts = [];
          $categoryAverages = [];
          $categoryTeamAverages = [];
          $categoryTeamWeightedAverages = [];
          $categoryWeights = [];
          foreach ($filteredPlayers as $player) {
            $playerId = (int)$player["id"];
            $overallScoresSum[$playerId] = 0;
            $overallScoresAvg[$playerId] = 0;
            $overallCategoryCounts[$playerId] = 0;
          }
          foreach ($assignedDisciplinesByCategory as $category => $categoryDisciplines) {
            $categoryWeight = $combineCategoryWeights[$category] ?? 1.0;
            if ($categoryWeight <= 0) {
              $categoryWeight = 1.0;
            }
            $categoryWeights[$category] = $categoryWeight;
            $disciplineCount = 0;
            $categoryTotals = [];
            $categoryTotalsAvg = [];
            $categoryWeightSumAll = 0.0;
            $categoryWeightSumsAvg = [];
            foreach ($filteredPlayers as $player) {
              $playerId = (int)$player["id"];
              $categoryTotals[$playerId] = 0;
              $categoryTotalsAvg[$playerId] = 0;
              $categoryWeightSumsAvg[$playerId] = 0.0;
            }
            foreach ($categoryDisciplines as $discipline) {
              $discId = (int)$discipline["id"];
              $disciplineWeight = $combineDisciplineWeights[$discId] ?? 1.0;
              if ($disciplineWeight <= 0) {
                $disciplineWeight = 1.0;
              }
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
                $categoryWeightSumAll += $disciplineWeight;
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
                $categoryTotals[$playerId] += $points * $disciplineWeight;
                if ($numericValue !== null && $bestValue !== null && $worstValue !== null) {
                  $categoryTotalsAvg[$playerId] += $points * $disciplineWeight;
                  $categoryWeightSumsAvg[$playerId] += $disciplineWeight;
                }
              }
            }
            if ($disciplineCount === 0 || $categoryWeightSumAll <= 0) {
              continue;
            }
            $teamSum = 0;
            $teamCount = 0;
            foreach ($filteredPlayers as $player) {
              $playerId = (int)$player["id"];
              $categoryAverage = $categoryTotals[$playerId] / $categoryWeightSumAll;
              $overallScoresSum[$playerId] += $categoryAverage * $categoryWeight;
              $avgWeightSum = $categoryWeightSumsAvg[$playerId] ?? 0.0;
              if ($avgWeightSum > 0) {
                $categoryAverageAvg = $categoryTotalsAvg[$playerId] / $avgWeightSum;
                $overallScoresAvg[$playerId] += $categoryAverageAvg;
                $overallCategoryCounts[$playerId] += 1;
              }
              $categoryAverages[$category][$playerId] = $categoryAverage;
              $teamSum += $categoryAverage;
              $teamCount++;
            }
            if ($teamCount > 0) {
              $categoryTeamAverages[$category] = $teamSum / $teamCount;
              $categoryTeamWeightedAverages[$category] = ($teamSum / $teamCount) * $categoryWeight;
            }
          }
          foreach ($overallScoresAvg as $playerId => $score) {
            $count = $overallCategoryCounts[$playerId] ?? 0;
            if ($count > 0) {
              $overallScoresAvg[$playerId] = $score / $count;
            }
          }
          $overallScores = $overallMode === "avg" ? $overallScoresAvg : $overallScoresSum;
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
          $overallBaseParams = [
            "id" => (int)$combineId,
            "mode" => "results",
          ];
          if ($filterGender !== "") {
            $overallBaseParams["gender"] = $filterGender;
          }
          if ($filterPosition !== "") {
            $overallBaseParams["position"] = $filterPosition;
          }
          $overallBaseUrl = "combine.php?" . http_build_query($overallBaseParams);
          $overallSumUrl = $overallBaseUrl . "&overall=sum";
          $overallAvgUrl = $overallBaseUrl . "&overall=avg";
        ?>
        <div class="info-card">
          <h3>Filter</h3>
          <form class="form" method="get" action="combine.php">
            <input type="hidden" name="id" value="<?php echo (int)$combineId; ?>">
            <input type="hidden" name="mode" value="results">
            <input type="hidden" name="overall" value="<?php echo htmlspecialchars($overallMode, ENT_QUOTES, "UTF-8"); ?>">
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
                <a class="text-link" href="<?php echo htmlspecialchars($overallBaseUrl . "&overall=" . urlencode($overallMode), ENT_QUOTES, "UTF-8"); ?>">Zurücksetzen</a>
              <?php endif; ?>
            </div>
          </form>
        </div>
        <div class="info-card">
          <div class="card-header">
            <h3>Overall Ranking</h3>
            <div class="card-actions">
              <a class="pill-button<?php echo $overallMode === "sum" ? " is-active" : ""; ?>" href="<?php echo htmlspecialchars($overallSumUrl, ENT_QUOTES, "UTF-8"); ?>">Summe</a>
              <a class="pill-button<?php echo $overallMode === "avg" ? " is-active" : ""; ?>" href="<?php echo htmlspecialchars($overallAvgUrl, ENT_QUOTES, "UTF-8"); ?>">Ø Kategorien</a>
            </div>
          </div>
          <?php if ($overallMode === "sum"): ?>
            <p class="help">Summe: Nicht absolvierte Disziplinen zählen als 0 in den Kategorien. Punkte werden relativ zu den Teilnehmern berechnet.</p>
          <?php else: ?>
            <p class="help">Ø Kategorien: Es zählen nur Kategorien und Disziplinen, die der Spieler absolviert hat. Punkte werden relativ zu den Teilnehmern berechnet.</p>
          <?php endif; ?>
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
                <?php
                  $detailUrl = "combine.php?id=" . (int)$combineId . "&mode=results";
                  if ($filterGender !== "") {
                    $detailUrl .= "&gender=" . urlencode($filterGender);
                  }
                  if ($filterPosition !== "") {
                    $detailUrl .= "&position=" . urlencode($filterPosition);
                  }
                  $detailUrl .= "&overall=" . urlencode($overallMode);
                  $detailUrl .= "&player_id=" . $playerId;
                ?>
                <li class="list-item<?php echo ($selectedPlayerId && (int)$selectedPlayerId === $playerId) ? " is-active" : ""; ?>">
                  <a class="list-link" href="<?php echo htmlspecialchars($detailUrl, ENT_QUOTES, "UTF-8"); ?>">
                    <div class="result-name">
                      <span class="rank-pill">Platz <?php echo htmlspecialchars($rankLabel, ENT_QUOTES, "UTF-8"); ?></span>
                      <strong>
                        <?php echo htmlspecialchars($player["first_name"], ENT_QUOTES, "UTF-8"); ?>
                        <?php echo " " . htmlspecialchars($player["last_name"], ENT_QUOTES, "UTF-8"); ?>
                      </strong>
                    </div>
                    <span class="badge"><?php echo htmlspecialchars(uc_format_points($overallPoints) . " P", ENT_QUOTES, "UTF-8"); ?></span>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
        <?php if ($selectedPlayerId && $selectedPlayer): ?>
          <?php
            $radarData = [];
            foreach ($categoryAverages as $category => $playerAverages) {
              $categoryWeight = $combineCategoryWeights[$category] ?? 1;
              if ($categoryWeight <= 0) {
                $categoryWeight = 1;
              }
              $playerAverage = ($playerAverages[$selectedPlayerId] ?? 0) * $categoryWeight;
              $teamAverage = $categoryTeamWeightedAverages[$category] ?? ($categoryTeamAverages[$category] ?? 0);
              $radarData[] = [
                "label" => $category,
                "player" => $playerAverage,
                "team" => $teamAverage,
              ];
            }
            $resetUrl = "combine.php?id=" . (int)$combineId . "&mode=results";
            if ($filterGender !== "") {
              $resetUrl .= "&gender=" . urlencode($filterGender);
            }
            if ($filterPosition !== "") {
              $resetUrl .= "&position=" . urlencode($filterPosition);
            }
            $resetUrl .= "&overall=" . urlencode($overallMode);
          ?>
          <div class="info-card player-detail">
            <div class="card-header">
              <h3>
                Ergebnisse: <?php echo htmlspecialchars($selectedPlayer["first_name"], ENT_QUOTES, "UTF-8"); ?>
                <?php echo " " . htmlspecialchars($selectedPlayer["last_name"], ENT_QUOTES, "UTF-8"); ?>
              </h3>
              <a class="text-link" href="<?php echo htmlspecialchars($resetUrl, ENT_QUOTES, "UTF-8"); ?>">Schließen</a>
            </div>
            <?php if (empty($radarData)): ?>
              <p class="help">Keine Kategorien für die Anzeige.</p>
            <?php else: ?>
              <div class="radar-grid">
                <div class="radar-chart">
                  <canvas id="radar-chart" width="360" height="360"></canvas>
                  <div class="radar-legend is-overlay">
                    <span class="legend-item legend-player">Spieler</span>
                    <span class="legend-item legend-team">Team</span>
                  </div>
                </div>
                <div class="radar-details">
                  <?php
                    $showCategoryWeights = false;
                    foreach ($assignedDisciplinesByCategory as $categoryKey => $categoryDisciplines) {
                      $weight = $combineCategoryWeights[$categoryKey] ?? 1;
                      if ((float)$weight !== 1.0) {
                        $showCategoryWeights = true;
                        break;
                      }
                    }
                  ?>
                  <?php foreach ($assignedDisciplinesByCategory as $category => $categoryDisciplines): ?>
                    <?php
                      $categoryWeight = $combineCategoryWeights[$category] ?? 1;
                      $showDisciplineWeights = false;
                      foreach ($categoryDisciplines as $discipline) {
                        $discId = (int)$discipline["id"];
                        $discWeight = $combineDisciplineWeights[$discId] ?? 1;
                        if ((float)$discWeight !== 1.0) {
                          $showDisciplineWeights = true;
                          break;
                        }
                      }
                    ?>
                    <div class="category-block">
                      <h4 class="category-title">
                        <?php echo htmlspecialchars($category, ENT_QUOTES, "UTF-8"); ?>
                        <?php if ($showCategoryWeights): ?>
                          <span class="meta">(<?php echo htmlspecialchars($categoryWeight, ENT_QUOTES, "UTF-8"); ?>x)</span>
                        <?php endif; ?>
                      </h4>
                      <?php if (count($categoryDisciplines) > 1): ?>
                        <?php
                          $categoryScore = $categoryAverages[$category][$selectedPlayerId] ?? null;
                          $categoryScoreLabel = $categoryScore === null ? "-" : uc_format_points($categoryScore) . " P";
                        ?>
                        <p class="help">Kategorie-Score: <?php echo htmlspecialchars($categoryScoreLabel, ENT_QUOTES, "UTF-8"); ?></p>
                      <?php endif; ?>
                      <ul class="list">
                        <?php foreach ($categoryDisciplines as $discipline): ?>
                          <?php
                            $discId = (int)$discipline["id"];
                            $direction = $discipline["rating_direction"] ?? "more";
                            if ($direction !== "less" && $direction !== "more") {
                              $direction = "more";
                            }
                            $unit = uc_format_unit($discipline["unit"] ?? "", $unitAbbrMap);
                            $disciplineWeight = $combineDisciplineWeights[$discId] ?? 1;
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
                            $playerValue = $resultsByDiscipline[$discId][$selectedPlayerId] ?? null;
                            $display = uc_display_value($playerValue, "-");
                            if ($display !== "-" && $unit !== "") { $display .= " " . $unit; }
                            $numericValue = $rankValues[$selectedPlayerId] ?? null;
                            if ($numericValue === null || $bestValue === null || $worstValue === null) {
                              $points = 0;
                            } elseif ($bestValue == $worstValue) {
                              $points = 2;
                            } else {
                              $ratio = ($numericValue - $worstValue) / ($bestValue - $worstValue);
                              $points = 1 + $ratio;
                            }
                            $pointsLabel = uc_format_points($points) . " P";
                            $rankLabel = isset($ranks[$selectedPlayerId]) ? (string)$ranks[$selectedPlayerId] : "-";
                          ?>
                          <li class="list-item">
                            <div>
                              <strong><?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?></strong>
                              <?php if ($showDisciplineWeights): ?>
                                <span class="meta">(<?php echo htmlspecialchars($disciplineWeight, ENT_QUOTES, "UTF-8"); ?>x)</span>
                              <?php endif; ?>
                              <?php if ($display !== "-"): ?>
                                <span class="meta"><?php echo htmlspecialchars($display, ENT_QUOTES, "UTF-8"); ?></span>
                              <?php endif; ?>
                            </div>
                            <span class="badge">
                              <?php echo htmlspecialchars("Platz " . $rankLabel . " · " . $pointsLabel, ENT_QUOTES, "UTF-8"); ?>
                            </span>
                          </li>
                        <?php endforeach; ?>
                      </ul>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <script id="radar-data" type="application/json"><?php echo json_encode($radarData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if (empty($assignedDisciplines)): ?>
          <p class="help">Keine Disziplinen zugeordnet.</p>
        <?php else: ?>
          <?php
            $showCategoryWeights = false;
            foreach ($assignedDisciplinesByCategory as $categoryKey => $categoryDisciplines) {
              $weight = $combineCategoryWeights[$categoryKey] ?? 1;
              if ((float)$weight !== 1.0) {
                $showCategoryWeights = true;
                break;
              }
            }
          ?>
          <?php foreach ($assignedDisciplinesByCategory as $category => $categoryDisciplines): ?>
            <?php
              $categoryWeight = $combineCategoryWeights[$category] ?? 1;
              $showDisciplineWeights = false;
              foreach ($categoryDisciplines as $discipline) {
                $discId = (int)$discipline["id"];
                $discWeight = $combineDisciplineWeights[$discId] ?? 1;
                if ((float)$discWeight !== 1.0) {
                  $showDisciplineWeights = true;
                  break;
                }
              }
            ?>
            <div class="category-block">
              <h3 class="category-title">
                <?php echo htmlspecialchars($category, ENT_QUOTES, "UTF-8"); ?>
                <?php if ($showCategoryWeights): ?>
                  <span class="meta">(<?php echo htmlspecialchars($categoryWeight, ENT_QUOTES, "UTF-8"); ?>x)</span>
                <?php endif; ?>
              </h3>
              <?php foreach ($categoryDisciplines as $discipline): ?>
                <?php
                  $discId = (int)$discipline["id"];
                  $direction = $discipline["rating_direction"] ?? "more";
                  if ($direction !== "less" && $direction !== "more") {
                    $direction = "more";
                  }
                  $unit = uc_format_unit($discipline["unit"] ?? "", $unitAbbrMap);
                  $disciplineWeight = $combineDisciplineWeights[$discId] ?? 1;
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
                  $topValue = null;
                  $topPlayerIds = [];
                  $averageValue = null;
                  if (!empty($rankValues)) {
                    $values = array_values($rankValues);
                    $averageValue = array_sum($values) / count($values);
                    $topValue = $direction === "less" ? min($values) : max($values);
                    foreach ($rankValues as $playerId => $numeric) {
                      if ($numeric == $topValue) {
                        $topPlayerIds[] = $playerId;
                      }
                    }
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
                  <details>
                    <summary>
                      <strong><?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?></strong>
                      <?php if ($showDisciplineWeights): ?>
                        <span class="meta">(<?php echo htmlspecialchars($disciplineWeight, ENT_QUOTES, "UTF-8"); ?>x)</span>
                      <?php endif; ?>
                      <span class="meta">
                        <?php
                          $topLabel = $topValue === null ? "-" : uc_display_value($topValue, "-");
                          if ($topLabel !== "-" && $unit !== "") { $topLabel .= " " . $unit; }
                          $avgLabel = $averageValue === null
                            ? "-"
                            : uc_display_value(number_format($averageValue, 2, ".", ""), "-");
                          if ($avgLabel !== "-" && $unit !== "") { $avgLabel .= " " . $unit; }
                        ?>
                        Top: <?php echo htmlspecialchars($topLabel, ENT_QUOTES, "UTF-8"); ?>
                        &middot;
                        Ø: <?php echo htmlspecialchars($avgLabel, ENT_QUOTES, "UTF-8"); ?>
                      </span>
                      <?php if (!empty($topPlayerIds)): ?>
                        <?php
                          $topNames = [];
                          foreach ($topPlayerIds as $topPlayerId) {
                            foreach ($filteredPlayers as $player) {
                              if ((int)$player["id"] === (int)$topPlayerId) {
                                $topNames[] = $player["first_name"] . " " . $player["last_name"];
                                break;
                              }
                            }
                          }
                        ?>
                        <div class="detail">
                          Top: <?php echo htmlspecialchars(implode(", ", $topNames), ENT_QUOTES, "UTF-8"); ?>
                        </div>
                      <?php else: ?>
                        <div class="detail">Top: -</div>
                      <?php endif; ?>
                    </summary>
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
                  </details>
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
          <label class="field">
            <span>Ort</span>
            <input type="text" name="combine_location" value="<?php echo htmlspecialchars($formCombineLocation, ENT_QUOTES, "UTF-8"); ?>">
          </label>
          <label class="field">
            <span>Notizen</span>
            <textarea name="combine_notes" rows="3"><?php echo htmlspecialchars($formCombineNotes, ENT_QUOTES, "UTF-8"); ?></textarea>
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
                        <?php echo htmlspecialchars(uc_format_unit($discipline["unit"] ?? "", $unitAbbrMap), ENT_QUOTES, "UTF-8"); ?>
                      </span>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <?php
            $selectedDisciplinesByCategory = [];
            foreach ($disciplines as $discipline) {
              $discId = (int)$discipline["id"];
              if (!in_array($discId, $formDisciplineIds, true)) {
                continue;
              }
              $category = trim((string)($discipline["category"] ?? ""));
              if ($category === "") {
                $category = "Ohne Kategorie";
              }
              $selectedDisciplinesByCategory[$category][] = $discipline;
            }
          ?>

          <?php if (!empty($selectedDisciplinesByCategory)): ?>
            <div class="field">
              <span>Gewichtungen</span>
              <div class="category-block">
                <?php foreach ($selectedDisciplinesByCategory as $category => $categoryDisciplines): ?>
                  <?php $categoryWeight = $formCategoryWeights[$category] ?? 1; ?>
                  <div class="category-block">
                    <h4 class="category-title"><?php echo htmlspecialchars($category, ENT_QUOTES, "UTF-8"); ?></h4>
                    <label class="field">
                      <input type="number" name="category_weight[]" step="1" min="1" value="<?php echo htmlspecialchars($categoryWeight, ENT_QUOTES, "UTF-8"); ?>">
                      <input type="hidden" name="category_name[]" value="<?php echo htmlspecialchars($category, ENT_QUOTES, "UTF-8"); ?>">
                    </label>
                    <?php if (count($categoryDisciplines) > 1): ?>
                      <div class="check-grid">
                        <?php foreach ($categoryDisciplines as $discipline): ?>
                          <?php
                            $discId = (int)$discipline["id"];
                            $weightValue = $formDisciplineWeights[$discId] ?? 1;
                          ?>
                          <label class="check-item">
                            <span>
                              <?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?>
                            </span>
                            <input type="number" name="discipline_weight[<?php echo $discId; ?>]" step="1" min="1" value="<?php echo htmlspecialchars($weightValue, ENT_QUOTES, "UTF-8"); ?>">
                          </label>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

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

    const radarDataEl = document.getElementById("radar-data");
    const radarCanvas = document.getElementById("radar-chart");
    if (radarDataEl && radarCanvas) {
      const data = JSON.parse(radarDataEl.textContent || "[]");
      if (data.length) {
        const drawRadar = () => {
          const container = radarCanvas.parentElement;
          if (!container) return;
          const rect = container.getBoundingClientRect();
          const size = Math.max(0, Math.floor(rect.width));
          if (!size) return;
          const dpi = window.devicePixelRatio || 1;
          radarCanvas.width = size * dpi;
          radarCanvas.height = size * dpi;
          radarCanvas.style.width = `${size}px`;
          radarCanvas.style.height = `${size}px`;

          const ctx = radarCanvas.getContext("2d");
          ctx.setTransform(dpi, 0, 0, dpi, 0, 0);

          const rootStyles = getComputedStyle(document.documentElement);
          const accent = rootStyles.getPropertyValue("--accent").trim() || "#ff7b4b";
          const accent2 = rootStyles.getPropertyValue("--accent-2").trim() || "#2c2a4a";
          const ink = rootStyles.getPropertyValue("--ink").trim() || "#1f1a14";
          const muted = rootStyles.getPropertyValue("--muted").trim() || "#6f6259";

          const center = size / 2;
          const radius = center - 60;
          const labelOffset = 6;
          const maxValue = 2;
          const midValue = 1;
          const midRatio = 0.4;
          const upperRings = 3;
          const angleStep = (Math.PI * 2) / data.length;

          ctx.clearRect(0, 0, size, size);
          ctx.translate(center, center);

          ctx.strokeStyle = "rgba(44, 42, 74, 0.2)";
          ctx.lineWidth = 1;
          const rings = [midRatio];
          for (let i = 1; i <= upperRings; i += 1) {
            rings.push(midRatio + (i / (upperRings + 1)) * (1 - midRatio));
          }
          rings.push(1);
          rings.forEach((ratio) => {
            const r = radius * ratio;
            ctx.beginPath();
            for (let i = 0; i < data.length; i += 1) {
              const angle = i * angleStep - Math.PI / 2;
              const x = Math.cos(angle) * r;
              const y = Math.sin(angle) * r;
              if (i === 0) {
                ctx.moveTo(x, y);
              } else {
                ctx.lineTo(x, y);
              }
            }
            ctx.closePath();
            ctx.stroke();
          });

          ctx.strokeStyle = "rgba(44, 42, 74, 0.25)";
          for (let i = 0; i < data.length; i += 1) {
            const angle = i * angleStep - Math.PI / 2;
            ctx.beginPath();
            ctx.moveTo(0, 0);
            ctx.lineTo(Math.cos(angle) * radius, Math.sin(angle) * radius);
            ctx.stroke();
          }

          const normalizeValue = (value) => {
            if (value <= midValue) {
              return (value / midValue) * midRatio;
            }
            return midRatio + ((value - midValue) / (maxValue - midValue)) * (1 - midRatio);
          };

          const drawShape = (values, stroke, fill) => {
            ctx.beginPath();
            values.forEach((value, index) => {
              const normalized = Math.max(0, Math.min(normalizeValue(value), 1));
              const angle = index * angleStep - Math.PI / 2;
              const x = Math.cos(angle) * radius * normalized;
              const y = Math.sin(angle) * radius * normalized;
              if (index === 0) {
                ctx.moveTo(x, y);
              } else {
                ctx.lineTo(x, y);
              }
            });
            ctx.closePath();
            ctx.fillStyle = fill;
            ctx.strokeStyle = stroke;
            ctx.lineWidth = 2;
            ctx.fill();
            ctx.stroke();
          };

          const teamValues = data.map((item) => item.team || 0);
          const playerValues = data.map((item) => item.player || 0);
          drawShape(teamValues, accent2, "rgba(44, 42, 74, 0.15)");
          drawShape(playerValues, accent, "rgba(255, 123, 75, 0.22)");

          ctx.fillStyle = ink;
          ctx.font = "12px \"Space Grotesk\", sans-serif";
          data.forEach((item, index) => {
            const angle = index * angleStep - Math.PI / 2;
            const x = Math.cos(angle) * (radius + labelOffset);
            const y = Math.sin(angle) * (radius + labelOffset);
            ctx.textAlign = x > 5 ? "left" : x < -5 ? "right" : "center";
            ctx.textBaseline = y > 5 ? "top" : y < -5 ? "bottom" : "middle";
            ctx.fillStyle = muted;
            ctx.fillText(item.label, x, y);
          });
        };

        const resizeRadar = () => {
          window.requestAnimationFrame(drawRadar);
        };

        drawRadar();
        window.addEventListener("resize", resizeRadar);
      }
    }
  </script></body>
</html>
