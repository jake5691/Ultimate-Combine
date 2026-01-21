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
$globalDisciplines = [];
$teams = [];
$validDirections = [
  "more" => "Mehr ist besser",
  "less" => "Weniger ist besser",
];

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

  if ($action === "create_global_discipline") {
    $disciplineName = trim($_POST["discipline_name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $unit = trim($_POST["unit"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $direction = $_POST["rating_direction"] ?? "";
    $expectedMinRaw = trim($_POST["expected_min"] ?? "");
    $expectedMaxRaw = trim($_POST["expected_max"] ?? "");
    $expectedMin = $expectedMinRaw === "" ? null : filter_var($expectedMinRaw, FILTER_VALIDATE_FLOAT);
    $expectedMax = $expectedMaxRaw === "" ? null : filter_var($expectedMaxRaw, FILTER_VALIDATE_FLOAT);

    if (
      $disciplineName === "" ||
      $description === "" ||
      $unit === "" ||
      $category === "" ||
      !isset($validDirections[$direction]) ||
      ($expectedMinRaw !== "" && $expectedMin === false) ||
      ($expectedMaxRaw !== "" && $expectedMax === false) ||
      (($expectedMin !== null || $expectedMax !== null) && ($expectedMin === null || $expectedMax === null)) ||
      ($expectedMin !== null && $expectedMax !== null && $expectedMin >= $expectedMax)
    ) {
      $adminError = "Bitte alle Felder für die Disziplin ausfüllen.";
    } else {
      try {
        $stmt = $pdo->prepare(
          "SELECT 1
           FROM disciplines
           WHERE team_id IS NULL
             AND discipline_name = :discipline_name
           LIMIT 1"
        );
        $stmt->execute([":discipline_name" => $disciplineName]);
        $exists = (bool)$stmt->fetchColumn();

        if ($exists) {
          $adminError = "Diese Disziplin existiert bereits.";
        } else {
          $stmt = $pdo->prepare(
            "INSERT INTO disciplines (team_id, discipline_name, description, unit, category, rating_direction, expected_min, expected_max)
             VALUES (NULL, :discipline_name, :description, :unit, :category, :rating_direction, :expected_min, :expected_max)"
          );
          $stmt->execute([
            ":discipline_name" => $disciplineName,
            ":description" => $description,
            ":unit" => $unit,
            ":category" => $category,
            ":rating_direction" => $direction,
            ":expected_min" => $expectedMin,
            ":expected_max" => $expectedMax,
          ]);
          $adminFeedback = "Disziplin wurde angelegt.";
        }
      } catch (Throwable $e) {
        $adminError = "Disziplin konnte nicht angelegt werden. Bitte Schema prüfen: ALTER TABLE disciplines MODIFY COLUMN team_id INT NULL;";
      }
    }
  }

  if ($action === "update_global_disciplines" && !empty($_POST["delete_discipline_id"])) {
    $disciplineId = filter_var($_POST["delete_discipline_id"], FILTER_VALIDATE_INT);
    if (!$disciplineId) {
      $adminError = "Disziplin konnte nicht gelöscht werden.";
    } else {
      try {
        $stmt = $pdo->prepare("DELETE FROM disciplines WHERE id = :id AND team_id IS NULL");
        $stmt->execute([":id" => $disciplineId]);
        $adminFeedback = "Disziplin wurde gelöscht.";
      } catch (Throwable $e) {
        $adminError = "Disziplin konnte nicht gelöscht werden.";
      }
    }
  }

  if ($action === "update_global_disciplines" && empty($_POST["delete_discipline_id"])) {
    $disciplineIds = (array)($_POST["discipline_id"] ?? []);
    $disciplineNames = (array)($_POST["discipline_name"] ?? []);
    $descriptions = (array)($_POST["description"] ?? []);
    $unitsInput = (array)($_POST["unit"] ?? []);
    $categories = (array)($_POST["category"] ?? []);
    $directions = (array)($_POST["rating_direction"] ?? []);
    $expectedMins = (array)($_POST["expected_min"] ?? []);
    $expectedMaxs = (array)($_POST["expected_max"] ?? []);
    $hasError = false;

    foreach ($disciplineIds as $index => $disciplineIdRaw) {
      $disciplineId = filter_var($disciplineIdRaw, FILTER_VALIDATE_INT);
      $disciplineName = trim((string)($disciplineNames[$index] ?? ""));
      $description = trim((string)($descriptions[$index] ?? ""));
      $unit = trim((string)($unitsInput[$index] ?? ""));
      $category = trim((string)($categories[$index] ?? ""));
      $direction = $directions[$index] ?? "";
      $expectedMinRaw = trim((string)($expectedMins[$index] ?? ""));
      $expectedMaxRaw = trim((string)($expectedMaxs[$index] ?? ""));
      $expectedMin = $expectedMinRaw === "" ? null : filter_var($expectedMinRaw, FILTER_VALIDATE_FLOAT);
      $expectedMax = $expectedMaxRaw === "" ? null : filter_var($expectedMaxRaw, FILTER_VALIDATE_FLOAT);
      if (
        !$disciplineId ||
        $disciplineName === "" ||
        $description === "" ||
        $unit === "" ||
        $category === "" ||
        !isset($validDirections[$direction]) ||
        ($expectedMinRaw !== "" && $expectedMin === false) ||
        ($expectedMaxRaw !== "" && $expectedMax === false) ||
        (($expectedMin !== null || $expectedMax !== null) && ($expectedMin === null || $expectedMax === null)) ||
        ($expectedMin !== null && $expectedMax !== null && $expectedMin >= $expectedMax)
      ) {
        $hasError = true;
        break;
      }
    }

    if ($hasError) {
      $adminError = "Bitte alle Felder für die Disziplinen ausfüllen.";
    } else {
      try {
        $stmt = $pdo->prepare(
          "UPDATE disciplines
           SET discipline_name = :discipline_name,
               description = :description,
               unit = :unit,
               category = :category,
               rating_direction = :rating_direction,
               expected_min = :expected_min,
               expected_max = :expected_max
           WHERE id = :id AND team_id IS NULL"
        );
        foreach ($disciplineIds as $index => $disciplineIdRaw) {
          $expectedMinRaw = trim((string)($expectedMins[$index] ?? ""));
          $expectedMaxRaw = trim((string)($expectedMaxs[$index] ?? ""));
          $expectedMin = $expectedMinRaw === "" ? null : (float)str_replace(",", ".", $expectedMinRaw);
          $expectedMax = $expectedMaxRaw === "" ? null : (float)str_replace(",", ".", $expectedMaxRaw);
          $stmt->execute([
            ":discipline_name" => trim((string)($disciplineNames[$index] ?? "")),
            ":description" => trim((string)($descriptions[$index] ?? "")),
            ":unit" => trim((string)($unitsInput[$index] ?? "")),
            ":category" => trim((string)($categories[$index] ?? "")),
            ":rating_direction" => $directions[$index] ?? "",
            ":expected_min" => $expectedMin,
            ":expected_max" => $expectedMax,
            ":id" => (int)$disciplineIdRaw,
          ]);
        }
        $adminFeedback = "Disziplinen wurden aktualisiert.";
      } catch (Throwable $e) {
        $adminError = "Disziplinen konnten nicht gespeichert werden.";
      }
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
    "SELECT id, discipline_name, description, unit, category, rating_direction, expected_min, expected_max, created_at
     FROM disciplines
     WHERE team_id IS NULL
     ORDER BY created_at DESC"
  );
  $stmt->execute();
  $globalDisciplines = $stmt->fetchAll();

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
      <button class="pill-button is-logout" type="submit">Abmelden</button>
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
        <div class="card-actions is-hidden" id="units-actions-edit" hidden>
          <button class="pill-button" type="button" data-edit-units-cancel>Abbrechen</button>
          <button class="primary-button" type="submit" form="unit-edit-form">Speichern</button>
        </div>
      </div>

      <div id="add-unit" class="is-hidden" hidden>
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

      <div id="edit-units" class="is-hidden" hidden>
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
      <div class="card-header">
        <h2>Globale Disziplinen</h2>
        <div class="card-actions" id="disciplines-actions-view">
          <button class="pill-button" type="button" data-edit-disciplines>Bearbeiten</button>
          <button class="icon-button small js-toggle" type="button" data-target="add-global-discipline" aria-expanded="false" aria-controls="add-global-discipline">+</button>
        </div>
        <div class="card-actions is-hidden" id="disciplines-actions-edit" hidden>
          <button class="pill-button" type="button" data-edit-disciplines-cancel>Abbrechen</button>
          <button class="primary-button" type="submit" form="discipline-edit-form">Speichern</button>
        </div>
      </div>

      <div id="add-global-discipline" class="is-hidden" hidden>
        <form class="form" method="post" action="">
          <input type="hidden" name="action" value="create_global_discipline">
          <label class="field">
            <span>Name</span>
            <input type="text" name="discipline_name" required>
          </label>
          <label class="field">
            <span>Beschreibung</span>
            <textarea name="description" rows="3" required></textarea>
          </label>
          <label class="field">
            <span>Einheit</span>
            <input type="text" name="unit" list="admin-unit-options" required>
          </label>
          <label class="field">
            <span>Kategorie</span>
            <input type="text" name="category" required>
          </label>
        <label class="field">
          <span>Bewertung</span>
          <select name="rating_direction" required>
            <option value="">Bitte wählen</option>
            <?php foreach ($validDirections as $key => $label): ?>
              <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, "UTF-8"); ?>">
                <?php echo htmlspecialchars($label, ENT_QUOTES, "UTF-8"); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field">
          <span>Erwartung Minimum (1 Punkt)</span>
          <input type="number" name="expected_min" step="0.1" placeholder="Optional">
        </label>
        <label class="field">
          <span>Erwartung Maximum (2 Punkte)</span>
          <input type="number" name="expected_max" step="0.1" placeholder="Optional">
        </label>
        <button class="primary-button" type="submit">Disziplin anlegen</button>
      </form>
      </div>

      <datalist id="admin-unit-options">
        <?php foreach ($units as $unit): ?>
          <?php
            $unitName = trim((string)($unit["unit_name"] ?? ""));
            $unitAbbr = trim((string)($unit["unit_abbreviation"] ?? ""));
            $unitLabel = $unitName;
            if ($unitAbbr !== "") {
              $unitLabel .= " (" . $unitAbbr . ")";
            }
          ?>
          <option value="<?php echo htmlspecialchars($unitLabel, ENT_QUOTES, "UTF-8"); ?>"></option>
        <?php endforeach; ?>
      </datalist>

      <?php if (empty($globalDisciplines)): ?>
        <p class="help">Noch keine globalen Disziplinen hinterlegt.</p>
      <?php else: ?>
        <ul class="list" id="disciplines-overview">
          <?php foreach ($globalDisciplines as $discipline): ?>
            <li class="list-item">
              <div>
                <strong><?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?></strong>
                <span class="meta">
                  <?php echo htmlspecialchars($discipline["unit"], ENT_QUOTES, "UTF-8"); ?>
                  &middot;
                  <?php echo htmlspecialchars($discipline["category"], ENT_QUOTES, "UTF-8"); ?>
                </span>
                <div class="detail"><?php echo htmlspecialchars($discipline["description"], ENT_QUOTES, "UTF-8"); ?></div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <div id="edit-disciplines" class="is-hidden" hidden>
        <?php if (!empty($globalDisciplines)): ?>
          <form id="discipline-edit-form" class="form" method="post" action="">
            <input type="hidden" name="action" value="update_global_disciplines">
            <ul class="list">
              <?php foreach ($globalDisciplines as $discipline): ?>
                <li class="list-item list-item--edit">
                  <div>
                    <div class="form inline-form">
                      <input type="hidden" name="discipline_id[]" value="<?php echo (int)$discipline["id"]; ?>">
                      <label class="field">
                        <span>Name</span>
                        <input type="text" name="discipline_name[]" value="<?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?>" required>
                      </label>
                      <label class="field">
                        <span>Beschreibung</span>
                        <textarea name="description[]" rows="3" required><?php echo htmlspecialchars($discipline["description"], ENT_QUOTES, "UTF-8"); ?></textarea>
                      </label>
                      <label class="field">
                        <span>Einheit</span>
                        <input type="text" name="unit[]" list="admin-unit-options" value="<?php echo htmlspecialchars($discipline["unit"], ENT_QUOTES, "UTF-8"); ?>" required>
                      </label>
                      <label class="field">
                        <span>Kategorie</span>
                        <input type="text" name="category[]" value="<?php echo htmlspecialchars($discipline["category"], ENT_QUOTES, "UTF-8"); ?>" required>
                      </label>
                      <label class="field">
                        <span>Bewertung</span>
                        <select name="rating_direction[]" required>
                          <?php foreach ($validDirections as $key => $label): ?>
                            <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, "UTF-8"); ?>"<?php echo ($discipline["rating_direction"] ?? "") === $key ? " selected" : ""; ?>>
                              <?php echo htmlspecialchars($label, ENT_QUOTES, "UTF-8"); ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </label>
                      <label class="field">
                        <span>Erwartung Minimum (1 Punkt)</span>
                        <input type="number" name="expected_min[]" step="0.1" value="<?php echo htmlspecialchars($discipline["expected_min"] ?? "", ENT_QUOTES, "UTF-8"); ?>" placeholder="Optional">
                      </label>
                      <label class="field">
                        <span>Erwartung Maximum (2 Punkte)</span>
                        <input type="number" name="expected_max[]" step="0.1" value="<?php echo htmlspecialchars($discipline["expected_max"] ?? "", ENT_QUOTES, "UTF-8"); ?>" placeholder="Optional">
                      </label>
                    </div>
                  </div>
                  <button class="pill-button" type="submit" name="delete_discipline_id" value="<?php echo (int)$discipline["id"]; ?>" formnovalidate>Löschen</button>
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
        target.hidden = isHidden;
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
      if (editPanel) {
        editPanel.classList.toggle("is-hidden", !isEdit);
        editPanel.hidden = !isEdit;
      }
      if (overviewList) overviewList.classList.toggle("is-hidden", isEdit);
      if (addUnitPanel) {
        addUnitPanel.classList.toggle("is-hidden", true);
        addUnitPanel.hidden = true;
      }
      if (viewActions) viewActions.classList.toggle("is-hidden", isEdit);
      if (editActions) {
        editActions.classList.toggle("is-hidden", !isEdit);
        editActions.hidden = !isEdit;
      }
      if (editButton) editButton.setAttribute("aria-expanded", show);
      if (cancelButton) cancelButton.setAttribute("aria-expanded", show);
    };

    if (editButton) {
      editButton.addEventListener("click", () => setEditMode(true));
    }
    if (cancelButton) {
      cancelButton.addEventListener("click", () => setEditMode(false));
    }

    const editDisciplinesButton = document.querySelector("[data-edit-disciplines]");
    const cancelDisciplinesButton = document.querySelector("[data-edit-disciplines-cancel]");
    const editDisciplinesPanel = document.getElementById("edit-disciplines");
    const disciplinesOverview = document.getElementById("disciplines-overview");
    const addDisciplinePanel = document.getElementById("add-global-discipline");
    const disciplinesViewActions = document.getElementById("disciplines-actions-view");
    const disciplinesEditActions = document.getElementById("disciplines-actions-edit");

    const setDisciplinesEditMode = (isEdit) => {
      const show = isEdit ? "true" : "false";
      if (editDisciplinesPanel) {
        editDisciplinesPanel.classList.toggle("is-hidden", !isEdit);
        editDisciplinesPanel.hidden = !isEdit;
      }
      if (disciplinesOverview) disciplinesOverview.classList.toggle("is-hidden", isEdit);
      if (addDisciplinePanel) {
        addDisciplinePanel.classList.toggle("is-hidden", true);
        addDisciplinePanel.hidden = true;
      }
      if (disciplinesViewActions) disciplinesViewActions.classList.toggle("is-hidden", isEdit);
      if (disciplinesEditActions) {
        disciplinesEditActions.classList.toggle("is-hidden", !isEdit);
        disciplinesEditActions.hidden = !isEdit;
      }
      if (editDisciplinesButton) editDisciplinesButton.setAttribute("aria-expanded", show);
      if (cancelDisciplinesButton) cancelDisciplinesButton.setAttribute("aria-expanded", show);
    };

    if (editDisciplinesButton) {
      editDisciplinesButton.addEventListener("click", () => setDisciplinesEditMode(true));
    }
    if (cancelDisciplinesButton) {
      cancelDisciplinesButton.addEventListener("click", () => setDisciplinesEditMode(false));
    }
  </script>
</body>
</html>
