<?php
require_once __DIR__ . "/bootstrap.php";

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
$combine = null;
$combineFeedback = null;
$combineError = null;
$players = [];
$disciplines = [];
$assignedPlayerIds = [];
$assignedDisciplineIds = [];
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
    "SELECT id, first_name, last_name, jersey_number, gender
     FROM players
     WHERE team_id = :team_id
     ORDER BY created_at DESC"
  );
  $stmt->execute([":team_id" => $teamId]);
  $players = $stmt->fetchAll();

  $stmt = $pdo->prepare(
    "SELECT id, discipline_name, category, unit
     FROM disciplines
     WHERE team_id = :team_id
     ORDER BY created_at DESC"
  );
  $stmt->execute([":team_id" => $teamId]);
  $disciplines = $stmt->fetchAll();

  if (!$combineError) {
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
      $combineFeedback = "Bitte Name und Datum für das Combine angeben.";
    } elseif (!empty($invalidPlayers) || !empty($invalidDisciplines)) {
      $combineFeedback = "Ungültige Spieler- oder Disziplin-Auswahl.";
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

        $combine["combine_name"] = $combineName;
        $combine["event_date"] = $eventDate;
        $assignedPlayerIds = $selectedPlayers;
        $assignedDisciplineIds = $selectedDisciplines;
        $formCombineName = $combineName;
        $formEventDate = $eventDate;
        $formPlayerIds = $selectedPlayers;
        $formDisciplineIds = $selectedDisciplines;
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
          $pdo->rollBack();
        }
        $combineFeedback = "Combine konnte nicht gespeichert werden.";
      }
    }

    if ($combineFeedback && $combineFeedback !== "Combine wurde aktualisiert.") {
      $formCombineName = $combineName;
      $formEventDate = $eventDate;
      $formPlayerIds = $selectedPlayers;
      $formDisciplineIds = $selectedDisciplines;
    }
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
      <span class="brand-mark">UC</span>
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
      <?php endif; ?>
    </section>

    <?php if (!$pageError && !$combineError): ?>
      <section class="info">
        <h2>Übersicht</h2>
        <div class="info-grid">
          <div class="info-card">
            <h3>Spieler</h3>
            <?php if (empty($assignedPlayerIds)): ?>
              <p class="help">Keine Spieler zugeordnet.</p>
            <?php else: ?>
              <ul class="list">
                <?php foreach ($players as $player): ?>
                  <?php if (in_array((int)$player["id"], $assignedPlayerIds, true)): ?>
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
                  <?php endif; ?>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
          <div class="info-card">
            <h3>Disziplinen</h3>
            <?php if (empty($assignedDisciplineIds)): ?>
              <p class="help">Keine Disziplinen zugeordnet.</p>
            <?php else: ?>
              <ul class="list">
                <?php foreach ($disciplines as $discipline): ?>
                  <?php if (in_array((int)$discipline["id"], $assignedDisciplineIds, true)): ?>
                    <li class="list-item">
                      <div>
                        <strong><?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?></strong>
                        <span class="meta">
                          <?php echo htmlspecialchars($discipline["category"], ENT_QUOTES, "UTF-8"); ?>
                          &middot;
                          <?php echo htmlspecialchars($discipline["unit"], ENT_QUOTES, "UTF-8"); ?>
                        </span>
                      </div>
                    </li>
                  <?php endif; ?>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>
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
</body>
</html>