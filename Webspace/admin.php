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
$adminError = null;
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

  if ($action === "create_unit") {
    $unitName = trim($_POST["unit_name"] ?? "");
    $unitAbbr = trim($_POST["unit_abbreviation"] ?? "");
    if ($unitName === "" || $unitAbbr === "") {
      $adminError = "Bitte Name und Kürzel für die Einheit angeben.";
    } else {
      $stmt = $pdo->prepare(
        "INSERT INTO units (unit_name, unit_abbreviation)
         VALUES (:unit_name, :unit_abbreviation)"
      );
      $stmt->execute([
        ":unit_name" => $unitName,
        ":unit_abbreviation" => $unitAbbr,
      ]);
      $adminFeedback = "Einheit wurde angelegt.";
    }
  }

  if ($action === "update_unit") {
    $unitId = filter_var($_POST["unit_id"] ?? null, FILTER_VALIDATE_INT);
    $unitName = trim($_POST["unit_name"] ?? "");
    $unitAbbr = trim($_POST["unit_abbreviation"] ?? "");
    if (!$unitId || $unitName === "" || $unitAbbr === "") {
      $adminError = "Bitte Name und Kürzel für die Einheit angeben.";
    } else {
      $stmt = $pdo->prepare(
        "UPDATE units
         SET unit_name = :unit_name,
             unit_abbreviation = :unit_abbreviation
         WHERE id = :id"
      );
      $stmt->execute([
        ":unit_name" => $unitName,
        ":unit_abbreviation" => $unitAbbr,
        ":id" => $unitId,
      ]);
      $adminFeedback = "Einheit wurde aktualisiert.";
    }
  }

  if ($action === "update_units" && !empty($_POST["delete_unit_id"])) {
    $unitId = filter_var($_POST["delete_unit_id"], FILTER_VALIDATE_INT);
    if (!$unitId) {
      $adminError = "Einheit konnte nicht gelöscht werden.";
    } else {
      $stmt = $pdo->prepare("DELETE FROM units WHERE id = :id");
      $stmt->execute([":id" => $unitId]);
      $adminFeedback = "Einheit wurde gelöscht.";
    }
  }

  if ($action === "update_units" && empty($_POST["delete_unit_id"])) {
    $unitIds = (array)($_POST["unit_id"] ?? []);
    $unitNames = (array)($_POST["unit_name"] ?? []);
    $unitAbbrs = (array)($_POST["unit_abbreviation"] ?? []);
    $hasError = false;

    foreach ($unitIds as $index => $unitIdRaw) {
      $unitId = filter_var($unitIdRaw, FILTER_VALIDATE_INT);
      $unitName = trim((string)($unitNames[$index] ?? ""));
      $unitAbbr = trim((string)($unitAbbrs[$index] ?? ""));
      if (!$unitId || $unitName === "" || $unitAbbr === "") {
        $hasError = true;
        break;
      }
    }

    if ($hasError) {
      $adminError = "Bitte Name und Kürzel für alle Einheiten angeben.";
    } else {
      $stmt = $pdo->prepare(
        "UPDATE units
         SET unit_name = :unit_name,
             unit_abbreviation = :unit_abbreviation
         WHERE id = :id"
      );
      foreach ($unitIds as $index => $unitIdRaw) {
        $unitId = (int)$unitIdRaw;
        $unitName = trim((string)($unitNames[$index] ?? ""));
        $unitAbbr = trim((string)($unitAbbrs[$index] ?? ""));
        $stmt->execute([
          ":unit_name" => $unitName,
          ":unit_abbreviation" => $unitAbbr,
          ":id" => $unitId,
        ]);
      }
      $adminFeedback = "Einheiten wurden aktualisiert.";
    }
  }

  if ($action === "delete_unit") {
    $unitId = filter_var($_POST["unit_id"] ?? null, FILTER_VALIDATE_INT);
    if (!$unitId) {
      $adminError = "Einheit konnte nicht gelöscht werden.";
    } else {
      $stmt = $pdo->prepare("DELETE FROM units WHERE id = :id");
      $stmt->execute([":id" => $unitId]);
      $adminFeedback = "Einheit wurde gelöscht.";
    }
  }
}

if (!$pageError) {
  $stmt = $pdo->prepare(
    "SELECT id, unit_name, unit_abbreviation, created_at
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
      <?php if ($adminError): ?>
        <p class="help"><?php echo htmlspecialchars($adminError, ENT_QUOTES, "UTF-8"); ?></p>
      <?php elseif ($adminFeedback): ?>
        <p class="help"><?php echo htmlspecialchars($adminFeedback, ENT_QUOTES, "UTF-8"); ?></p>
      <?php endif; ?>
    </section>

    <section class="info">
      <div class="card-header">
        <h2>Einheiten</h2>
        <div class="card-actions" id="units-actions-view">
          <button class="pill-button" type="button" data-edit-units>Bearbeiten</button>
          <button class="icon-button small js-toggle" type="button" data-target="add-unit" aria-expanded="false" aria-controls="add-unit">+</button>
        </div>
        <div class="card-actions is-hidden" id="units-actions-edit">
          <button class="pill-button" type="button" data-edit-units-cancel>Abbrechen</button>
          <button class="primary-button" type="submit" form="unit-edit-form">Speichern</button>
        </div>
      </div>

      <div id="add-unit" class="is-hidden">
        <form class="form" method="post" action="">
          <input type="hidden" name="action" value="create_unit">
          <label class="field">
            <span>Name</span>
            <input type="text" name="unit_name" placeholder="z. B. Meter" required>
          </label>
          <label class="field">
            <span>Kürzel</span>
            <input type="text" name="unit_abbreviation" placeholder="z. B. m" required>
          </label>
          <button class="primary-button" type="submit">Einheit anlegen</button>
        </form>
      </div>

      <?php if (empty($units)): ?>
        <p class="help">Noch keine Einheiten hinterlegt.</p>
      <?php else: ?>
        <ul class="list" id="units-overview">
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

      <div id="edit-units" class="is-hidden">
        <?php if (!empty($units)): ?>
          <form id="unit-edit-form" class="form" method="post" action="">
            <input type="hidden" name="action" value="update_units">
            <ul class="list">
              <?php foreach ($units as $unit): ?>
                <li class="list-item list-item--edit">
                  <div>
                    <div class="form inline-form">
                      <input type="hidden" name="unit_id[]" value="<?php echo (int)$unit["id"]; ?>">
                      <label class="field">
                        <span>Name</span>
                        <input type="text" name="unit_name[]" value="<?php echo htmlspecialchars($unit["unit_name"], ENT_QUOTES, "UTF-8"); ?>" required>
                      </label>
                      <label class="field">
                        <span>Kürzel</span>
                        <input type="text" name="unit_abbreviation[]" value="<?php echo htmlspecialchars($unit["unit_abbreviation"], ENT_QUOTES, "UTF-8"); ?>" required>
                      </label>
                    </div>
                  </div>
                  <button class="pill-button" type="submit" name="delete_unit_id" value="<?php echo (int)$unit["id"]; ?>" formnovalidate>Löschen</button>
                </li>
              <?php endforeach; ?>
            </ul>
          </form>
        <?php endif; ?>
      </div>
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
  <script>
    const toggles = document.querySelectorAll(".js-toggle");
    toggles.forEach((btn) => {
      btn.addEventListener("click", () => {
        const targetId = btn.dataset.target;
        const target = document.getElementById(targetId);
        if (!target) return;
        const isHidden = target.classList.toggle("is-hidden");
        btn.setAttribute("aria-expanded", String(!isHidden));
        if (!isHidden) {
          target.scrollIntoView({ behavior: "smooth", block: "start" });
        }
      });
    });

    const editButton = document.querySelector("[data-edit-units]");
    const cancelButton = document.querySelector("[data-edit-units-cancel]");
    const editPanel = document.getElementById("edit-units");
    const overviewList = document.getElementById("units-overview");
    const addUnitPanel = document.getElementById("add-unit");
    const viewActions = document.getElementById("units-actions-view");
    const editActions = document.getElementById("units-actions-edit");

    const setEditMode = (isEdit) => {
      const show = isEdit ? "true" : "false";
      if (editPanel) editPanel.classList.toggle("is-hidden", !isEdit);
      if (overviewList) overviewList.classList.toggle("is-hidden", isEdit);
      if (addUnitPanel) addUnitPanel.classList.toggle("is-hidden", true);
      if (viewActions) viewActions.classList.toggle("is-hidden", isEdit);
      if (editActions) editActions.classList.toggle("is-hidden", !isEdit);
      if (editButton) editButton.setAttribute("aria-expanded", show);
      if (cancelButton) cancelButton.setAttribute("aria-expanded", show);
    };

    if (editButton) {
      editButton.addEventListener("click", () => setEditMode(true));
    }
    if (cancelButton) {
      cancelButton.addEventListener("click", () => setEditMode(false));
    }
  </script>
</body>
</html>
