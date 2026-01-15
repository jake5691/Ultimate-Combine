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

$playerFeedback = null;
$combineFeedback = null;
$validGenders = [
  "m" => "Maennlich",
  "w" => "Weiblich",
  "d" => "Divers",
];

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$pageError) {
  $action = $_POST["action"] ?? "";

  if ($action === "logout") {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
  }

  if ($action === "create_player") {
    $firstName = trim($_POST["first_name"] ?? "");
    $lastName = trim($_POST["last_name"] ?? "");
    $jerseyRaw = trim($_POST["jersey_number"] ?? "");
    $gender = $_POST["gender"] ?? "";

    $jerseyNumber = filter_var($jerseyRaw, FILTER_VALIDATE_INT);

    if ($firstName === "" || $lastName === "" || $jerseyNumber === false || !isset($validGenders[$gender])) {
      $playerFeedback = "Bitte alle Felder fuer den Spieler korrekt ausfuellen.";
    } else {
      $stmt = $pdo->prepare(
        "INSERT INTO players (team_id, first_name, last_name, jersey_number, gender)
         VALUES (:team_id, :first_name, :last_name, :jersey_number, :gender)"
      );
      $stmt->execute([
        ":team_id" => $teamId,
        ":first_name" => $firstName,
        ":last_name" => $lastName,
        ":jersey_number" => $jerseyNumber,
        ":gender" => $gender,
      ]);
      $playerFeedback = "Spieler wurde angelegt.";
    }
  }

  if ($action === "create_combine") {
    $combineName = trim($_POST["combine_name"] ?? "");
    $eventDate = trim($_POST["event_date"] ?? "");
    $eventDate = $eventDate !== "" ? $eventDate : null;

    if ($combineName === "") {
      $combineFeedback = "Bitte einen Namen fuer das Combine angeben.";
    } else {
      $stmt = $pdo->prepare(
        "INSERT INTO combines (team_id, combine_name, event_date)
         VALUES (:team_id, :combine_name, :event_date)"
      );
      $stmt->execute([
        ":team_id" => $teamId,
        ":combine_name" => $combineName,
        ":event_date" => $eventDate,
      ]);
      $combineFeedback = "Combine wurde angelegt.";
    }
  }
}

$players = [];
$combines = [];

if (!$pageError) {
  $stmt = $pdo->prepare(
    "SELECT first_name, last_name, jersey_number, gender, created_at
     FROM players
     WHERE team_id = :team_id
     ORDER BY created_at DESC"
  );
  $stmt->execute([":team_id" => $teamId]);
  $players = $stmt->fetchAll();

  $stmt = $pdo->prepare(
    "SELECT combine_name, event_date, created_at
     FROM combines
     WHERE team_id = :team_id
     ORDER BY created_at DESC"
  );
  $stmt->execute([":team_id" => $teamId]);
  $combines = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ultimate Combine – Team</title>
  <link rel="stylesheet" href="ui.css">
</head>
<body>
  <div class="bg-grid"></div>

  <header class="topbar">
    <form method="post" action="">
      <input type="hidden" name="action" value="logout">
      <button class="pill-button" type="submit">Logout</button>
    </form>
    <div class="brand">
      <span class="brand-mark">UC</span>
      <span class="brand-text">Ultimate Combine</span>
    </div>
    <span class="pill-button"><?php echo htmlspecialchars($teamName, ENT_QUOTES, "UTF-8"); ?></span>
  </header>

  <main class="team">
    <section class="auth-card">
      <h1>Team-Übersicht</h1>
      <p class="lead">Verwalte Spieler und Combines für dein Team.</p>
      <?php if ($pageError): ?>
        <p class="help"><?php echo htmlspecialchars($pageError, ENT_QUOTES, "UTF-8"); ?></p>
      <?php endif; ?>
    </section>

    <section class="team-grid">
      <div class="auth-card">
        <h2>Spieler anlegen</h2>
        <form class="form" method="post" action="">
          <input type="hidden" name="action" value="create_player">
          <label class="field">
            <span>Vorname</span>
            <input type="text" name="first_name" required>
          </label>
          <label class="field">
            <span>Nachname</span>
            <input type="text" name="last_name" required>
          </label>
          <label class="field">
            <span>Trikotnummer</span>
            <input type="number" name="jersey_number" min="0" required>
          </label>
          <label class="field">
            <span>Geschlecht</span>
            <select name="gender" required>
              <option value="">Bitte wählen</option>
              <option value="m">Männlich</option>
              <option value="w">Weiblich</option>
              <option value="d">Divers</option>
            </select>
          </label>
          <button class="primary-button" type="submit">Spieler speichern</button>
          <?php if ($playerFeedback): ?>
            <p class="help"><?php echo htmlspecialchars($playerFeedback, ENT_QUOTES, "UTF-8"); ?></p>
          <?php endif; ?>
        </form>
      </div>

      <div class="auth-card">
        <h2>Combine anlegen</h2>
        <form class="form" method="post" action="">
          <input type="hidden" name="action" value="create_combine">
          <label class="field">
            <span>Name</span>
            <input type="text" name="combine_name" required>
          </label>
          <label class="field">
            <span>Datum</span>
            <input type="date" name="event_date">
          </label>
          <button class="primary-button" type="submit">Combine speichern</button>
          <?php if ($combineFeedback): ?>
            <p class="help"><?php echo htmlspecialchars($combineFeedback, ENT_QUOTES, "UTF-8"); ?></p>
          <?php endif; ?>
        </form>
      </div>
    </section>

    <section class="info">
      <h2>Bestehende Daten</h2>
      <div class="info-grid">
        <div class="info-card">
          <h3>Spieler</h3>
          <?php if (empty($players)): ?>
            <p class="help">Noch keine Spieler angelegt.</p>
          <?php else: ?>
            <ul class="list">
              <?php foreach ($players as $player): ?>
                <li class="list-item">
                  <div>
                    <strong><?php echo htmlspecialchars($player["first_name"], ENT_QUOTES, "UTF-8"); ?></strong>
                    <?php echo " " . htmlspecialchars($player["last_name"], ENT_QUOTES, "UTF-8"); ?>
                    <span class="meta">
                      <?php echo htmlspecialchars($validGenders[$player["gender"]] ?? $player["gender"], ENT_QUOTES, "UTF-8"); ?>
                    </span>
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
          <h3>Combines</h3>
          <?php if (empty($combines)): ?>
            <p class="help">Noch keine Combines angelegt.</p>
          <?php else: ?>
            <ul class="list">
              <?php foreach ($combines as $combine): ?>
                <li class="list-item">
                  <div>
                    <strong><?php echo htmlspecialchars($combine["combine_name"], ENT_QUOTES, "UTF-8"); ?></strong>
                    <?php if (!empty($combine["event_date"])): ?>
                      <span class="meta"><?php echo htmlspecialchars($combine["event_date"], ENT_QUOTES, "UTF-8"); ?></span>
                    <?php endif; ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </main>
</body>
</html>
