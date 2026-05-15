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
$feedbackEntries = [];
$teams = [];
$teamCombinesByTeam = [];
$broadcastSubject = "";
$broadcastMessage = "";
$validDirections = [
  "more" => t("common.more_is_better", "Mehr ist besser"),
  "less" => t("common.less_is_better", "Weniger ist besser"),
];
$feedbackStatuses = ["Neu", "Todo", "Done", "Abgelehnt"];
$feedbackFilter = $_GET["feedback_status"] ?? "all";
if ($feedbackFilter !== "all" && !in_array($feedbackFilter, $feedbackStatuses, true)) {
  $feedbackFilter = "all";
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$pageError) {
  $action = $_POST["action"] ?? "";
  if ($action === "logout") {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
  }

  if ($action === "send_broadcast_email") {
    $broadcastSubject = trim($_POST["broadcast_subject"] ?? "");
    $broadcastMessage = trim($_POST["broadcast_message"] ?? "");
    if ($broadcastSubject === "" || $broadcastMessage === "") {
      $adminError = t("admin.mail.error.required", "Bitte Betreff und Nachricht angeben.");
    } else {
      $stmt = $pdo->prepare("SELECT contact FROM teams WHERE contact IS NOT NULL AND contact <> ''");
      $stmt->execute();
      $contacts = $stmt->fetchAll(PDO::FETCH_COLUMN);
      $recipients = [];
      foreach ($contacts as $contact) {
        $email = trim((string)$contact);
        if ($email !== "" && filter_var($email, FILTER_VALIDATE_EMAIL)) {
          $recipients[strtolower($email)] = $email;
        }
      }

      if (empty($recipients)) {
        $adminError = t("admin.mail.error.no_recipients", "Keine gültigen Empfänger gefunden.");
      } else {
        $sent = 0;
        $failed = 0;
        foreach ($recipients as $email) {
          $mailError = null;
          if (uc_smtp_send($env, $email, $broadcastSubject, $broadcastMessage, $mailError)) {
            $sent++;
          } else {
            $failed++;
          }
        }
        if ($sent === 0) {
          $adminError = t("admin.mail.error.send_failed", "Mailversand fehlgeschlagen.");
        } elseif ($failed > 0) {
          $adminError = sprintf(
            t("admin.mail.error.partial", "Mail teilweise gesendet (%d erfolgreich, %d fehlgeschlagen)."),
            $sent,
            $failed
          );
        } else {
          $adminFeedback = sprintf(
            t("admin.mail.feedback.sent", "Mail wurde an %d Empfänger gesendet."),
            $sent
          );
          $broadcastSubject = "";
          $broadcastMessage = "";
        }
      }
    }
  }

  if ($action === "create_unit") {
    $unitName = trim($_POST["unit_name"] ?? "");
    $unitAbbr = trim($_POST["unit_abbreviation"] ?? "");
    if ($unitName === "" || $unitAbbr === "") {
      $adminError = t("admin.error.unit_name_required", "Bitte Name und Kürzel für die Einheit angeben.");
    } else {
      $stmt = $pdo->prepare(
        "INSERT INTO units (unit_name, unit_abbreviation)
         VALUES (:unit_name, :unit_abbreviation)"
      );
      $stmt->execute([
        ":unit_name" => $unitName,
        ":unit_abbreviation" => $unitAbbr,
      ]);
      $adminFeedback = t("admin.feedback.unit_created", "Einheit wurde angelegt.");
    }
  }

  if ($action === "update_unit") {
    $unitId = filter_var($_POST["unit_id"] ?? null, FILTER_VALIDATE_INT);
    $unitName = trim($_POST["unit_name"] ?? "");
    $unitAbbr = trim($_POST["unit_abbreviation"] ?? "");
    if (!$unitId || $unitName === "" || $unitAbbr === "") {
      $adminError = t("admin.error.unit_name_required", "Bitte Name und Kürzel für die Einheit angeben.");
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
      $adminFeedback = t("admin.feedback.unit_updated", "Einheit wurde aktualisiert.");
    }
  }

  if ($action === "update_units" && !empty($_POST["delete_unit_id"])) {
    $unitId = filter_var($_POST["delete_unit_id"], FILTER_VALIDATE_INT);
    if (!$unitId) {
      $adminError = t("admin.error.unit_delete_failed", "Einheit konnte nicht gelöscht werden.");
    } else {
      $stmt = $pdo->prepare("DELETE FROM units WHERE id = :id");
      $stmt->execute([":id" => $unitId]);
      $adminFeedback = t("admin.feedback.unit_deleted", "Einheit wurde gelöscht.");
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
      $adminError = t("admin.error.units_required", "Bitte Name und Kürzel für alle Einheiten angeben.");
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
      $adminFeedback = t("admin.feedback.units_updated", "Einheiten wurden aktualisiert.");
    }
  }

  if ($action === "delete_unit") {
    $unitId = filter_var($_POST["unit_id"] ?? null, FILTER_VALIDATE_INT);
    if (!$unitId) {
      $adminError = t("admin.error.unit_delete_failed", "Einheit konnte nicht gelöscht werden.");
    } else {
      $stmt = $pdo->prepare("DELETE FROM units WHERE id = :id");
      $stmt->execute([":id" => $unitId]);
      $adminFeedback = t("admin.feedback.unit_deleted", "Einheit wurde gelöscht.");
    }
  }

  if ($action === "create_global_discipline") {
    $disciplineName = trim($_POST["discipline_name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $unit = trim($_POST["unit"] ?? "");
    $unitAbbrRaw = trim($_POST["unit_abbreviation"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $direction = $_POST["rating_direction"] ?? "";
    $expectedMinRaw = trim($_POST["expected_min"] ?? "");
    $expectedMaxRaw = trim($_POST["expected_max"] ?? "");
    $bonusRelRaw = trim($_POST["bonus_relative"] ?? "");
    $bonusAbsRaw = trim($_POST["bonus_absolute"] ?? "");
    if ($unitAbbrRaw === "" && preg_match('/^(.+?)\s*\(([^)]+)\)\s*$/', $unit, $matches)) {
      $unit = trim($matches[1]);
      $unitAbbrRaw = trim($matches[2]);
    }
    $expectedMin = $expectedMinRaw === "" ? null : filter_var($expectedMinRaw, FILTER_VALIDATE_FLOAT);
    $expectedMax = $expectedMaxRaw === "" ? null : filter_var($expectedMaxRaw, FILTER_VALIDATE_FLOAT);
    $bonusRel = $bonusRelRaw === "" ? null : filter_var($bonusRelRaw, FILTER_VALIDATE_FLOAT);
    $bonusAbs = $bonusAbsRaw === "" ? null : filter_var($bonusAbsRaw, FILTER_VALIDATE_FLOAT);
    $invalidExpectedRange = false;
    if ($expectedMin !== null && $expectedMax !== null) {
      if ($direction === "less") {
        $invalidExpectedRange = $expectedMin <= $expectedMax;
      } else {
        $invalidExpectedRange = $expectedMin >= $expectedMax;
      }
    }

    if (
      $disciplineName === "" ||
      $description === "" ||
      $unit === "" ||
      $unitAbbrRaw === "" ||
      $category === "" ||
      !isset($validDirections[$direction]) ||
      ($expectedMinRaw !== "" && $expectedMin === false) ||
      ($expectedMaxRaw !== "" && $expectedMax === false) ||
      ($bonusRelRaw !== "" && ($bonusRel === false || $bonusRel <= 0)) ||
      ($bonusAbsRaw !== "" && ($bonusAbs === false || $bonusAbs <= 0)) ||
      (($expectedMin !== null || $expectedMax !== null) && ($expectedMin === null || $expectedMax === null)) ||
      $invalidExpectedRange
    ) {
      $adminError = t("admin.error.discipline_fields_required", "Bitte alle Felder für die Disziplin ausfüllen.");
    } else {
      try {
        $unitAbbr = $unitAbbrRaw;
        $stmt = $pdo->prepare(
          "SELECT 1 FROM units WHERE unit_name = :unit_name AND unit_abbreviation = :unit_abbreviation AND team_id IS NULL LIMIT 1"
        );
        $stmt->execute([
          ":unit_name" => $unit,
          ":unit_abbreviation" => $unitAbbr,
        ]);
        $unitExists = (bool)$stmt->fetchColumn();
        if (!$unitExists) {
          $stmt = $pdo->prepare(
            "INSERT INTO units (team_id, unit_name, unit_abbreviation) VALUES (NULL, :unit_name, :unit_abbreviation)"
          );
          $stmt->execute([
            ":unit_name" => $unit,
            ":unit_abbreviation" => $unitAbbr,
          ]);
        }
        $stmt = $pdo->prepare(
          "SELECT 1
           FROM disciplines
           WHERE team_id IS NULL
             AND discipline_name = :discipline_name
             AND description = :description
             AND unit = :unit
             AND category = :category
           LIMIT 1"
        );
        $stmt->execute([
          ":discipline_name" => $disciplineName,
          ":description" => $description,
          ":unit" => $unit,
          ":category" => $category,
        ]);
        $exists = (bool)$stmt->fetchColumn();

        if ($exists) {
          $adminError = t("admin.error.discipline_exists", "Diese Disziplin existiert bereits.");
        } else {
        $stmt = $pdo->prepare(
          "INSERT INTO disciplines (team_id, discipline_name, description, unit, category, rating_direction, expected_min, expected_max, bonus_relative, bonus_absolute)
          VALUES (NULL, :discipline_name, :description, :unit, :category, :rating_direction, :expected_min, :expected_max, :bonus_relative, :bonus_absolute)"
        );
          $stmt->execute([
            ":discipline_name" => $disciplineName,
            ":description" => $description,
            ":unit" => $unit,
            ":category" => $category,
            ":rating_direction" => $direction,
            ":expected_min" => $expectedMin,
            ":expected_max" => $expectedMax,
            ":bonus_relative" => $bonusRel,
            ":bonus_absolute" => $bonusAbs,
          ]);
          $adminFeedback = t("admin.feedback.discipline_created", "Disziplin wurde angelegt.");
        }
      } catch (Throwable $e) {
        $adminError = t("admin.error.discipline_create_failed", "Disziplin konnte nicht angelegt werden. Bitte Schema prüfen: ALTER TABLE disciplines MODIFY COLUMN team_id INT NULL;");
      }
    }
  }

  if ($action === "update_global_disciplines" && !empty($_POST["delete_discipline_id"])) {
    $disciplineId = filter_var($_POST["delete_discipline_id"], FILTER_VALIDATE_INT);
    if (!$disciplineId) {
      $adminError = t("admin.error.discipline_delete_failed", "Disziplin konnte nicht gelöscht werden.");
    } else {
      try {
        $stmt = $pdo->prepare("DELETE FROM disciplines WHERE id = :id AND team_id IS NULL");
        $stmt->execute([":id" => $disciplineId]);
        $adminFeedback = t("admin.feedback.discipline_deleted", "Disziplin wurde gelöscht.");
      } catch (Throwable $e) {
        $adminError = t("admin.error.discipline_delete_failed", "Disziplin konnte nicht gelöscht werden.");
      }
    }
  }

  if ($action === "update_global_disciplines" && empty($_POST["delete_discipline_id"])) {
    $disciplineIds = (array)($_POST["discipline_id"] ?? []);
    $disciplineNames = (array)($_POST["discipline_name"] ?? []);
    $descriptions = (array)($_POST["description"] ?? []);
    $unitsInput = (array)($_POST["unit"] ?? []);
    $unitAbbrsInput = (array)($_POST["unit_abbreviation"] ?? []);
    $categories = (array)($_POST["category"] ?? []);
    $directions = (array)($_POST["rating_direction"] ?? []);
    $expectedMins = (array)($_POST["expected_min"] ?? []);
    $expectedMaxs = (array)($_POST["expected_max"] ?? []);
    $bonusRels = (array)($_POST["bonus_relative"] ?? []);
    $bonusAbss = (array)($_POST["bonus_absolute"] ?? []);
    $hasError = false;

    foreach ($disciplineIds as $index => $disciplineIdRaw) {
      $disciplineId = filter_var($disciplineIdRaw, FILTER_VALIDATE_INT);
      $disciplineName = trim((string)($disciplineNames[$index] ?? ""));
      $description = trim((string)($descriptions[$index] ?? ""));
      $unit = trim((string)($unitsInput[$index] ?? ""));
      $unitAbbrRaw = trim((string)($unitAbbrsInput[$index] ?? ""));
      $category = trim((string)($categories[$index] ?? ""));
      $direction = $directions[$index] ?? "";
      $expectedMinRaw = trim((string)($expectedMins[$index] ?? ""));
      $expectedMaxRaw = trim((string)($expectedMaxs[$index] ?? ""));
      $bonusRelRaw = trim((string)($bonusRels[$index] ?? ""));
      $bonusAbsRaw = trim((string)($bonusAbss[$index] ?? ""));
      $expectedMin = $expectedMinRaw === "" ? null : filter_var($expectedMinRaw, FILTER_VALIDATE_FLOAT);
      $expectedMax = $expectedMaxRaw === "" ? null : filter_var($expectedMaxRaw, FILTER_VALIDATE_FLOAT);
      if ($unitAbbrRaw === "" && preg_match('/^(.+?)\s*\(([^)]+)\)\s*$/', $unit, $matches)) {
        $unit = trim($matches[1]);
        $unitAbbrRaw = trim($matches[2]);
      }
      $bonusRel = $bonusRelRaw === "" ? null : filter_var($bonusRelRaw, FILTER_VALIDATE_FLOAT);
      $bonusAbs = $bonusAbsRaw === "" ? null : filter_var($bonusAbsRaw, FILTER_VALIDATE_FLOAT);
      $invalidExpectedRange = false;
      if ($expectedMin !== null && $expectedMax !== null) {
        if ($direction === "less") {
          $invalidExpectedRange = $expectedMin <= $expectedMax;
        } else {
          $invalidExpectedRange = $expectedMin >= $expectedMax;
        }
      }
      if (
        !$disciplineId ||
        $disciplineName === "" ||
        $description === "" ||
        $unit === "" ||
        $unitAbbrRaw === "" ||
        $category === "" ||
        !isset($validDirections[$direction]) ||
        ($expectedMinRaw !== "" && $expectedMin === false) ||
        ($expectedMaxRaw !== "" && $expectedMax === false) ||
        ($bonusRelRaw !== "" && ($bonusRel === false || $bonusRel <= 0)) ||
        ($bonusAbsRaw !== "" && ($bonusAbs === false || $bonusAbs <= 0)) ||
        (($expectedMin !== null || $expectedMax !== null) && ($expectedMin === null || $expectedMax === null)) ||
        $invalidExpectedRange
      ) {
        $hasError = true;
        break;
      }
    }

      if ($hasError) {
        $adminError = t("admin.error.disciplines_fields_required", "Bitte alle Felder für die Disziplinen ausfüllen.");
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
               expected_max = :expected_max,
               bonus_relative = :bonus_relative,
               bonus_absolute = :bonus_absolute
           WHERE id = :id AND team_id IS NULL"
        );
        foreach ($disciplineIds as $index => $disciplineIdRaw) {
          $expectedMinRaw = trim((string)($expectedMins[$index] ?? ""));
          $expectedMaxRaw = trim((string)($expectedMaxs[$index] ?? ""));
          $unitAbbrRaw = trim((string)($unitAbbrsInput[$index] ?? ""));
          $bonusRelRaw = trim((string)($bonusRels[$index] ?? ""));
          $bonusAbsRaw = trim((string)($bonusAbss[$index] ?? ""));
          $expectedMin = $expectedMinRaw === "" ? null : (float)str_replace(",", ".", $expectedMinRaw);
          $expectedMax = $expectedMaxRaw === "" ? null : (float)str_replace(",", ".", $expectedMaxRaw);
          if ($unitAbbrRaw === "" && preg_match('/^(.+?)\s*\(([^)]+)\)\s*$/', (string)($unitsInput[$index] ?? ""), $matches)) {
            $unitAbbrRaw = trim($matches[2]);
          }
          $bonusRel = $bonusRelRaw === "" ? null : (float)str_replace(",", ".", $bonusRelRaw);
          $bonusAbs = $bonusAbsRaw === "" ? null : (float)str_replace(",", ".", $bonusAbsRaw);
          $stmt->execute([
            ":discipline_name" => trim((string)($disciplineNames[$index] ?? "")),
            ":description" => trim((string)($descriptions[$index] ?? "")),
            ":unit" => trim((string)($unitsInput[$index] ?? "")),
            ":category" => trim((string)($categories[$index] ?? "")),
            ":rating_direction" => $directions[$index] ?? "",
            ":expected_min" => $expectedMin,
            ":expected_max" => $expectedMax,
            ":bonus_relative" => $bonusRel,
            ":bonus_absolute" => $bonusAbs,
            ":id" => (int)$disciplineIdRaw,
          ]);
          $unitName = trim((string)($unitsInput[$index] ?? ""));
          $unitAbbr = $unitAbbrRaw;
          if ($unitName !== "" && $unitAbbr !== "") {
            $unitStmt = $pdo->prepare(
              "SELECT 1 FROM units WHERE unit_name = :unit_name AND unit_abbreviation = :unit_abbreviation AND team_id IS NULL LIMIT 1"
            );
            $unitStmt->execute([
              ":unit_name" => $unitName,
              ":unit_abbreviation" => $unitAbbr,
            ]);
            $unitExists = (bool)$unitStmt->fetchColumn();
            if (!$unitExists) {
              $unitStmt = $pdo->prepare(
                "INSERT INTO units (team_id, unit_name, unit_abbreviation) VALUES (NULL, :unit_name, :unit_abbreviation)"
              );
              $unitStmt->execute([
                ":unit_name" => $unitName,
                ":unit_abbreviation" => $unitAbbr,
              ]);
            }
          }
        }
        $adminFeedback = t("admin.feedback.disciplines_updated", "Disziplinen wurden aktualisiert.");
      } catch (Throwable $e) {
        $adminError = t("admin.error.disciplines_update_failed", "Disziplinen konnten nicht gespeichert werden.");
      }
    }
  }

  if ($action === "update_feedback_status") {
    $feedbackId = filter_var($_POST["feedback_id"] ?? null, FILTER_VALIDATE_INT);
    $status = trim($_POST["status"] ?? "");
    $hasStatusColumn = false;
    $statusColumns = $pdo
      ->query(
        "SELECT column_name
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = 'feedback'"
      )
      ->fetchAll(PDO::FETCH_COLUMN);
    if (in_array("status", $statusColumns, true)) {
      $hasStatusColumn = true;
    }
    if (!$hasStatusColumn) {
      $adminError = t("admin.error.feedback_status_schema_missing", "Feedback-Status konnte nicht aktualisiert werden (Schema fehlt).");
    } elseif (!$feedbackId || !in_array($status, $feedbackStatuses, true)) {
      $adminError = t("admin.error.feedback_status_update_failed", "Feedback-Status konnte nicht aktualisiert werden.");
    } else {
      $stmt = $pdo->prepare(
        "UPDATE feedback
         SET status = :status
         WHERE id = :id"
      );
      $stmt->execute([
        ":status" => $status,
        ":id" => $feedbackId,
      ]);
      $adminFeedback = t("admin.feedback.feedback_status_updated", "Feedback-Status wurde aktualisiert.");
    }
  }

  if ($action === "delete_team_admin") {
    $teamId = filter_var($_POST["team_id"] ?? null, FILTER_VALIDATE_INT);
    if (!$teamId) {
      $adminError = t("admin.error.team_delete_failed", "Team konnte nicht gelöscht werden.");
    } else {
      $stmt = $pdo->prepare("DELETE FROM teams WHERE id = :id");
      $stmt->execute([":id" => $teamId]);
      $adminFeedback = t("admin.feedback.team_deleted", "Team wurde gelöscht.");
    }
  }
}

if (!$pageError) {
  $stmt = $pdo->prepare(
    "SELECT id, unit_name, unit_abbreviation, created_at
     FROM units
     WHERE team_id IS NULL
     ORDER BY unit_name ASC"
  );
  $stmt->execute();
  $units = $stmt->fetchAll();

  $unitNameToAbbr = [];
  foreach ($units as $unit) {
    $unitName = trim((string)($unit["unit_name"] ?? ""));
    $unitAbbr = trim((string)($unit["unit_abbreviation"] ?? ""));
    if ($unitName !== "" && $unitAbbr !== "") {
      $unitNameToAbbr[$unitName] = $unitAbbr;
    }
  }

  $stmt = $pdo->prepare(
    "SELECT id, discipline_name, description, unit, category, rating_direction, expected_min, expected_max, bonus_relative, bonus_absolute, created_at
     FROM disciplines
     WHERE team_id IS NULL
     ORDER BY created_at DESC"
  );
  $stmt->execute();
  $globalDisciplines = $stmt->fetchAll();

  $feedbackHasStatus = false;
  $feedbackColumns = $pdo
    ->query(
      "SELECT column_name
       FROM information_schema.columns
       WHERE table_schema = DATABASE()
         AND table_name = 'feedback'"
    )
    ->fetchAll(PDO::FETCH_COLUMN);
  if (in_array("status", $feedbackColumns, true)) {
    $feedbackHasStatus = true;
  }
  if (!$feedbackHasStatus) {
    $feedbackFilter = "all";
  }

  $feedbackSql = "SELECT f.id, f.sender_name, f.sender_email, f.subject, f.message, f.created_at,
                         " . ($feedbackHasStatus ? "f.status" : "'Neu'") . " AS status,
                         t.team_name
                  FROM feedback f
                  LEFT JOIN teams t ON t.id = f.team_id";
  $feedbackParams = [];
  if ($feedbackFilter !== "all") {
    $feedbackSql .= " WHERE f.status = :status";
    $feedbackParams[":status"] = $feedbackFilter;
  }
  $feedbackSql .= " ORDER BY f.created_at DESC";
  $stmt = $pdo->prepare($feedbackSql);
  $stmt->execute($feedbackParams);
  $feedbackEntries = $stmt->fetchAll();

  $stmt = $pdo->prepare(
    "SELECT t.id, t.team_name, t.contact, t.last_login_at,
            COUNT(DISTINCT p.id) AS player_count,
            COUNT(DISTINCT d.id) AS discipline_count,
            COUNT(DISTINCT c.id) AS combine_count
     FROM teams t
     LEFT JOIN players p ON p.team_id = t.id
     LEFT JOIN disciplines d ON d.team_id = t.id
     LEFT JOIN combines c ON c.team_id = t.id
     GROUP BY t.id, t.team_name, t.contact, t.last_login_at
     ORDER BY t.created_at DESC"
  );
  $stmt->execute();
  $teams = $stmt->fetchAll();

  $stmt = $pdo->prepare(
    "SELECT c.id, c.team_id, c.combine_name, c.event_date,
            COUNT(DISTINCT cp.player_id) AS player_count,
            COUNT(DISTINCT cd.discipline_id) AS discipline_count
     FROM combines c
     LEFT JOIN combine_players cp ON cp.combine_id = c.id
     LEFT JOIN combine_disciplines cd ON cd.combine_id = c.id
     GROUP BY c.id, c.team_id, c.combine_name, c.event_date, c.created_at
     ORDER BY c.event_date DESC, c.created_at DESC"
  );
  $stmt->execute();
  foreach ($stmt->fetchAll() as $combineRow) {
    $teamId = (int)($combineRow["team_id"] ?? 0);
    if ($teamId > 0) {
      $teamCombinesByTeam[$teamId][] = $combineRow;
    }
  }
}
?>
<?php
$pageTitle = t("admin.title", "Ultimate Combine – Admin");
$pageLang = "de";
require __DIR__ . "/partials/head.php";
$brandText = "Ultimate Combine";
$brandSuffix = t("admin.brand", "Admin");
$showLogout = true;
$themeLabels = false;
$themeToggleText = "Auto";
require __DIR__ . "/partials/header-brand.php";
?>

  <main class="team">
    <section class="auth-card">
      <h1><?php echo htmlspecialchars(t("admin.overview_title", "Admin-Übersicht"), ENT_QUOTES, "UTF-8"); ?></h1>
      <p class="lead"><?php echo htmlspecialchars(t("admin.overview_lead", "Verwalte Einheiten und behalte Teams im Blick."), ENT_QUOTES, "UTF-8"); ?></p>
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
        <h2><?php echo htmlspecialchars(t("admin.feedback.title", "Feedback"), ENT_QUOTES, "UTF-8"); ?></h2>
        <form class="form" method="get" action="">
          <label class="field">
            <span><?php echo htmlspecialchars(t("admin.feedback.filter_label", "Status"), ENT_QUOTES, "UTF-8"); ?></span>
            <select name="feedback_status" onchange="this.form.submit()">
              <option value="all"<?php echo $feedbackFilter === "all" ? " selected" : ""; ?>><?php echo htmlspecialchars(t("admin.feedback.filter_all", "Alle"), ENT_QUOTES, "UTF-8"); ?></option>
              <?php foreach ($feedbackStatuses as $status): ?>
                <option value="<?php echo htmlspecialchars($status, ENT_QUOTES, "UTF-8"); ?>"<?php echo $feedbackFilter === $status ? " selected" : ""; ?>>
                  <?php echo htmlspecialchars($status, ENT_QUOTES, "UTF-8"); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
        </form>
      </div>
      <?php if (empty($feedbackEntries)): ?>
        <p class="help"><?php echo htmlspecialchars(t("admin.feedback.empty", "Noch kein Feedback eingegangen."), ENT_QUOTES, "UTF-8"); ?></p>
      <?php else: ?>
        <ul class="list list--teams-admin">
          <?php foreach ($feedbackEntries as $entry): ?>
            <?php $previewLine = strtok((string)($entry["message"] ?? ""), "\n"); ?>
            <li class="list-item">
              <details>
                <summary>
                  <strong><?php echo htmlspecialchars($entry["subject"], ENT_QUOTES, "UTF-8"); ?></strong>
                  <span class="badge status-badge status-<?php echo htmlspecialchars(strtolower((string)$entry["status"]), ENT_QUOTES, "UTF-8"); ?>">
                    <?php echo htmlspecialchars($entry["status"] ?? "Neu", ENT_QUOTES, "UTF-8"); ?>
                  </span>
                  <span class="meta">
                    <?php
                      $metaParts = [];
                      if (!empty($entry["sender_name"])) {
                        $metaParts[] = $entry["sender_name"];
                      }
                      if (!empty($entry["sender_email"])) {
                        $metaParts[] = $entry["sender_email"];
                      }
                      if (!empty($entry["team_name"])) {
                        $metaParts[] = t("common.team_prefix", "Team: ") . $entry["team_name"];
                      }
                      if (!empty($entry["created_at"])) {
                        $metaParts[] = $entry["created_at"];
                      }
                    ?>
                    <?php echo htmlspecialchars(implode(" · ", $metaParts), ENT_QUOTES, "UTF-8"); ?>
                  </span>
                  <?php if ($previewLine !== ""): ?>
                    <div class="detail feedback-preview"><?php echo htmlspecialchars($previewLine, ENT_QUOTES, "UTF-8"); ?></div>
                  <?php endif; ?>
                </summary>
                <div class="detail"><?php echo nl2br(htmlspecialchars($entry["message"], ENT_QUOTES, "UTF-8")); ?></div>
                <form class="form" method="post" action="">
                  <input type="hidden" name="action" value="update_feedback_status">
                  <input type="hidden" name="feedback_id" value="<?php echo (int)$entry["id"]; ?>">
                  <div class="form-actions">
                    <label class="field">
                      <span><?php echo htmlspecialchars(t("admin.feedback.set_status", "Status setzen"), ENT_QUOTES, "UTF-8"); ?></span>
                      <select name="status" required>
                        <?php foreach ($feedbackStatuses as $status): ?>
                          <option value="<?php echo htmlspecialchars($status, ENT_QUOTES, "UTF-8"); ?>"<?php echo ($entry["status"] ?? "Neu") === $status ? " selected" : ""; ?>>
                            <?php echo htmlspecialchars($status, ENT_QUOTES, "UTF-8"); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <button class="pill-button" type="submit"><?php echo htmlspecialchars(t("common.save", "Speichern"), ENT_QUOTES, "UTF-8"); ?></button>
                  </div>
                </form>
              </details>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="info">
      <div class="card-header">
        <h2><?php echo htmlspecialchars(t("admin.units.title", "Einheiten"), ENT_QUOTES, "UTF-8"); ?></h2>
        <div class="card-actions" id="units-actions-view">
          <?php if (!empty($units)): ?>
            <button class="pill-button js-toggle" type="button" data-target="units-overview" aria-expanded="false" aria-controls="units-overview" data-toggle-label data-label-open="<?php echo htmlspecialchars(t("common.hide", "Ausblenden"), ENT_QUOTES, "UTF-8"); ?>" data-label-closed="<?php echo htmlspecialchars(t("common.show", "Anzeigen"), ENT_QUOTES, "UTF-8"); ?>">
              <?php echo htmlspecialchars(t("common.show", "Anzeigen"), ENT_QUOTES, "UTF-8"); ?>
            </button>
          <?php endif; ?>
          <button class="pill-button" type="button" data-edit-units><?php echo htmlspecialchars(t("common.edit", "Bearbeiten"), ENT_QUOTES, "UTF-8"); ?></button>
          <button class="icon-button small js-toggle" type="button" data-target="add-unit" aria-expanded="false" aria-controls="add-unit">+</button>
        </div>
        <div class="card-actions is-hidden" id="units-actions-edit" hidden>
          <button class="pill-button" type="button" data-edit-units-cancel><?php echo htmlspecialchars(t("common.cancel", "Abbrechen"), ENT_QUOTES, "UTF-8"); ?></button>
          <button class="primary-button" type="submit" form="unit-edit-form"><?php echo htmlspecialchars(t("common.save", "Speichern"), ENT_QUOTES, "UTF-8"); ?></button>
        </div>
      </div>

      <div id="add-unit" class="is-hidden" hidden>
        <form class="form" method="post" action="">
          <input type="hidden" name="action" value="create_unit">
          <label class="field">
            <span><?php echo htmlspecialchars(t("common.name", "Name"), ENT_QUOTES, "UTF-8"); ?></span>
            <input type="text" name="unit_name" placeholder="<?php echo htmlspecialchars(t("common.unit_placeholder", "z. B. Meter"), ENT_QUOTES, "UTF-8"); ?>" required>
          </label>
          <label class="field">
            <span><?php echo htmlspecialchars(t("admin.units.abbr", "Kürzel"), ENT_QUOTES, "UTF-8"); ?></span>
            <input type="text" name="unit_abbreviation" placeholder="<?php echo htmlspecialchars(t("common.unit_abbr_placeholder", "z. B. m"), ENT_QUOTES, "UTF-8"); ?>" required>
          </label>
          <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("admin.units.create", "Einheit anlegen"), ENT_QUOTES, "UTF-8"); ?></button>
        </form>
      </div>

      <?php if (empty($units)): ?>
        <p class="help"><?php echo htmlspecialchars(t("admin.units.empty", "Noch keine Einheiten hinterlegt."), ENT_QUOTES, "UTF-8"); ?></p>
      <?php else: ?>
        <ul class="list is-hidden" id="units-overview" hidden>
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
                        <span><?php echo htmlspecialchars(t("common.name", "Name"), ENT_QUOTES, "UTF-8"); ?></span>
                        <input type="text" name="unit_name[]" value="<?php echo htmlspecialchars($unit["unit_name"], ENT_QUOTES, "UTF-8"); ?>" required>
                      </label>
                      <label class="field">
                        <span><?php echo htmlspecialchars(t("admin.units.abbr", "Kürzel"), ENT_QUOTES, "UTF-8"); ?></span>
                        <input type="text" name="unit_abbreviation[]" value="<?php echo htmlspecialchars($unit["unit_abbreviation"], ENT_QUOTES, "UTF-8"); ?>" required>
                      </label>
                    </div>
                  </div>
                  <button class="pill-button" type="submit" name="delete_unit_id" value="<?php echo (int)$unit["id"]; ?>" formnovalidate><?php echo htmlspecialchars(t("common.delete", "Löschen"), ENT_QUOTES, "UTF-8"); ?></button>
                </li>
              <?php endforeach; ?>
            </ul>
          </form>
        <?php endif; ?>
      </div>
    </section>

    <section class="info">
      <div class="card-header">
        <h2><?php echo htmlspecialchars(t("admin.disciplines.title", "Globale Disziplinen"), ENT_QUOTES, "UTF-8"); ?></h2>
        <div class="card-actions" id="disciplines-actions-view">
          <?php if (!empty($globalDisciplines)): ?>
            <button class="pill-button js-toggle" type="button" data-target="disciplines-overview" aria-expanded="false" aria-controls="disciplines-overview" data-toggle-label data-label-open="<?php echo htmlspecialchars(t("common.hide", "Ausblenden"), ENT_QUOTES, "UTF-8"); ?>" data-label-closed="<?php echo htmlspecialchars(t("common.show", "Anzeigen"), ENT_QUOTES, "UTF-8"); ?>">
              <?php echo htmlspecialchars(t("common.show", "Anzeigen"), ENT_QUOTES, "UTF-8"); ?>
            </button>
          <?php endif; ?>
          <button class="pill-button" type="button" data-edit-disciplines><?php echo htmlspecialchars(t("common.edit", "Bearbeiten"), ENT_QUOTES, "UTF-8"); ?></button>
          <button class="icon-button small js-toggle" type="button" data-target="add-global-discipline" aria-expanded="false" aria-controls="add-global-discipline">+</button>
        </div>
        <div class="card-actions is-hidden" id="disciplines-actions-edit" hidden>
          <button class="pill-button" type="button" data-edit-disciplines-cancel><?php echo htmlspecialchars(t("common.cancel", "Abbrechen"), ENT_QUOTES, "UTF-8"); ?></button>
          <button class="primary-button" type="submit" form="discipline-edit-form"><?php echo htmlspecialchars(t("common.save", "Speichern"), ENT_QUOTES, "UTF-8"); ?></button>
        </div>
      </div>

      <div id="add-global-discipline" class="is-hidden" hidden>
        <form class="form" method="post" action="">
          <input type="hidden" name="action" value="create_global_discipline">
          <label class="field">
            <span><?php echo htmlspecialchars(t("common.name", "Name"), ENT_QUOTES, "UTF-8"); ?></span>
            <input type="text" name="discipline_name" required>
          </label>
          <label class="field">
            <span><?php echo htmlspecialchars(t("common.description", "Beschreibung"), ENT_QUOTES, "UTF-8"); ?></span>
            <textarea name="description" rows="3" required></textarea>
          </label>
          <label class="field">
            <span><?php echo htmlspecialchars(t("common.unit", "Einheit"), ENT_QUOTES, "UTF-8"); ?></span>
            <input type="text" name="unit" list="admin-unit-options" required data-unit-name>
          </label>
          <label class="field">
            <span><?php echo htmlspecialchars(t("common.unit_abbr", "Einheit (Abkürzung)"), ENT_QUOTES, "UTF-8"); ?></span>
            <input type="text" name="unit_abbreviation" placeholder="<?php echo htmlspecialchars(t("common.unit_abbr_placeholder", "z. B. m"), ENT_QUOTES, "UTF-8"); ?>" required data-unit-abbr>
          </label>
          <label class="field">
            <span><?php echo htmlspecialchars(t("common.category", "Kategorie"), ENT_QUOTES, "UTF-8"); ?></span>
            <input type="text" name="category" required>
          </label>
        <label class="field">
          <span><?php echo htmlspecialchars(t("common.rating", "Bewertung"), ENT_QUOTES, "UTF-8"); ?></span>
          <select name="rating_direction" required>
            <option value=""><?php echo htmlspecialchars(t("common.choose", "Bitte wählen"), ENT_QUOTES, "UTF-8"); ?></option>
            <?php foreach ($validDirections as $key => $label): ?>
              <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, "UTF-8"); ?>">
                <?php echo htmlspecialchars($label, ENT_QUOTES, "UTF-8"); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field">
          <span><?php echo htmlspecialchars(t("common.expected_min", "Erwartung Minimum (1 Punkt)"), ENT_QUOTES, "UTF-8"); ?></span>
          <input type="number" name="expected_min" step="any" placeholder="<?php echo htmlspecialchars(t("common.optional", "Optional"), ENT_QUOTES, "UTF-8"); ?>">
        </label>
        <label class="field">
          <span><?php echo htmlspecialchars(t("common.expected_max", "Erwartung Maximum (2 Punkte)"), ENT_QUOTES, "UTF-8"); ?></span>
          <input type="number" name="expected_max" step="any" placeholder="<?php echo htmlspecialchars(t("common.optional", "Optional"), ENT_QUOTES, "UTF-8"); ?>">
        </label>
        <label class="field">
          <span><?php echo htmlspecialchars(t("common.bonus_relative", "Bonus Platz 1 (Relativ)"), ENT_QUOTES, "UTF-8"); ?></span>
          <input type="number" name="bonus_relative" step="any" placeholder="<?php echo htmlspecialchars(t("common.optional", "Optional"), ENT_QUOTES, "UTF-8"); ?>">
        </label>
        <label class="field">
          <span><?php echo htmlspecialchars(t("common.bonus_absolute", "Bonus Bestwert (Absolut)"), ENT_QUOTES, "UTF-8"); ?></span>
          <input type="number" name="bonus_absolute" step="any" placeholder="<?php echo htmlspecialchars(t("common.optional", "Optional"), ENT_QUOTES, "UTF-8"); ?>">
        </label>
        <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("admin.disciplines.create", "Disziplin anlegen"), ENT_QUOTES, "UTF-8"); ?></button>
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
          <option value="<?php echo htmlspecialchars($unitName, ENT_QUOTES, "UTF-8"); ?>" data-abbr="<?php echo htmlspecialchars($unitAbbr, ENT_QUOTES, "UTF-8"); ?>">
            <?php echo htmlspecialchars($unitLabel, ENT_QUOTES, "UTF-8"); ?>
          </option>
        <?php endforeach; ?>
      </datalist>

      <?php if (empty($globalDisciplines)): ?>
        <p class="help"><?php echo htmlspecialchars(t("admin.disciplines.empty", "Noch keine globalen Disziplinen hinterlegt."), ENT_QUOTES, "UTF-8"); ?></p>
      <?php else: ?>
        <ul class="list is-hidden" id="disciplines-overview" hidden>
          <?php foreach ($globalDisciplines as $discipline): ?>
            <li class="list-item">
              <div>
                <strong><?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?></strong>
                <span class="meta">
                  <?php
                    $unitName = trim((string)($discipline["unit"] ?? ""));
                    $unitAbbr = $unitNameToAbbr[$unitName] ?? "";
                    $unitLabel = $unitName;
                    if ($unitAbbr !== "" && $unitAbbr !== $unitName) {
                      $unitLabel .= " (" . $unitAbbr . ")";
                    }
                  ?>
                  <?php echo htmlspecialchars($unitLabel, ENT_QUOTES, "UTF-8"); ?>
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
                        <span><?php echo htmlspecialchars(t("common.name", "Name"), ENT_QUOTES, "UTF-8"); ?></span>
                        <input type="text" name="discipline_name[]" value="<?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?>" required>
                      </label>
                      <label class="field">
                        <span><?php echo htmlspecialchars(t("common.description", "Beschreibung"), ENT_QUOTES, "UTF-8"); ?></span>
                        <textarea name="description[]" rows="3" required><?php echo htmlspecialchars($discipline["description"], ENT_QUOTES, "UTF-8"); ?></textarea>
                      </label>
                      <label class="field">
                        <span><?php echo htmlspecialchars(t("common.unit", "Einheit"), ENT_QUOTES, "UTF-8"); ?></span>
                        <input type="text" name="unit[]" list="admin-unit-options" value="<?php echo htmlspecialchars($discipline["unit"], ENT_QUOTES, "UTF-8"); ?>" required data-unit-name>
                      </label>
                      <label class="field">
                        <span><?php echo htmlspecialchars(t("common.unit_abbr", "Einheit (Abkürzung)"), ENT_QUOTES, "UTF-8"); ?></span>
                        <input type="text" name="unit_abbreviation[]" value="<?php echo htmlspecialchars($unitNameToAbbr[$discipline["unit"] ?? ""] ?? "", ENT_QUOTES, "UTF-8"); ?>" required data-unit-abbr>
                      </label>
                      <label class="field">
                        <span><?php echo htmlspecialchars(t("common.category", "Kategorie"), ENT_QUOTES, "UTF-8"); ?></span>
                        <input type="text" name="category[]" value="<?php echo htmlspecialchars($discipline["category"], ENT_QUOTES, "UTF-8"); ?>" required>
                      </label>
                      <label class="field">
                        <span><?php echo htmlspecialchars(t("common.rating", "Bewertung"), ENT_QUOTES, "UTF-8"); ?></span>
                        <select name="rating_direction[]" required>
                          <?php foreach ($validDirections as $key => $label): ?>
                            <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, "UTF-8"); ?>"<?php echo ($discipline["rating_direction"] ?? "") === $key ? " selected" : ""; ?>>
                              <?php echo htmlspecialchars($label, ENT_QUOTES, "UTF-8"); ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </label>
                      <label class="field">
                        <span><?php echo htmlspecialchars(t("common.expected_min", "Erwartung Minimum (1 Punkt)"), ENT_QUOTES, "UTF-8"); ?></span>
                        <input type="number" name="expected_min[]" step="any" value="<?php echo htmlspecialchars($discipline["expected_min"] ?? "", ENT_QUOTES, "UTF-8"); ?>" placeholder="<?php echo htmlspecialchars(t("common.optional", "Optional"), ENT_QUOTES, "UTF-8"); ?>">
                      </label>
                      <label class="field">
                        <span><?php echo htmlspecialchars(t("common.expected_max", "Erwartung Maximum (2 Punkte)"), ENT_QUOTES, "UTF-8"); ?></span>
                        <input type="number" name="expected_max[]" step="any" value="<?php echo htmlspecialchars($discipline["expected_max"] ?? "", ENT_QUOTES, "UTF-8"); ?>" placeholder="<?php echo htmlspecialchars(t("common.optional", "Optional"), ENT_QUOTES, "UTF-8"); ?>">
                      </label>
                      <label class="field">
                        <span><?php echo htmlspecialchars(t("common.bonus_relative", "Bonus Platz 1 (Relativ)"), ENT_QUOTES, "UTF-8"); ?></span>
                        <input type="number" name="bonus_relative[]" step="any" value="<?php echo htmlspecialchars($discipline["bonus_relative"] ?? "", ENT_QUOTES, "UTF-8"); ?>" placeholder="<?php echo htmlspecialchars(t("common.optional", "Optional"), ENT_QUOTES, "UTF-8"); ?>">
                      </label>
                      <label class="field">
                        <span><?php echo htmlspecialchars(t("common.bonus_absolute", "Bonus Bestwert (Absolut)"), ENT_QUOTES, "UTF-8"); ?></span>
                        <input type="number" name="bonus_absolute[]" step="any" value="<?php echo htmlspecialchars($discipline["bonus_absolute"] ?? "", ENT_QUOTES, "UTF-8"); ?>" placeholder="<?php echo htmlspecialchars(t("common.optional", "Optional"), ENT_QUOTES, "UTF-8"); ?>">
                      </label>
                    </div>
                  </div>
                  <button class="pill-button" type="submit" name="delete_discipline_id" value="<?php echo (int)$discipline["id"]; ?>" formnovalidate><?php echo htmlspecialchars(t("common.delete", "Löschen"), ENT_QUOTES, "UTF-8"); ?></button>
                </li>
              <?php endforeach; ?>
            </ul>
          </form>
        <?php endif; ?>
      </div>
    </section>

    <section class="info">
      <div class="card-header">
        <h2><?php echo htmlspecialchars(t("admin.teams.title", "Teams"), ENT_QUOTES, "UTF-8"); ?></h2>
        <div class="card-actions">
          <button class="pill-button js-toggle" type="button" data-target="admin-broadcast" aria-expanded="false" aria-controls="admin-broadcast" data-toggle-label data-label-open="<?php echo htmlspecialchars(t("common.cancel", "Abbrechen"), ENT_QUOTES, "UTF-8"); ?>" data-label-closed="<?php echo htmlspecialchars(t("admin.mail.button", "Mail an alle"), ENT_QUOTES, "UTF-8"); ?>">
            <?php echo htmlspecialchars(t("admin.mail.button", "Mail an alle"), ENT_QUOTES, "UTF-8"); ?>
          </button>
        </div>
      </div>
      <div id="admin-broadcast" class="is-hidden" hidden>
        <form class="form" method="post" action="" onsubmit="return confirm('<?php echo htmlspecialchars(t("admin.mail.confirm", "Mail wirklich an alle Teams senden?"), ENT_QUOTES, "UTF-8"); ?>');">
          <input type="hidden" name="action" value="send_broadcast_email">
          <label class="field">
            <span><?php echo htmlspecialchars(t("admin.mail.subject", "Betreff"), ENT_QUOTES, "UTF-8"); ?></span>
            <input type="text" name="broadcast_subject" value="<?php echo htmlspecialchars($broadcastSubject, ENT_QUOTES, "UTF-8"); ?>" required>
          </label>
          <label class="field">
            <span><?php echo htmlspecialchars(t("admin.mail.message", "Nachricht"), ENT_QUOTES, "UTF-8"); ?></span>
            <textarea name="broadcast_message" rows="6" required><?php echo htmlspecialchars($broadcastMessage, ENT_QUOTES, "UTF-8"); ?></textarea>
          </label>
          <p class="help"><?php echo htmlspecialchars(t("admin.mail.note", "Empfänger sind alle Teams mit hinterlegter Kontakt-E-Mail."), ENT_QUOTES, "UTF-8"); ?></p>
          <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("admin.mail.send", "Mail senden"), ENT_QUOTES, "UTF-8"); ?></button>
        </form>
      </div>
      <?php if (empty($teams)): ?>
        <p class="help"><?php echo htmlspecialchars(t("admin.teams.empty", "Noch keine Teams registriert."), ENT_QUOTES, "UTF-8"); ?></p>
      <?php else: ?>
        <ul class="list list--teams-admin">
          <?php foreach ($teams as $team): ?>
            <?php
              $teamId = (int)$team["id"];
              $teamCombines = $teamCombinesByTeam[$teamId] ?? [];
              $playersCount = (int)($team["player_count"] ?? 0);
              $disciplinesCount = (int)($team["discipline_count"] ?? 0);
              $combinesCount = (int)($team["combine_count"] ?? 0);
            ?>
            <li class="list-item list-item--admin-team">
              <details class="admin-team-details">
                <summary>
                  <div class="admin-team-summary-main">
                    <span class="admin-team-arrow" aria-hidden="true"></span>
                    <strong><?php echo htmlspecialchars($team["team_name"], ENT_QUOTES, "UTF-8"); ?></strong>
                    <span class="meta"><?php echo htmlspecialchars($team["contact"] ?? "", ENT_QUOTES, "UTF-8"); ?></span>
                  </div>
                  <div class="result-meta">
                    <span class="badge">
                      <?php
                        echo htmlspecialchars(
                          $playersCount . " " . t("common.players", "Spieler") . " · " . $disciplinesCount . " " . t("common.disciplines", "Disziplinen") . " · " . $combinesCount . " " . t("common.combines", "Combines"),
                          ENT_QUOTES,
                          "UTF-8"
                        );
                      ?>
                    </span>
                  </div>
                </summary>
                <div class="admin-team-detail">
                  <p class="detail">
                    <strong><?php echo htmlspecialchars(t("admin.teams.last_login", "Letzter Login"), ENT_QUOTES, "UTF-8"); ?>:</strong>
                    <?php echo htmlspecialchars($team["last_login_at"] ?: t("admin.teams.last_login_never", "Noch nie"), ENT_QUOTES, "UTF-8"); ?>
                  </p>
                  <?php if (empty($teamCombines)): ?>
                    <p class="help"><?php echo htmlspecialchars(t("admin.teams.combines_empty", "Noch keine Combines angelegt."), ENT_QUOTES, "UTF-8"); ?></p>
                  <?php else: ?>
                    <ul class="list admin-team-combines">
                      <?php foreach ($teamCombines as $combine): ?>
                        <li class="list-item">
                          <div>
                            <strong><?php echo htmlspecialchars($combine["combine_name"], ENT_QUOTES, "UTF-8"); ?></strong>
                            <span class="meta"><?php echo htmlspecialchars($combine["event_date"], ENT_QUOTES, "UTF-8"); ?></span>
                          </div>
                          <span class="badge">
                            <?php
                              $combinePlayersCount = (int)($combine["player_count"] ?? 0);
                              $combineDisciplinesCount = (int)($combine["discipline_count"] ?? 0);
                              echo htmlspecialchars(
                                $combinePlayersCount . " " . t("common.players", "Spieler") . " · " . $combineDisciplinesCount . " " . t("common.disciplines", "Disziplinen"),
                                ENT_QUOTES,
                                "UTF-8"
                              );
                            ?>
                          </span>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                </div>
              </details>
              <form method="post" action="" onsubmit="return confirm('<?php echo htmlspecialchars(t("admin.confirm.team_delete", "Team wirklich löschen? Alle Combines, Disziplinen und Spieler werden entfernt."), ENT_QUOTES, "UTF-8"); ?>') && confirm('<?php echo htmlspecialchars(t("admin.confirm.team_delete_final", "Letzte Warnung: Dieser Vorgang kann nicht rückgängig gemacht werden. Wirklich löschen?"), ENT_QUOTES, "UTF-8"); ?>');">
                <input type="hidden" name="action" value="delete_team_admin">
                <input type="hidden" name="team_id" value="<?php echo $teamId; ?>">
                <button class="pill-button is-danger" type="submit"><?php echo htmlspecialchars(t("common.delete", "Löschen"), ENT_QUOTES, "UTF-8"); ?></button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </main>
  <?php require __DIR__ . "/partials/footer.php"; ?>
  <script src="js/admin.js"></script>
  <?php require __DIR__ . "/partials/foot.php"; ?>
