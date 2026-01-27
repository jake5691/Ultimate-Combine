<?php
require_once __DIR__ . "/bootstrap.php";

if (!$pdo) {
  $pageError = $dbError ?? t("error.db_unreachable", "Datenbank ist nicht erreichbar.");
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

$playerFeedback = null;
$combineFeedback = null;
$disciplineFeedback = null;
$validGenders = [
  "m" => t("team.players.gender_m", "Männlich"),
  "w" => t("team.players.gender_w", "Weiblich"),
  "d" => t("team.players.gender_d", "Divers"),
];
$validDirections = [
  "more" => t("common.more_is_better", "Mehr ist besser"),
  "less" => t("common.less_is_better", "Weniger ist besser"),
];
$infoTexts = [
  "players" => t("team.info.players", "Hier pflegst du deinen Kader mit Namen, Nummern und Positionen.\nKlicke auf einen Spieler, um diesen zu bearbeiten."),
  "combines" => t("team.info.combines", "Combines sind einzelne Leistungsbewertungsevents.\nPro Combine können beliebig viele Spieler in verschiedenen Disziplinen erfasst werden.\nEs können mehrere Combines pro Team angelegt werden.\nKlicke auf ein Combine, um Details zu sehen und Ergebnisse zu erfassen."),
  "disciplines" => t("team.info.disciplines", "Disziplinen sind die verschiedenen Übungen, die bei einem Combine durchgeführt werden können (z. B. 40-Meter-Sprint, Weitsprung, etc.).\nJede Disziplin hat eine Beschreibung, eine Einheit (z. B. Sekunden, Meter) und eine Bewertungsrichtung (mehr ist besser / weniger ist besser).\nDisziplinen können in Kategorien zusammengefasst werden (z. B. Sprint, Sprung), diese bilden dann die Grundlage für die Gesamtbewertung eines Combines.\nKlicke auf eine Disziplin, um diese zu bearbeiten. Globale Disziplinen können nicht direkt bearbeitet werden, aber du kannst sie übernehmen und als Team-Disziplin anpassen."),
];
$formatTooltip = static function (string $text): string {
  return str_replace("\n", "&#10;", htmlspecialchars($text, ENT_QUOTES, "UTF-8"));
};
$formatLabel = static function (string $text): string {
  return htmlspecialchars(str_replace("\n", " ", $text), ENT_QUOTES, "UTF-8");
};
$teamContact = "";
$teamKeyHash = "";
$teamEditFeedback = null;
$teamEditSuccess = false;
$editType = $_GET["edit"] ?? null;
$editId = filter_var($_GET["id"] ?? null, FILTER_VALIDATE_INT);
$cloneSourceId = filter_var($_GET["clone"] ?? null, FILTER_VALIDATE_INT);
$editRecord = null;
$editError = null;

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
    $positions = (array)($_POST["positions"] ?? []);
    $isHandler = in_array("handler", $positions, true) ? 1 : 0;
    $isCutter = in_array("cutter", $positions, true) ? 1 : 0;

    $jerseyNumber = $jerseyRaw === "" ? null : filter_var($jerseyRaw, FILTER_VALIDATE_INT);

    if ($firstName === "" || $lastName === "" || $jerseyNumber === false || !isset($validGenders[$gender])) {
      $playerFeedback = t("team.error.player_required", "Bitte alle Pflichtfelder fuer den Spieler korrekt ausfuellen.");
    } else {
      $stmt = $pdo->prepare(
        "SELECT 1
         FROM players
         WHERE team_id = :team_id
           AND first_name = :first_name
           AND last_name = :last_name
           AND gender = :gender
           AND ((:jersey_number IS NULL AND jersey_number IS NULL) OR jersey_number = :jersey_number)
         LIMIT 1"
      );
      $stmt->execute([
        ":team_id" => $teamId,
        ":first_name" => $firstName,
        ":last_name" => $lastName,
        ":gender" => $gender,
        ":jersey_number" => $jerseyNumber,
      ]);
      $exists = (bool)$stmt->fetchColumn();

      if ($exists) {
        $playerFeedback = t("team.error.player_exists", "Dieser Spieler existiert bereits.");
      } else {
        $stmt = $pdo->prepare(
          "INSERT INTO players (team_id, first_name, last_name, jersey_number, gender, position_handler, position_cutter)
           VALUES (:team_id, :first_name, :last_name, :jersey_number, :gender, :position_handler, :position_cutter)"
        );
        $stmt->execute([
          ":team_id" => $teamId,
          ":first_name" => $firstName,
          ":last_name" => $lastName,
          ":jersey_number" => $jerseyNumber,
        ":gender" => $gender,
        ":position_handler" => $isHandler,
        ":position_cutter" => $isCutter,
        ]);
        $playerFeedback = t("team.feedback.player_created", "Spieler wurde angelegt.");
      }
    }
  }

  if ($action === "create_combine") {
    $combineName = trim($_POST["combine_name"] ?? "");
    $eventDate = trim($_POST["event_date"] ?? "");
    $combineLocation = trim($_POST["combine_location"] ?? "");
    $combineNotes = trim($_POST["combine_notes"] ?? "");

    if ($combineName === "" || $eventDate === "") {
      $combineFeedback = t("team.error.combine_required", "Bitte Name und Datum fuer das Combine angeben.");
    } else {
      $stmt = $pdo->prepare(
        "SELECT 1
         FROM combines
         WHERE team_id = :team_id
           AND combine_name = :combine_name
           AND event_date = :event_date
         LIMIT 1"
      );
      $stmt->execute([
        ":team_id" => $teamId,
        ":combine_name" => $combineName,
        ":event_date" => $eventDate,
      ]);
      $exists = (bool)$stmt->fetchColumn();

      if ($exists) {
        $combineFeedback = t("team.error.combine_exists", "Dieses Combine existiert bereits.");
      } else {
        $stmt = $pdo->prepare(
          "INSERT INTO combines (team_id, combine_name, event_date, combine_location, combine_notes)
           VALUES (:team_id, :combine_name, :event_date, :combine_location, :combine_notes)"
        );
        $stmt->execute([
          ":team_id" => $teamId,
          ":combine_name" => $combineName,
          ":event_date" => $eventDate,
          ":combine_location" => $combineLocation !== "" ? $combineLocation : null,
          ":combine_notes" => $combineNotes !== "" ? $combineNotes : null,
        ]);
        $combineFeedback = t("team.feedback.combine_created", "Combine wurde angelegt.");
      }
    }
  }

  if ($action === "create_discipline") {
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
      (($expectedMin !== null || $expectedMax !== null) && ($expectedMin === null || $expectedMax === null))
    ) {
      $disciplineFeedback = t("team.error.discipline_fields_required", "Bitte alle Felder für die Disziplin ausfüllen.");
    } elseif ($invalidExpectedRange) {
      if ($direction === "less") {
        $disciplineFeedback = t(
          "team.error.expected_range_less",
          "Bei \"weniger ist besser\" muss der beste Wert kleiner als der schlechteste Wert sein."
        );
      } else {
        $disciplineFeedback = t(
          "team.error.expected_range_more",
          "Bei \"mehr ist besser\" muss der beste Wert größer als der schlechteste Wert sein."
        );
      }
    } else {
      $unitAbbr = $unitAbbrRaw;
      $stmt = $pdo->prepare(
        "SELECT 1 FROM units WHERE unit_name = :unit_name AND unit_abbreviation = :unit_abbreviation AND (team_id = :team_id OR team_id IS NULL) LIMIT 1"
      );
      $stmt->execute([
        ":unit_name" => $unit,
        ":unit_abbreviation" => $unitAbbr,
        ":team_id" => $teamId,
      ]);
      $unitExists = (bool)$stmt->fetchColumn();
      if (!$unitExists) {
        $stmt = $pdo->prepare(
          "INSERT INTO units (team_id, unit_name, unit_abbreviation) VALUES (:team_id, :unit_name, :unit_abbreviation)"
        );
        $stmt->execute([
          ":team_id" => $teamId,
          ":unit_name" => $unit,
          ":unit_abbreviation" => $unitAbbr,
        ]);
      }
      $stmt = $pdo->prepare(
        "SELECT 1
         FROM disciplines
         WHERE team_id = :team_id
           AND discipline_name = :discipline_name
           AND description = :description
           AND unit = :unit
           AND category = :category
         LIMIT 1"
      );
      $stmt->execute([
        ":team_id" => $teamId,
        ":discipline_name" => $disciplineName,
        ":description" => $description,
        ":unit" => $unit,
        ":category" => $category,
      ]);
      $exists = (bool)$stmt->fetchColumn();

      if ($exists) {
        $disciplineFeedback = t("team.error.discipline_exists", "Diese Disziplin existiert bereits.");
      } else {
        $stmt = $pdo->prepare(
          "INSERT INTO disciplines (team_id, discipline_name, description, unit, category, rating_direction, expected_min, expected_max, bonus_relative, bonus_absolute)
           VALUES (:team_id, :discipline_name, :description, :unit, :category, :rating_direction, :expected_min, :expected_max, :bonus_relative, :bonus_absolute)"
        );
        $stmt->execute([
          ":team_id" => $teamId,
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
        $disciplineFeedback = t("team.feedback.discipline_created", "Disziplin wurde angelegt.");
      }
    }
  }

  if ($action === "update_team") {
    $teamNameInput = trim($_POST["team_name"] ?? "");
    $teamContactInput = trim($_POST["contact"] ?? "");
    $oldKey = trim($_POST["team_key_old"] ?? "");
    $newKey = trim($_POST["team_key_new"] ?? "");
    $newKeyRepeat = trim($_POST["team_key_repeat"] ?? "");
    $changeKey = ($_POST["change_key"] ?? "0") === "1";
    $currentKeyHash = $teamKeyHash;
    if ($changeKey) {
      $stmt = $pdo->prepare(
        "SELECT team_key_hash
         FROM teams
         WHERE id = :team_id"
      );
      $stmt->execute([":team_id" => $teamId]);
      $currentKeyHash = $stmt->fetchColumn() ?: "";
    }

    if ($teamNameInput === "" || $teamContactInput === "") {
      $teamEditFeedback = t("team.error.team_fields_required", "Bitte alle Felder für das Team ausfüllen.");
    } elseif (!filter_var($teamContactInput, FILTER_VALIDATE_EMAIL)) {
      $teamEditFeedback = t("team.error.contact_invalid", "Bitte eine gültige E-Mail-Adresse angeben.");
    } elseif ($changeKey && ($oldKey === "" || $newKey === "" || $newKeyRepeat === "")) {
      $teamEditFeedback = t("team.error.password_fields_required", "Bitte alle Felder für das Schluesselwort ausfüllen.");
    } elseif ($changeKey && $newKey !== $newKeyRepeat) {
      $teamEditFeedback = t("team.error.password_mismatch", "Das neue Schlüsselwort stimmt nicht überein.");
    } elseif ($changeKey && (!$currentKeyHash || !password_verify($oldKey, $currentKeyHash))) {
      $teamEditFeedback = t("team.error.password_incorrect", "Das aktuelle Schlüsselwort ist falsch.");
    } else {
      $stmt = $pdo->prepare(
        "SELECT 1 FROM teams WHERE team_name = :team_name AND id <> :id"
      );
      $stmt->execute([
        ":team_name" => $teamNameInput,
        ":id" => $teamId,
      ]);
      $exists = (bool)$stmt->fetchColumn();

      if ($exists) {
        $teamEditFeedback = t("team.error.team_name_exists", "Dieser Teamname ist bereits vergeben.");
      } else {
        if ($changeKey) {
          $stmt = $pdo->prepare(
            "UPDATE teams
             SET team_name = :team_name,
                 contact = :contact,
                 team_key_hash = :team_key_hash
             WHERE id = :id"
          );
          $newKeyHash = password_hash($newKey, PASSWORD_DEFAULT);
          $stmt->execute([
            ":team_name" => $teamNameInput,
            ":contact" => $teamContactInput,
            ":team_key_hash" => $newKeyHash,
            ":id" => $teamId,
          ]);
          $teamKeyHash = $newKeyHash;
        } else {
          $stmt = $pdo->prepare(
            "UPDATE teams
             SET team_name = :team_name,
                 contact = :contact
             WHERE id = :id"
          );
          $stmt->execute([
            ":team_name" => $teamNameInput,
            ":contact" => $teamContactInput,
            ":id" => $teamId,
          ]);
        }
        $teamEditFeedback = t("team.feedback.team_updated", "Team wurde aktualisiert.");
        $teamEditSuccess = true;
        $teamName = $teamNameInput;
        $teamContact = $teamContactInput;
        $_SESSION["team_name"] = $teamNameInput;
      }
    }
  }

  if ($action === "delete_team") {
    $stmt = $pdo->prepare("DELETE FROM teams WHERE id = :id");
    $stmt->execute([":id" => $teamId]);
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
  }

  if ($action === "update_player") {
    $editType = "player";
    $editId = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT);
    $firstName = trim($_POST["first_name"] ?? "");
    $lastName = trim($_POST["last_name"] ?? "");
    $jerseyRaw = trim($_POST["jersey_number"] ?? "");
    $gender = $_POST["gender"] ?? "";
    $positions = (array)($_POST["positions"] ?? []);
    $isHandler = in_array("handler", $positions, true) ? 1 : 0;
    $isCutter = in_array("cutter", $positions, true) ? 1 : 0;

    $jerseyNumber = $jerseyRaw === "" ? null : filter_var($jerseyRaw, FILTER_VALIDATE_INT);

    if (!$editId || $firstName === "" || $lastName === "" || $jerseyNumber === false || !isset($validGenders[$gender])) {
      $playerFeedback = t("team.error.player_required", "Bitte alle Pflichtfelder für den Spieler korrekt ausfüllen.");
    } else {
      $stmt = $pdo->prepare(
        "SELECT 1
         FROM players
         WHERE team_id = :team_id
           AND first_name = :first_name
           AND last_name = :last_name
           AND gender = :gender
           AND ((:jersey_number IS NULL AND jersey_number IS NULL) OR jersey_number = :jersey_number)
           AND id <> :id
         LIMIT 1"
      );
      $stmt->execute([
        ":team_id" => $teamId,
        ":first_name" => $firstName,
        ":last_name" => $lastName,
        ":gender" => $gender,
        ":jersey_number" => $jerseyNumber,
        ":id" => $editId,
      ]);
      $exists = (bool)$stmt->fetchColumn();

      if ($exists) {
        $playerFeedback = t("team.error.player_exists", "Dieser Spieler existiert bereits.");
      } else {
        $stmt = $pdo->prepare(
          "UPDATE players
           SET first_name = :first_name,
               last_name = :last_name,
               jersey_number = :jersey_number,
               gender = :gender,
               position_handler = :position_handler,
               position_cutter = :position_cutter
           WHERE id = :id AND team_id = :team_id"
        );
        $stmt->execute([
          ":first_name" => $firstName,
          ":last_name" => $lastName,
          ":jersey_number" => $jerseyNumber,
          ":gender" => $gender,
          ":position_handler" => $isHandler,
          ":position_cutter" => $isCutter,
          ":id" => $editId,
          ":team_id" => $teamId,
        ]);
        $playerFeedback = t("team.feedback.player_updated", "Spieler wurde aktualisiert.");
      }
    }
  }

  if ($action === "delete_player") {
    $editType = "player";
    $editId = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT);
    if (!$editId) {
      $playerFeedback = t("team.error.player_delete_failed", "Spieler konnte nicht gelöscht werden.");
    } else {
      $stmt = $pdo->prepare("DELETE FROM players WHERE id = :id AND team_id = :team_id");
      $stmt->execute([
        ":id" => $editId,
        ":team_id" => $teamId,
      ]);
      $playerFeedback = t("team.feedback.player_deleted", "Spieler wurde gelöscht.");
      $editType = null;
      $editId = null;
      $editRecord = null;
    }
  }

  if ($action === "update_combine") {
    $editType = "combine";
    $editId = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT);
    $combineName = trim($_POST["combine_name"] ?? "");
    $eventDate = trim($_POST["event_date"] ?? "");
    $combineLocation = trim($_POST["combine_location"] ?? "");
    $combineNotes = trim($_POST["combine_notes"] ?? "");

    if (!$editId || $combineName === "" || $eventDate === "") {
      $combineFeedback = t("team.error.combine_required", "Bitte Name und Datum fuer das Combine angeben.");
    } else {
      $stmt = $pdo->prepare(
        "SELECT 1
         FROM combines
         WHERE team_id = :team_id
           AND combine_name = :combine_name
           AND event_date = :event_date
           AND id <> :id
         LIMIT 1"
      );
      $stmt->execute([
        ":team_id" => $teamId,
        ":combine_name" => $combineName,
        ":event_date" => $eventDate,
        ":id" => $editId,
      ]);
      $exists = (bool)$stmt->fetchColumn();

      if ($exists) {
        $combineFeedback = t("team.error.combine_exists", "Dieses Combine existiert bereits.");
      } else {
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
          ":id" => $editId,
          ":team_id" => $teamId,
        ]);
        $combineFeedback = t("team.feedback.combine_updated", "Combine wurde aktualisiert.");
      }
    }
  }

  if ($action === "update_discipline") {
    $editType = "discipline";
    $editId = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT);
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
      !$editId ||
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
      (($expectedMin !== null || $expectedMax !== null) && ($expectedMin === null || $expectedMax === null))
    ) {
      $disciplineFeedback = t("team.error.discipline_fields_required", "Bitte alle Felder für die Disziplin ausfüllen.");
    } elseif ($invalidExpectedRange) {
      if ($direction === "less") {
        $disciplineFeedback = t(
          "team.error.expected_range_less",
          "Bei \"weniger ist besser\" muss der beste Wert kleiner als der schlechteste Wert sein."
        );
      } else {
        $disciplineFeedback = t(
          "team.error.expected_range_more",
          "Bei \"mehr ist besser\" muss der beste Wert größer als der schlechteste Wert sein."
        );
      }
    } else {
      $unitAbbr = $unitAbbrRaw;
      $stmt = $pdo->prepare(
        "SELECT 1 FROM units WHERE unit_name = :unit_name AND unit_abbreviation = :unit_abbreviation AND (team_id = :team_id OR team_id IS NULL) LIMIT 1"
      );
      $stmt->execute([
        ":unit_name" => $unit,
        ":unit_abbreviation" => $unitAbbr,
        ":team_id" => $teamId,
      ]);
      $unitExists = (bool)$stmt->fetchColumn();
      if (!$unitExists) {
        $stmt = $pdo->prepare(
          "INSERT INTO units (team_id, unit_name, unit_abbreviation) VALUES (:team_id, :unit_name, :unit_abbreviation)"
        );
        $stmt->execute([
          ":team_id" => $teamId,
          ":unit_name" => $unit,
          ":unit_abbreviation" => $unitAbbr,
        ]);
      }
      $stmt = $pdo->prepare(
        "SELECT 1
         FROM disciplines
         WHERE team_id = :team_id
           AND discipline_name = :discipline_name
           AND description = :description
           AND unit = :unit
           AND category = :category
           AND id <> :id
         LIMIT 1"
      );
      $stmt->execute([
        ":team_id" => $teamId,
        ":discipline_name" => $disciplineName,
        ":description" => $description,
        ":unit" => $unit,
        ":category" => $category,
        ":id" => $editId,
      ]);
      $exists = (bool)$stmt->fetchColumn();

      if ($exists) {
        $disciplineFeedback = t("team.error.discipline_exists", "Diese Disziplin existiert bereits.");
      } else {
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
           WHERE id = :id AND team_id = :team_id"
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
          ":id" => $editId,
          ":team_id" => $teamId,
        ]);
        $disciplineFeedback = t("team.feedback.discipline_updated", "Disziplin wurde aktualisiert.");
      }
    }
  }

  if ($action === "delete_discipline") {
    $editType = "discipline";
    $editId = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT);
    if (!$editId) {
      $disciplineFeedback = t("team.error.discipline_delete_failed", "Disziplin konnte nicht gelöscht werden.");
    } else {
      $stmt = $pdo->prepare("DELETE FROM disciplines WHERE id = :id AND team_id = :team_id");
      $stmt->execute([
        ":id" => $editId,
        ":team_id" => $teamId,
      ]);
      $disciplineFeedback = t("team.feedback.discipline_deleted", "Disziplin wurde gelöscht.");
      $editType = null;
      $editId = null;
      $editRecord = null;
    }
  }
}

$players = [];
$combines = [];
$disciplines = [];
$units = [];
$disciplineCategories = [];
$disciplinesByCategory = [];

if (!$pageError) {
  $stmt = $pdo->prepare(
    "SELECT team_name, contact, team_key_hash
     FROM teams
     WHERE id = :team_id"
  );
  $stmt->execute([":team_id" => $teamId]);
  $teamRow = $stmt->fetch();
  if ($teamRow) {
    $teamName = $teamRow["team_name"] ?? $teamName;
    $teamContact = $teamRow["contact"] ?? "";
    $teamKeyHash = $teamRow["team_key_hash"] ?? "";
    $_SESSION["team_name"] = $teamName;
  } else {
    $pageError = t("team.error.not_found", "Team wurde nicht gefunden.");
  }

  if ($editType && ($editId || $cloneSourceId)) {
    if ($editType === "player") {
      $stmt = $pdo->prepare(
        "SELECT id, first_name, last_name, jersey_number, gender, position_handler, position_cutter
         FROM players
         WHERE id = :id AND team_id = :team_id"
      );
      $stmt->execute([
        ":id" => $editId,
        ":team_id" => $teamId,
      ]);
      $editRecord = $stmt->fetch();
    } elseif ($editType === "combine") {
      $stmt = $pdo->prepare(
        "SELECT id, combine_name, event_date, combine_location, combine_notes
         FROM combines
         WHERE id = :id AND team_id = :team_id"
      );
      $stmt->execute([
        ":id" => $editId,
        ":team_id" => $teamId,
      ]);
      $editRecord = $stmt->fetch();
    } elseif ($editType === "discipline") {
      if ($editId) {
        $stmt = $pdo->prepare(
          "SELECT id, discipline_name, description, unit, category, rating_direction, expected_min, expected_max, bonus_relative, bonus_absolute, team_id
           FROM disciplines
           WHERE id = :id AND team_id = :team_id"
        );
        $stmt->execute([
          ":id" => $editId,
          ":team_id" => $teamId,
        ]);
        $editRecord = $stmt->fetch();
      } elseif ($cloneSourceId) {
        $stmt = $pdo->prepare(
          "SELECT discipline_name, description, unit, category, rating_direction, expected_min, expected_max, bonus_relative, bonus_absolute, team_id
           FROM disciplines
           WHERE id = :id AND team_id IS NULL"
        );
        $stmt->execute([":id" => $cloneSourceId]);
        $editRecord = $stmt->fetch();
      }
    } else {
      $editError = t("team.error.entry_unknown", "Unbekannter Eintrag.");
    }

    if ($editType && !$editRecord && !$editError) {
      $editError = t("team.error.entry_not_found", "Eintrag wurde nicht gefunden.");
    }
  }

  $stmt = $pdo->prepare(
    "SELECT id, first_name, last_name, jersey_number, gender, position_handler, position_cutter, created_at
     FROM players
     WHERE team_id = :team_id
     ORDER BY first_name ASC, last_name ASC"
  );
  $stmt->execute([":team_id" => $teamId]);
  $players = $stmt->fetchAll();

  $stmt = $pdo->prepare(
    "SELECT id, combine_name, event_date, combine_location, combine_notes, created_at
     FROM combines
     WHERE team_id = :team_id
     ORDER BY event_date DESC, created_at DESC"
  );
  $stmt->execute([":team_id" => $teamId]);
  $combines = $stmt->fetchAll();

  $stmt = $pdo->prepare(
    "SELECT id, team_id, discipline_name, description, unit, category, rating_direction, expected_min, expected_max, bonus_relative, bonus_absolute, created_at
     FROM disciplines
     WHERE team_id = :team_id OR team_id IS NULL
     ORDER BY created_at DESC"
  );
  $stmt->execute([":team_id" => $teamId]);
  $disciplines = $stmt->fetchAll();

  $stmt = $pdo->prepare(
    "SELECT unit_name, unit_abbreviation, team_id
     FROM units
     WHERE team_id = :team_id OR team_id IS NULL
     ORDER BY (team_id IS NULL) ASC, unit_name ASC"
  );
  $stmt->execute([":team_id" => $teamId]);
  $units = $stmt->fetchAll();

  $unitNameToAbbr = [];
  foreach ($units as $unit) {
    $unitName = trim((string)($unit["unit_name"] ?? ""));
    $unitAbbr = trim((string)($unit["unit_abbreviation"] ?? ""));
    if ($unitName !== "" && $unitAbbr !== "") {
      if (!isset($unitNameToAbbr[$unitName])) {
        $unitNameToAbbr[$unitName] = $unitAbbr;
      }
    }
  }

  $disciplineCategories = [];
  $disciplinesByCategory = [];
  foreach ($disciplines as $discipline) {
    $category = trim((string)$discipline["category"]);
    if ($category === "") {
      $category = t("common.uncategorized", "Ohne Kategorie");
    }
    $disciplinesByCategory[$category][] = $discipline;
    $disciplineCategories[] = $category;
  }
  $disciplineCategories = array_values(array_unique($disciplineCategories));
  sort($disciplineCategories, SORT_NATURAL | SORT_FLAG_CASE);
  ksort($disciplinesByCategory, SORT_NATURAL | SORT_FLAG_CASE);
}
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($lang, ENT_QUOTES, "UTF-8"); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($teamName, ENT_QUOTES, "UTF-8"); ?></title>
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
    <div class="brand">
      <img class="brand-logo" src="assets/FrisbeeCatch.png" alt="Ultimate Combine">
      <span class="brand-text">Ultimate Combine</span>
      <span class="brand-sep">•</span>
      <span class="brand-team"><?php echo htmlspecialchars($teamName, ENT_QUOTES, "UTF-8"); ?></span>
    </div>
    <div class="topbar-actions">
      <details class="header-menu">
        <summary class="pill-button is-muted" aria-label="<?php echo htmlspecialchars(t("common.menu", "Menü"), ENT_QUOTES, "UTF-8"); ?>">☰</summary>
        <div class="menu-panel">
          <div class="menu-item">
            <span class="menu-label"><?php echo htmlspecialchars(t("common.theme", "Design"), ENT_QUOTES, "UTF-8"); ?></span>
            <button
              class="pill-button is-muted theme-toggle"
              type="button"
              data-theme-toggle
              data-theme-label-system="<?php echo htmlspecialchars(t("common.theme_auto", "Auto"), ENT_QUOTES, "UTF-8"); ?>"
              data-theme-label-dark="<?php echo htmlspecialchars(t("common.theme_dark", "Dunkel"), ENT_QUOTES, "UTF-8"); ?>"
              data-theme-label-light="<?php echo htmlspecialchars(t("common.theme_light", "Hell"), ENT_QUOTES, "UTF-8"); ?>"
              aria-pressed="false"
            ><?php echo htmlspecialchars(t("common.theme_auto", "Auto"), ENT_QUOTES, "UTF-8"); ?></button>
          </div>
          <div class="menu-item">
            <span class="menu-label"><?php echo htmlspecialchars(t("common.language", "Sprache"), ENT_QUOTES, "UTF-8"); ?></span>
            <div class="menu-links">
              <a class="pill-button is-muted<?php echo $lang === "de" ? " is-active" : ""; ?>" href="<?php echo htmlspecialchars(uc_lang_url("de"), ENT_QUOTES, "UTF-8"); ?>">DE</a>
              <a class="pill-button is-muted<?php echo $lang === "en" ? " is-active" : ""; ?>" href="<?php echo htmlspecialchars(uc_lang_url("en"), ENT_QUOTES, "UTF-8"); ?>">EN</a>
            </div>
          </div>
          <div class="menu-item">
            <form method="post" action="">
              <input type="hidden" name="action" value="logout">
              <button class="pill-button is-logout" type="submit"><?php echo htmlspecialchars(t("common.logout", "Abmelden"), ENT_QUOTES, "UTF-8"); ?></button>
            </form>
          </div>
        </div>
      </details>
    </div>
  </header>

  <main class="team">
    <?php $teamEditOpen = ($teamEditFeedback !== null && !$teamEditSuccess); ?>
    <section class="auth-card">
      <div class="section-header">
        <h1><?php echo htmlspecialchars($teamName, ENT_QUOTES, "UTF-8"); ?><?php echo htmlspecialchars(t("team.overview_suffix", "-Übersicht"), ENT_QUOTES, "UTF-8"); ?></h1>
        <div class="card-actions">
          <button
            class="pill-button is-primary<?php echo $teamEditOpen ? "" : " is-hidden"; ?> js-edit-save"
            type="submit"
            form="team-edit-form"
          >
            <?php echo htmlspecialchars(t("common.save", "Speichern"), ENT_QUOTES, "UTF-8"); ?>
          </button>
          <button
            class="pill-button js-toggle<?php echo $teamEditOpen ? " is-muted" : ""; ?>"
            type="button"
            data-target="edit-team"
            data-label-edit="<?php echo htmlspecialchars(t("common.edit", "Bearbeiten"), ENT_QUOTES, "UTF-8"); ?>"
            data-label-cancel="<?php echo htmlspecialchars(t("common.cancel", "Abbrechen"), ENT_QUOTES, "UTF-8"); ?>"
            aria-expanded="<?php echo $teamEditOpen ? "true" : "false"; ?>"
            aria-controls="edit-team"
          >
            <?php echo $teamEditOpen ? htmlspecialchars(t("common.cancel", "Abbrechen"), ENT_QUOTES, "UTF-8") : htmlspecialchars(t("common.edit", "Bearbeiten"), ENT_QUOTES, "UTF-8"); ?>
          </button>
        </div>
      </div>
      <p class="lead"><?php echo htmlspecialchars(t("team.lead", "Verwalte Spieler, Disziplinen und Combines für dein Team."), ENT_QUOTES, "UTF-8"); ?></p>
      <?php if ($teamEditFeedback && $teamEditSuccess): ?>
        <p class="help js-flash"><?php echo htmlspecialchars($teamEditFeedback, ENT_QUOTES, "UTF-8"); ?></p>
      <?php endif; ?>
      <?php if ($pageError): ?>
        <p class="help"><?php echo htmlspecialchars($pageError, ENT_QUOTES, "UTF-8"); ?></p>
      <?php endif; ?>
    </section>

    <section class="auth-card<?php echo $teamEditOpen ? "" : " is-hidden"; ?>" id="edit-team">
      <h2><?php echo htmlspecialchars(t("team.edit.title", "Team bearbeiten"), ENT_QUOTES, "UTF-8"); ?></h2>
      <form class="form" id="team-edit-form" method="post" action="">
        <input type="hidden" name="action" value="update_team">
        <label class="field">
          <span><?php echo htmlspecialchars(t("common.name", "Name"), ENT_QUOTES, "UTF-8"); ?></span>
          <input type="text" name="team_name" value="<?php echo htmlspecialchars($teamName, ENT_QUOTES, "UTF-8"); ?>" required>
        </label>
        <label class="field">
          <span><?php echo htmlspecialchars(t("team.contact", "Kontakt"), ENT_QUOTES, "UTF-8"); ?></span>
          <input type="email" name="contact" value="<?php echo htmlspecialchars($teamContact, ENT_QUOTES, "UTF-8"); ?>" required>
        </label>
        <input type="hidden" name="change_key" value="0">
        <button class="pill-button js-toggle-key" type="button" aria-expanded="false"><?php echo htmlspecialchars(t("team.change_password", "Schlüsselwort ändern"), ENT_QUOTES, "UTF-8"); ?></button>
        <div class="key-fields is-hidden">
          <label class="field">
            <span><?php echo htmlspecialchars(t("team.current_password", "Aktuelles Schlüsselwort"), ENT_QUOTES, "UTF-8"); ?></span>
            <input type="password" name="team_key_old" autocomplete="current-password">
          </label>
          <label class="field">
            <span><?php echo htmlspecialchars(t("team.new_password", "Neues Schlüsselwort"), ENT_QUOTES, "UTF-8"); ?></span>
            <input type="password" name="team_key_new" autocomplete="new-password">
          </label>
          <label class="field">
            <span><?php echo htmlspecialchars(t("team.new_password_repeat", "Neues Schlüsselwort wiederholen"), ENT_QUOTES, "UTF-8"); ?></span>
            <input type="password" name="team_key_repeat" autocomplete="new-password">
          </label>
        </div>
        <?php if ($teamEditFeedback && !$teamEditSuccess): ?>
          <p class="help"><?php echo htmlspecialchars($teamEditFeedback, ENT_QUOTES, "UTF-8"); ?></p>
        <?php endif; ?>
        <div class="form-actions">
          <button class="pill-button is-primary" type="submit"><?php echo htmlspecialchars(t("common.save", "Speichern"), ENT_QUOTES, "UTF-8"); ?></button>
          <button class="pill-button is-danger" type="submit" form="delete-team-form"><?php echo htmlspecialchars(t("team.delete_team", "Team löschen"), ENT_QUOTES, "UTF-8"); ?></button>
          <button class="pill-button is-muted" type="button" onclick="window.location.href='team.php'"><?php echo htmlspecialchars(t("common.cancel", "Abbrechen"), ENT_QUOTES, "UTF-8"); ?></button>
        </div>
      </form>
      <form id="delete-team-form" method="post" action="" onsubmit="return confirm('<?php echo htmlspecialchars(t("team.confirm.delete_team", "Team wirklich löschen? Alle Combines, Disziplinen und Spieler werden entfernt."), ENT_QUOTES, "UTF-8"); ?>') && confirm('<?php echo htmlspecialchars(t("team.confirm.delete_team_final", "Letzte Warnung: Dieser Vorgang kann nicht rückgängig gemacht werden. Wirklich löschen?"), ENT_QUOTES, "UTF-8"); ?>');">
        <input type="hidden" name="action" value="delete_team">
      </form>
    </section>

    <section class="info">
      <h2><?php echo htmlspecialchars(t("team.data.title", "Bestehende Daten"), ENT_QUOTES, "UTF-8"); ?></h2>
      <div class="info-grid team-info-grid">
        <div class="info-card is-wide">
          <div class="card-header">
            <h3><?php echo htmlspecialchars(t("team.combines.title", "Combines"), ENT_QUOTES, "UTF-8"); ?></h3>
            <div class="card-actions">
              <button class="info-icon js-info" type="button" aria-label="<?php echo htmlspecialchars(t("common.explanation_prefix", "Erklärung:"), ENT_QUOTES, "UTF-8"); ?> <?php echo $formatLabel($infoTexts["combines"]); ?>" aria-expanded="false" data-tooltip="<?php echo $formatTooltip($infoTexts["combines"]); ?>">i</button>
              <button class="icon-button small js-toggle" type="button" data-target="create-combine" aria-expanded="false" aria-controls="create-combine">+</button>
            </div>
          </div>
          <?php if (empty($combines)): ?>
            <p class="help"><?php echo htmlspecialchars(t("team.combines.empty", "Noch keine Combines angelegt."), ENT_QUOTES, "UTF-8"); ?></p>
          <?php else: ?>
            <ul class="list">
              <?php foreach ($combines as $combine): ?>
                <li class="list-item">
                  <div>
                    <strong>
                      <a class="text-link" href="combine.php?id=<?php echo (int)$combine["id"]; ?>">
                        <?php echo htmlspecialchars($combine["combine_name"], ENT_QUOTES, "UTF-8"); ?>
                      </a>
                    </strong>
                    <span class="meta"><?php echo htmlspecialchars($combine["event_date"], ENT_QUOTES, "UTF-8"); ?></span>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
        <div class="info-card">
          <div class="card-header">
            <h3><?php echo htmlspecialchars(t("team.players.title", "Spieler"), ENT_QUOTES, "UTF-8"); ?></h3>
            <div class="card-actions">
              <button class="info-icon js-info" type="button" aria-label="<?php echo htmlspecialchars(t("common.explanation_prefix", "Erklärung:"), ENT_QUOTES, "UTF-8"); ?> <?php echo $formatLabel($infoTexts["players"]); ?>" aria-expanded="false" data-tooltip="<?php echo $formatTooltip($infoTexts["players"]); ?>">i</button>
              <button class="icon-button small js-toggle" type="button" data-target="create-player" aria-expanded="false" aria-controls="create-player">+</button>
            </div>
          </div>
          <?php if (empty($players)): ?>
            <p class="help"><?php echo htmlspecialchars(t("team.players.empty", "Noch keine Spieler angelegt."), ENT_QUOTES, "UTF-8"); ?></p>
          <?php else: ?>
            <ul class="list">
              <?php foreach ($players as $player): ?>
                <li class="list-item">
                  <div>
                    <strong>
                      <a class="text-link" href="?edit=player&id=<?php echo (int)$player["id"]; ?>#edit">
                        <?php echo htmlspecialchars($player["first_name"], ENT_QUOTES, "UTF-8"); ?>
                        <?php echo " " . htmlspecialchars($player["last_name"], ENT_QUOTES, "UTF-8"); ?>
                      </a>
                    </strong>
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
          <div class="card-header">
            <h3><?php echo htmlspecialchars(t("team.disciplines.title", "Disziplinen"), ENT_QUOTES, "UTF-8"); ?></h3>
            <div class="card-actions">
              <button class="info-icon js-info" type="button" aria-label="<?php echo htmlspecialchars(t("common.explanation_prefix", "Erklärung:"), ENT_QUOTES, "UTF-8"); ?> <?php echo $formatLabel($infoTexts["disciplines"]); ?>" aria-expanded="false" data-tooltip="<?php echo $formatTooltip($infoTexts["disciplines"]); ?>">i</button>
              <button class="icon-button small js-toggle" type="button" data-target="create-discipline" aria-expanded="false" aria-controls="create-discipline">+</button>
            </div>
          </div>
          <?php if (empty($disciplines)): ?>
            <p class="help"><?php echo htmlspecialchars(t("team.disciplines.empty", "Noch keine Disziplinen angelegt."), ENT_QUOTES, "UTF-8"); ?></p>
          <?php else: ?>
            <?php foreach ($disciplinesByCategory as $category => $categoryDisciplines): ?>
              <div class="category-block">
                <h4 class="category-title"><?php echo htmlspecialchars($category, ENT_QUOTES, "UTF-8"); ?></h4>
                <ul class="list">
                  <?php foreach ($categoryDisciplines as $discipline): ?>
                    <li class="list-item">
                      <div>
                        <strong>
                          <?php if ($discipline["team_id"] !== null): ?>
                            <a class="text-link" href="?edit=discipline&id=<?php echo (int)$discipline["id"]; ?>#edit">
                              <?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?>
                            </a>
                          <?php else: ?>
                            <?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?>
                          <?php endif; ?>
                        </strong>
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
                          <?php echo htmlspecialchars($validDirections[$discipline["rating_direction"]] ?? $discipline["rating_direction"], ENT_QUOTES, "UTF-8"); ?>
                          <?php if ($discipline["team_id"] === null): ?>
                            &middot;
                            Global
                          <?php endif; ?>
                        </span>
                        <div class="detail"><?php echo htmlspecialchars($discipline["description"], ENT_QUOTES, "UTF-8"); ?></div>
                      </div>
                      <?php if ($discipline["team_id"] === null): ?>
                        <a class="pill-button is-muted" href="?edit=discipline&clone=<?php echo (int)$discipline["id"]; ?>#edit"><?php echo htmlspecialchars(t("team.disciplines.adapt", "Anpassen"), ENT_QUOTES, "UTF-8"); ?></a>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <section class="team-grid">
      <div class="auth-card is-hidden" id="create-player">
        <h2><?php echo htmlspecialchars(t("team.players.create", "Spieler anlegen"), ENT_QUOTES, "UTF-8"); ?></h2>
        <form class="form" method="post" action="">
          <input type="hidden" name="action" value="create_player">
          <label class="field">
            <span><?php echo htmlspecialchars(t("team.players.first_name", "Vorname"), ENT_QUOTES, "UTF-8"); ?></span>
            <input type="text" name="first_name" required>
          </label>
          <label class="field">
            <span><?php echo htmlspecialchars(t("team.players.last_name", "Nachname"), ENT_QUOTES, "UTF-8"); ?></span>
            <input type="text" name="last_name" required>
          </label>
          <label class="field">
            <span><?php echo htmlspecialchars(t("team.players.jersey_number", "Trikotnummer"), ENT_QUOTES, "UTF-8"); ?></span>
            <input type="number" name="jersey_number" min="0">
          </label>
          <label class="field">
            <span><?php echo htmlspecialchars(t("team.players.gender", "Geschlecht"), ENT_QUOTES, "UTF-8"); ?></span>
            <select name="gender" required>
              <option value=""><?php echo htmlspecialchars(t("common.choose", "Bitte wählen"), ENT_QUOTES, "UTF-8"); ?></option>
              <option value="m"><?php echo htmlspecialchars(t("team.players.gender_m", "Männlich"), ENT_QUOTES, "UTF-8"); ?></option>
              <option value="w"><?php echo htmlspecialchars(t("team.players.gender_w", "Weiblich"), ENT_QUOTES, "UTF-8"); ?></option>
              <option value="d"><?php echo htmlspecialchars(t("team.players.gender_d", "Divers"), ENT_QUOTES, "UTF-8"); ?></option>
            </select>
          </label>
          <div class="field">
            <span><?php echo htmlspecialchars(t("team.players.position", "Position"), ENT_QUOTES, "UTF-8"); ?></span>
            <div class="check-grid">
              <label class="check-item">
                <input type="checkbox" name="positions[]" value="handler">
                <span><?php echo htmlspecialchars(t("team.players.position_handler", "Handler"), ENT_QUOTES, "UTF-8"); ?></span>
              </label>
              <label class="check-item">
                <input type="checkbox" name="positions[]" value="cutter">
                <span><?php echo htmlspecialchars(t("team.players.position_cutter", "Cutter"), ENT_QUOTES, "UTF-8"); ?></span>
              </label>
            </div>
          </div>
          <div class="form-actions">
            <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("team.players.save", "Spieler speichern"), ENT_QUOTES, "UTF-8"); ?></button>
            <button class="pill-button is-muted js-close" type="button" data-target="create-player"><?php echo htmlspecialchars(t("common.cancel", "Abbrechen"), ENT_QUOTES, "UTF-8"); ?></button>
          </div>
          <?php if ($playerFeedback): ?>
            <p class="help"><?php echo htmlspecialchars($playerFeedback, ENT_QUOTES, "UTF-8"); ?></p>
          <?php endif; ?>
        </form>
      </div>

      <div class="auth-card is-hidden" id="create-combine">
        <h2><?php echo htmlspecialchars(t("team.combines.create", "Combine anlegen"), ENT_QUOTES, "UTF-8"); ?></h2>
        <form class="form" method="post" action="">
          <input type="hidden" name="action" value="create_combine">
          <label class="field">
            <span><?php echo htmlspecialchars(t("common.name", "Name"), ENT_QUOTES, "UTF-8"); ?></span>
            <input type="text" name="combine_name" required>
          </label>
          <label class="field">
            <span><?php echo htmlspecialchars(t("common.date", "Datum"), ENT_QUOTES, "UTF-8"); ?></span>
            <input type="date" name="event_date" required>
          </label>
          <label class="field">
            <span><?php echo htmlspecialchars(t("common.location", "Ort"), ENT_QUOTES, "UTF-8"); ?></span>
            <input type="text" name="combine_location" placeholder="<?php echo htmlspecialchars(t("team.combines.location_placeholder", "z. B. Sportplatz Nord"), ENT_QUOTES, "UTF-8"); ?>">
          </label>
          <label class="field">
            <span><?php echo htmlspecialchars(t("common.notes", "Notizen"), ENT_QUOTES, "UTF-8"); ?></span>
            <textarea name="combine_notes" rows="3" placeholder="<?php echo htmlspecialchars(t("common.optional", "Optional"), ENT_QUOTES, "UTF-8"); ?>"></textarea>
          </label>
          <div class="form-actions">
            <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("team.combines.save", "Combine speichern"), ENT_QUOTES, "UTF-8"); ?></button>
            <button class="pill-button is-muted js-close" type="button" data-target="create-combine"><?php echo htmlspecialchars(t("common.cancel", "Abbrechen"), ENT_QUOTES, "UTF-8"); ?></button>
          </div>
          <?php if ($combineFeedback): ?>
            <p class="help"><?php echo htmlspecialchars($combineFeedback, ENT_QUOTES, "UTF-8"); ?></p>
          <?php endif; ?>
        </form>
      </div>
    </section>

    <section class="auth-card is-hidden" id="create-discipline">
      <h2><?php echo htmlspecialchars(t("team.disciplines.create", "Disziplin anlegen"), ENT_QUOTES, "UTF-8"); ?></h2>
      <form class="form" method="post" action="">
        <input type="hidden" name="action" value="create_discipline">
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
          <input type="text" name="unit" list="unit-options" placeholder="<?php echo htmlspecialchars(t("common.unit_placeholder", "z. B. Meter"), ENT_QUOTES, "UTF-8"); ?>" data-unit-name required>
        </label>
        <label class="field">
          <span><?php echo htmlspecialchars(t("common.unit_abbr", "Einheit (Abkürzung)"), ENT_QUOTES, "UTF-8"); ?></span>
          <input type="text" name="unit_abbreviation" placeholder="<?php echo htmlspecialchars(t("common.unit_abbr_placeholder", "z. B. m"), ENT_QUOTES, "UTF-8"); ?>" data-unit-abbr required>
        </label>
        <label class="field">
          <span><?php echo htmlspecialchars(t("common.category", "Kategorie"), ENT_QUOTES, "UTF-8"); ?></span>
          <input type="text" name="category" list="discipline-categories" placeholder="<?php echo htmlspecialchars(t("team.disciplines.category_placeholder", "z. B. Sprint, Sprung"), ENT_QUOTES, "UTF-8"); ?>" required>
        </label>
        <datalist id="discipline-categories">
          <?php foreach ($disciplineCategories as $category): ?>
            <option value="<?php echo htmlspecialchars($category, ENT_QUOTES, "UTF-8"); ?>"></option>
          <?php endforeach; ?>
        </datalist>
        <datalist id="unit-options">
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
        <label class="field">
          <span><?php echo htmlspecialchars(t("common.rating", "Bewertung"), ENT_QUOTES, "UTF-8"); ?></span>
          <select name="rating_direction" required>
            <option value=""><?php echo htmlspecialchars(t("common.choose", "Bitte wählen"), ENT_QUOTES, "UTF-8"); ?></option>
            <option value="more"><?php echo htmlspecialchars(t("common.more_is_better", "Mehr ist besser"), ENT_QUOTES, "UTF-8"); ?></option>
            <option value="less"><?php echo htmlspecialchars(t("common.less_is_better", "Weniger ist besser"), ENT_QUOTES, "UTF-8"); ?></option>
          </select>
        </label>
        <label class="field">
          <span><?php echo htmlspecialchars(t("team.disciplines.expected_worst", "Erwartung Schlechtester (1 Punkt)"), ENT_QUOTES, "UTF-8"); ?></span>
          <input type="number" name="expected_min" step="any" placeholder="<?php echo htmlspecialchars(t("common.optional", "Optional"), ENT_QUOTES, "UTF-8"); ?>">
        </label>
        <label class="field">
          <span><?php echo htmlspecialchars(t("team.disciplines.expected_best", "Erwartung Bester (2 Punkte)"), ENT_QUOTES, "UTF-8"); ?></span>
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
        <div class="form-actions">
          <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("team.disciplines.save", "Disziplin speichern"), ENT_QUOTES, "UTF-8"); ?></button>
          <button class="pill-button is-muted js-close" type="button" data-target="create-discipline"><?php echo htmlspecialchars(t("common.cancel", "Abbrechen"), ENT_QUOTES, "UTF-8"); ?></button>
        </div>
        <?php if ($disciplineFeedback): ?>
          <p class="help"><?php echo htmlspecialchars($disciplineFeedback, ENT_QUOTES, "UTF-8"); ?></p>
        <?php endif; ?>
      </form>
    </section>

    <?php if ($editType && ($editId || $cloneSourceId)): ?>
      <section class="auth-card" id="edit">
        <h2>
          <?php
          if ($editType === "player") {
            echo htmlspecialchars(t("team.edit.player", "Spieler bearbeiten"), ENT_QUOTES, "UTF-8");
          } elseif ($editType === "combine") {
            echo htmlspecialchars(t("team.edit.combine", "Combine bearbeiten"), ENT_QUOTES, "UTF-8");
          } elseif ($editType === "discipline") {
            echo $cloneSourceId
              ? htmlspecialchars(t("team.edit.discipline_clone", "Disziplin übernehmen"), ENT_QUOTES, "UTF-8")
              : htmlspecialchars(t("team.edit.discipline", "Disziplin bearbeiten"), ENT_QUOTES, "UTF-8");
          } else {
            echo htmlspecialchars(t("team.edit.entry", "Eintrag bearbeiten"), ENT_QUOTES, "UTF-8");
          }
          ?>
        </h2>
        <?php if ($editError): ?>
          <p class="help"><?php echo htmlspecialchars($editError, ENT_QUOTES, "UTF-8"); ?></p>
        <?php elseif ($editType === "player" && $editRecord): ?>
          <form class="form" method="post" action="">
            <input type="hidden" name="action" value="update_player">
            <input type="hidden" name="id" value="<?php echo (int)$editRecord["id"]; ?>">
            <label class="field">
              <span><?php echo htmlspecialchars(t("team.players.first_name", "Vorname"), ENT_QUOTES, "UTF-8"); ?></span>
              <input type="text" name="first_name" value="<?php echo htmlspecialchars($editRecord["first_name"], ENT_QUOTES, "UTF-8"); ?>" required>
            </label>
            <label class="field">
              <span><?php echo htmlspecialchars(t("team.players.last_name", "Nachname"), ENT_QUOTES, "UTF-8"); ?></span>
              <input type="text" name="last_name" value="<?php echo htmlspecialchars($editRecord["last_name"], ENT_QUOTES, "UTF-8"); ?>" required>
            </label>
            <label class="field">
              <span><?php echo htmlspecialchars(t("team.players.jersey_number", "Trikotnummer"), ENT_QUOTES, "UTF-8"); ?></span>
              <input type="number" name="jersey_number" min="0" value="<?php echo $editRecord["jersey_number"] !== null ? (int)$editRecord["jersey_number"] : ""; ?>">
            </label>
            <label class="field">
              <span><?php echo htmlspecialchars(t("team.players.gender", "Geschlecht"), ENT_QUOTES, "UTF-8"); ?></span>
              <select name="gender" required>
                <option value=""><?php echo htmlspecialchars(t("common.choose", "Bitte wählen"), ENT_QUOTES, "UTF-8"); ?></option>
                <?php foreach ($validGenders as $key => $label): ?>
                  <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, "UTF-8"); ?>"<?php echo $editRecord["gender"] === $key ? " selected" : ""; ?>>
                    <?php echo htmlspecialchars($label, ENT_QUOTES, "UTF-8"); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <div class="field">
              <span><?php echo htmlspecialchars(t("team.players.position", "Position"), ENT_QUOTES, "UTF-8"); ?></span>
              <div class="check-grid">
                <label class="check-item">
                  <input type="checkbox" name="positions[]" value="handler"<?php echo !empty($editRecord["position_handler"]) ? " checked" : ""; ?>>
                  <span><?php echo htmlspecialchars(t("team.players.position_handler", "Handler"), ENT_QUOTES, "UTF-8"); ?></span>
                </label>
                <label class="check-item">
                  <input type="checkbox" name="positions[]" value="cutter"<?php echo !empty($editRecord["position_cutter"]) ? " checked" : ""; ?>>
                  <span><?php echo htmlspecialchars(t("team.players.position_cutter", "Cutter"), ENT_QUOTES, "UTF-8"); ?></span>
                </label>
              </div>
            </div>
            <div class="form-actions">
              <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("common.save", "Speichern"), ENT_QUOTES, "UTF-8"); ?></button>
              <a class="pill-button is-muted" href="team.php"><?php echo htmlspecialchars(t("common.cancel", "Abbrechen"), ENT_QUOTES, "UTF-8"); ?></a>
              <button class="pill-button is-danger" type="submit" form="delete-player-form"><?php echo htmlspecialchars(t("team.players.delete", "Spieler löschen"), ENT_QUOTES, "UTF-8"); ?></button>
            </div>
            <?php if ($playerFeedback && $editType === "player"): ?>
              <p class="help"><?php echo htmlspecialchars($playerFeedback, ENT_QUOTES, "UTF-8"); ?></p>
            <?php endif; ?>
          </form>
          <form id="delete-player-form" method="post" action="" onsubmit="return confirm('<?php echo htmlspecialchars(t("team.confirm.delete_player", "Spieler wirklich löschen? Alle zugehörigen Ergebnisse werden entfernt."), ENT_QUOTES, "UTF-8"); ?>') && confirm('<?php echo htmlspecialchars(t("team.confirm.delete_player_final", "Letzte Warnung: Dieser Vorgang kann nicht rückgängig gemacht werden. Wirklich löschen?"), ENT_QUOTES, "UTF-8"); ?>');">
            <input type="hidden" name="action" value="delete_player">
            <input type="hidden" name="id" value="<?php echo (int)$editRecord["id"]; ?>">
          </form>
        <?php elseif ($editType === "combine" && $editRecord): ?>
          <form class="form" method="post" action="">
            <input type="hidden" name="action" value="update_combine">
            <input type="hidden" name="id" value="<?php echo (int)$editRecord["id"]; ?>">
            <label class="field">
              <span><?php echo htmlspecialchars(t("common.name", "Name"), ENT_QUOTES, "UTF-8"); ?></span>
              <input type="text" name="combine_name" value="<?php echo htmlspecialchars($editRecord["combine_name"], ENT_QUOTES, "UTF-8"); ?>" required>
            </label>
            <label class="field">
              <span><?php echo htmlspecialchars(t("common.date", "Datum"), ENT_QUOTES, "UTF-8"); ?></span>
              <input type="date" name="event_date" value="<?php echo htmlspecialchars($editRecord["event_date"], ENT_QUOTES, "UTF-8"); ?>" required>
            </label>
            <label class="field">
              <span><?php echo htmlspecialchars(t("common.location", "Ort"), ENT_QUOTES, "UTF-8"); ?></span>
              <input type="text" name="combine_location" value="<?php echo htmlspecialchars($editRecord["combine_location"] ?? "", ENT_QUOTES, "UTF-8"); ?>">
            </label>
            <label class="field">
              <span><?php echo htmlspecialchars(t("common.notes", "Notizen"), ENT_QUOTES, "UTF-8"); ?></span>
              <textarea name="combine_notes" rows="3"><?php echo htmlspecialchars($editRecord["combine_notes"] ?? "", ENT_QUOTES, "UTF-8"); ?></textarea>
            </label>
            <div class="form-actions">
              <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("common.save", "Speichern"), ENT_QUOTES, "UTF-8"); ?></button>
              <a class="pill-button is-muted" href="team.php"><?php echo htmlspecialchars(t("common.cancel", "Abbrechen"), ENT_QUOTES, "UTF-8"); ?></a>
            </div>
            <?php if ($combineFeedback && $editType === "combine"): ?>
              <p class="help"><?php echo htmlspecialchars($combineFeedback, ENT_QUOTES, "UTF-8"); ?></p>
            <?php endif; ?>
          </form>
        <?php elseif ($editType === "discipline" && $editRecord): ?>
          <form class="form" method="post" action="">
            <input type="hidden" name="action" value="<?php echo $cloneSourceId ? "create_discipline" : "update_discipline"; ?>">
            <?php if (!$cloneSourceId): ?>
              <input type="hidden" name="id" value="<?php echo (int)$editRecord["id"]; ?>">
            <?php endif; ?>
            <label class="field">
              <span><?php echo htmlspecialchars(t("common.name", "Name"), ENT_QUOTES, "UTF-8"); ?></span>
              <input type="text" name="discipline_name" value="<?php echo htmlspecialchars($editRecord["discipline_name"], ENT_QUOTES, "UTF-8"); ?>" required>
            </label>
            <label class="field">
              <span><?php echo htmlspecialchars(t("common.description", "Beschreibung"), ENT_QUOTES, "UTF-8"); ?></span>
              <textarea name="description" rows="3" required><?php echo htmlspecialchars($editRecord["description"], ENT_QUOTES, "UTF-8"); ?></textarea>
            </label>
            <label class="field">
              <span><?php echo htmlspecialchars(t("common.unit", "Einheit"), ENT_QUOTES, "UTF-8"); ?></span>
              <input type="text" name="unit" list="unit-options" value="<?php echo htmlspecialchars($editRecord["unit"], ENT_QUOTES, "UTF-8"); ?>" data-unit-name required>
            </label>
            <label class="field">
              <span><?php echo htmlspecialchars(t("common.unit_abbr", "Einheit (Abkürzung)"), ENT_QUOTES, "UTF-8"); ?></span>
              <input type="text" name="unit_abbreviation" value="<?php echo htmlspecialchars($unitNameToAbbr[$editRecord["unit"] ?? ""] ?? "", ENT_QUOTES, "UTF-8"); ?>" data-unit-abbr required>
            </label>
            <label class="field">
              <span><?php echo htmlspecialchars(t("common.category", "Kategorie"), ENT_QUOTES, "UTF-8"); ?></span>
              <input type="text" name="category" list="discipline-categories" value="<?php echo htmlspecialchars($editRecord["category"], ENT_QUOTES, "UTF-8"); ?>" required>
            </label>
            <label class="field">
              <span><?php echo htmlspecialchars(t("common.rating", "Bewertung"), ENT_QUOTES, "UTF-8"); ?></span>
              <select name="rating_direction" required>
                <option value=""><?php echo htmlspecialchars(t("common.choose", "Bitte wählen"), ENT_QUOTES, "UTF-8"); ?></option>
                <?php foreach ($validDirections as $key => $label): ?>
                  <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, "UTF-8"); ?>"<?php echo $editRecord["rating_direction"] === $key ? " selected" : ""; ?>>
                    <?php echo htmlspecialchars($label, ENT_QUOTES, "UTF-8"); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="field">
              <span><?php echo htmlspecialchars(t("team.disciplines.expected_worst", "Erwartung Schlechtester (1 Punkt)"), ENT_QUOTES, "UTF-8"); ?></span>
              <input type="number" name="expected_min" step="any" value="<?php echo htmlspecialchars($editRecord["expected_min"] ?? "", ENT_QUOTES, "UTF-8"); ?>" placeholder="<?php echo htmlspecialchars(t("common.optional", "Optional"), ENT_QUOTES, "UTF-8"); ?>">
            </label>
            <label class="field">
              <span><?php echo htmlspecialchars(t("team.disciplines.expected_best", "Erwartung Bester (2 Punkte)"), ENT_QUOTES, "UTF-8"); ?></span>
              <input type="number" name="expected_max" step="any" value="<?php echo htmlspecialchars($editRecord["expected_max"] ?? "", ENT_QUOTES, "UTF-8"); ?>" placeholder="<?php echo htmlspecialchars(t("common.optional", "Optional"), ENT_QUOTES, "UTF-8"); ?>">
            </label>
            <label class="field">
              <span><?php echo htmlspecialchars(t("common.bonus_relative", "Bonus Platz 1 (Relativ)"), ENT_QUOTES, "UTF-8"); ?></span>
              <input type="number" name="bonus_relative" step="any" value="<?php echo htmlspecialchars($editRecord["bonus_relative"] ?? "", ENT_QUOTES, "UTF-8"); ?>" placeholder="<?php echo htmlspecialchars(t("common.optional", "Optional"), ENT_QUOTES, "UTF-8"); ?>">
            </label>
            <label class="field">
              <span><?php echo htmlspecialchars(t("common.bonus_absolute", "Bonus Bestwert (Absolut)"), ENT_QUOTES, "UTF-8"); ?></span>
              <input type="number" name="bonus_absolute" step="any" value="<?php echo htmlspecialchars($editRecord["bonus_absolute"] ?? "", ENT_QUOTES, "UTF-8"); ?>" placeholder="<?php echo htmlspecialchars(t("common.optional", "Optional"), ENT_QUOTES, "UTF-8"); ?>">
            </label>
            <div class="form-actions">
              <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("common.save", "Speichern"), ENT_QUOTES, "UTF-8"); ?></button>
              <a class="pill-button is-muted" href="team.php"><?php echo htmlspecialchars(t("common.cancel", "Abbrechen"), ENT_QUOTES, "UTF-8"); ?></a>
              <?php if (!$cloneSourceId): ?>
                <button class="pill-button is-danger" type="submit" form="delete-discipline-form"><?php echo htmlspecialchars(t("team.disciplines.delete", "Disziplin löschen"), ENT_QUOTES, "UTF-8"); ?></button>
              <?php endif; ?>
            </div>
            <?php if ($disciplineFeedback && $editType === "discipline"): ?>
              <p class="help"><?php echo htmlspecialchars($disciplineFeedback, ENT_QUOTES, "UTF-8"); ?></p>
            <?php endif; ?>
          </form>
          <?php if (!$cloneSourceId): ?>
            <form id="delete-discipline-form" method="post" action="" onsubmit="return confirm('<?php echo htmlspecialchars(t("team.confirm.delete_discipline", "Disziplin wirklich löschen? Alle zugehörigen Ergebnisse werden entfernt."), ENT_QUOTES, "UTF-8"); ?>') && confirm('<?php echo htmlspecialchars(t("team.confirm.delete_discipline_final", "Letzte Warnung: Dieser Vorgang kann nicht rückgängig gemacht werden. Wirklich löschen?"), ENT_QUOTES, "UTF-8"); ?>');">
              <input type="hidden" name="action" value="delete_discipline">
              <input type="hidden" name="id" value="<?php echo (int)$editRecord["id"]; ?>">
            </form>
          <?php endif; ?>
        <?php endif; ?>
      </section>
    <?php endif; ?>

  </main>
  <footer class="site-footer">
    <a class="footer-link" href="impressum.php"><?php echo htmlspecialchars(t("footer.impressum", "Impressum"), ENT_QUOTES, "UTF-8"); ?></a>
    <a class="footer-link" href="feedback.php"><?php echo htmlspecialchars(t("footer.feedback", "Feedback"), ENT_QUOTES, "UTF-8"); ?></a>
    <script type="text/javascript" src="https://cdnjs.buymeacoffee.com/1.0.0/button.prod.min.js" data-name="bmc-button" data-slug="jakob.christen" data-color="#ff7b4b" data-emoji="☕" data-font="Inter" data-text="Buy me a coffee" data-outline-color="#000000" data-font-color="#000000" data-coffee-color="#FFDD00"></script>
  </footer>
  <script src="theme.js"></script>
  <script>
    const toggles = document.querySelectorAll(".js-toggle");
    const toggleTargets = ["edit-team", "create-player", "create-combine", "create-discipline"];
    const closeTarget = (targetId) => {
      const target = document.getElementById(targetId);
      if (!target || target.classList.contains("is-hidden")) return;
      target.classList.add("is-hidden");
      const toggle = document.querySelector(`[data-target="${targetId}"]`);
      if (!toggle) return;
      toggle.setAttribute("aria-expanded", "false");
      if (targetId === "edit-team") {
        const saveButton = document.querySelector(".js-edit-save");
        const editLabel = toggle.dataset.labelEdit || "<?php echo htmlspecialchars(t("common.edit", "Bearbeiten"), ENT_QUOTES, "UTF-8"); ?>";
        toggle.textContent = editLabel;
        toggle.classList.remove("is-muted");
        if (saveButton) {
          saveButton.classList.add("is-hidden");
        }
      }
    };
    const closeOtherTargets = (exceptId) => {
      toggleTargets.forEach((id) => {
        if (id === exceptId) return;
        closeTarget(id);
      });
    };

    toggles.forEach((btn) => {
      btn.addEventListener("click", () => {
        const targetId = btn.dataset.target;
        const target = document.getElementById(targetId);
        if (!target) return;
        if (targetId && targetId.startsWith("create-")) {
          closeTarget("edit-team");
        }
        const isHidden = target.classList.toggle("is-hidden");
        btn.setAttribute("aria-expanded", String(!isHidden));
        if (!isHidden && targetId && toggleTargets.includes(targetId)) {
          closeOtherTargets(targetId);
        }
        if (targetId === "edit-team") {
          const saveButton = document.querySelector(".js-edit-save");
          const editLabel = btn.dataset.labelEdit || "<?php echo htmlspecialchars(t("common.edit", "Bearbeiten"), ENT_QUOTES, "UTF-8"); ?>";
          const cancelLabel = btn.dataset.labelCancel || "<?php echo htmlspecialchars(t("common.cancel", "Abbrechen"), ENT_QUOTES, "UTF-8"); ?>";
          btn.textContent = isHidden ? editLabel : cancelLabel;
          btn.classList.toggle("is-muted", !isHidden);
          if (saveButton) {
            saveButton.classList.toggle("is-hidden", isHidden);
          }
        }
        if (!isHidden) {
          target.scrollIntoView({ behavior: "smooth", block: "start" });
        }
      });
    });

    const closeButtons = document.querySelectorAll(".js-close");
    closeButtons.forEach((btn) => {
      btn.addEventListener("click", () => {
        const targetId = btn.dataset.target;
        const target = targetId ? document.getElementById(targetId) : null;
        if (!target) return;
        target.classList.add("is-hidden");
        const toggle = document.querySelector(`[data-target="${targetId}"]`);
        if (toggle) {
          toggle.setAttribute("aria-expanded", "false");
        }
      });
    });

    const infoButtons = document.querySelectorAll(".js-info");
    const closeAllInfos = (except) => {
      infoButtons.forEach((btn) => {
        if (btn === except) return;
        btn.classList.remove("is-open");
        btn.setAttribute("aria-expanded", "false");
      });
    };

    infoButtons.forEach((btn) => {
      btn.addEventListener("click", (event) => {
        event.stopPropagation();
        const isOpen = btn.classList.toggle("is-open");
        btn.setAttribute("aria-expanded", String(isOpen));
        if (isOpen) {
          closeAllInfos(btn);
        }
      });
    });

    document.addEventListener("click", () => {
      closeAllInfos();
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") {
        closeAllInfos();
      }
    });

    const flashMessage = document.querySelector(".js-flash");
    if (flashMessage) {
      window.setTimeout(() => {
        flashMessage.classList.add("is-hidden");
      }, 10000);
    }

    const keyToggle = document.querySelector(".js-toggle-key");
    if (keyToggle) {
      const keyFields = document.querySelector(".key-fields");
      const keyInputs = keyFields ? keyFields.querySelectorAll("input") : [];
      const changeKeyInput = document.querySelector("input[name='change_key']");

      keyToggle.addEventListener("click", () => {
        if (!keyFields || !changeKeyInput) return;
        const isHidden = keyFields.classList.toggle("is-hidden");
        const isOpen = !isHidden;
        keyToggle.setAttribute("aria-expanded", String(isOpen));
        changeKeyInput.value = isOpen ? "1" : "0";
        keyInputs.forEach((input) => {
          if (isOpen) {
            input.setAttribute("required", "required");
          } else {
            input.removeAttribute("required");
            input.value = "";
          }
        });
      });
    }

    const unitOptions = document.getElementById("unit-options");
    const unitNameInputs = document.querySelectorAll("input[data-unit-name]");
    unitNameInputs.forEach((input) => {
      const form = input.closest("form");
      const abbrInput = form ? form.querySelector("input[data-unit-abbr]") : null;
      if (!abbrInput) return;
      input.addEventListener("input", () => {
        if (!unitOptions) return;
        const match = Array.from(unitOptions.options).find((opt) => opt.value === input.value);
        if (match) {
          const abbr = match.getAttribute("data-abbr") || "";
          if (abbr !== "") {
            abbrInput.value = abbr;
          }
        }
      });
    });
  </script>
</body>
</html>
