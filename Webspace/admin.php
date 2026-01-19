<?php
require_once __DIR__ . "/bootstrap.php";

if (!$pdo) {
  $pageError = $dbError ?? "Datenbank ist nicht erreichbar.";
} else {
  $pageError = null;
}

if (empty($_SESSION["is_admin"])) {
  header("Location: index.php");
  exit;
}

$adminFeedback = null;
$units = [];
$teams = [];

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
    "SELECT unit_name, unit_abbreviation, created_at
     FROM units
     ORDER BY unit_name ASC"
  );
  $stmt->execute();
  $units = $stmt->fetchAll();

  $stmt = $pdo->prepare(
    "SELECT t.id, t.team_name, t.contact,
            COUNT(DISTINCT p.id) AS player_count,
            COUNT(DISTINCT d.id) AS discipline_count,
            COUNT(DISTINCT c.id) AS combine_count
     FROM teams t
     LEFT JOIN players p ON p.team_id = t.id
     LEFT JOIN disciplines d ON d.team_id = t.id
     LEFT JOIN combines c ON c.team_id = t.id
     GROUP BY t.id, t.team_name, t.contact
     ORDER BY t.created_at DESC"
  );
  $stmt->execute();
  $teams = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ultimate Combine – Admin</title>
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
    <form method="post" action="">
      <input type="hidden" name="action" value="logout">
      <button class="pill-button" type="submit">Logout</button>
    </form>
    <div class="brand">
      <img class="brand-logo" src="assets/FrisbeeCatch.png" alt="Ultimate Combine">
      <span class="brand-text">Ultimate Combine</span>
      <span class="brand-sep">•</span>
      <span class="brand-team">Admin</span>
    </div>
    <span></span>
  </header>

  <main class="team">
    <section class="auth-card">
      <h1>Admin-Übersicht</h1>
      <p class="lead">Verwalte Einheiten und behalte Teams im Blick.</p>
      <?php if ($pageError): ?>
        <p class="help"><?php echo htmlspecialchars($pageError, ENT_QUOTES, "UTF-8"); ?></p>
      <?php endif; ?>
    </section>

    <section class="info">
      <h2>Einheiten</h2>
      <?php if (empty($units)): ?>
        <p class="help">Noch keine Einheiten hinterlegt.</p>
      <?php else: ?>
        <ul class="list">
          <?php foreach ($units as $unit): ?>
            <li class="list-item">
              <div>
                <strong><?php echo htmlspecialchars($unit["unit_name"], ENT_QUOTES, "UTF-8"); ?></strong>
                <span class="meta"><?php echo htmlspecialchars($unit["unit_abbreviation"], ENT_QUOTES, "UTF-8"); ?></span>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="info">
      <h2>Teams</h2>
      <?php if (empty($teams)): ?>
        <p class="help">Noch keine Teams registriert.</p>
      <?php else: ?>
        <ul class="list">
          <?php foreach ($teams as $team): ?>
            <li class="list-item">
              <div>
                <strong><?php echo htmlspecialchars($team["team_name"], ENT_QUOTES, "UTF-8"); ?></strong>
                <span class="meta"><?php echo htmlspecialchars($team["contact"] ?? "", ENT_QUOTES, "UTF-8"); ?></span>
              </div>
              <span class="badge">
                <?php
                  $playersCount = (int)($team["player_count"] ?? 0);
                  $disciplinesCount = (int)($team["discipline_count"] ?? 0);
                  $combinesCount = (int)($team["combine_count"] ?? 0);
                  echo htmlspecialchars(
                    $playersCount . " Spieler · " . $disciplinesCount . " Disziplinen · " . $combinesCount . " Combines",
                    ENT_QUOTES,
                    "UTF-8"
                  );
                ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
