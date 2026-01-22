<?php
require_once __DIR__ . "/bootstrap.php";

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

$playerFeedback = null;
$combineFeedback = null;
$disciplineFeedback = null;
$validGenders = [
  "m" => "Männlich",
  "w" => "Weiblich",
  "d" => "Divers",
];
$validDirections = [
  "more" => "Mehr ist besser",
  "less" => "Weniger ist besser",
];
$infoTexts = [
  "players" => "Hier pflegst du deinen Kader mit Namen, Nummern und Positionen.\nKlicke auf einen Spieler, um diesen zu bearbeiten.",
  "combines" => "Combines sind einzelne Leistungsbewertungsevents.\nPro Combine können beliebig viele Spieler in verschiedenen Disziplinen erfasst werden.\nEs können mehrere Combines pro Team angelegt werden.\nKlicke auf ein Combine, um Details zu sehen und Ergebnisse zu erfassen.",
  "disciplines" => "Disziplinen sind die verschiedenen Übungen, die bei einem Combine durchgeführt werden können (z. B. 40-Meter-Sprint, Weitsprung, etc.).\nJede Disziplin hat eine Beschreibung, eine Einheit (z. B. Sekunden, Meter) und eine Bewertungsrichtung (mehr ist besser / weniger ist besser).\nDisziplinen können in Kategorien zusammengefasst werden (z. B. Sprint, Sprung), diese bilden dann die Grundlage für die Gesamtbewertung eines Combines.\nKlicke auf eine Disziplin, um diese zu bearbeiten. (Globale Disziplinen können nicht bearbeitet werden.)",
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
      $playerFeedback = "Bitte alle Pflichtfelder fuer den Spieler korrekt ausfuellen.";
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
        $playerFeedback = "Dieser Spieler existiert bereits.";
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
        $playerFeedback = "Spieler wurde angelegt.";
      }
    }
  }

  if ($action === "create_combine") {
    $combineName = trim($_POST["combine_name"] ?? "");
    $eventDate = trim($_POST["event_date"] ?? "");
    $combineLocation = trim($_POST["combine_location"] ?? "");
    $combineNotes = trim($_POST["combine_notes"] ?? "");

    if ($combineName === "" || $eventDate === "") {
      $combineFeedback = "Bitte Name und Datum fuer das Combine angeben.";
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
        $combineFeedback = "Dieses Combine existiert bereits.";
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
        $combineFeedback = "Combine wurde angelegt.";
      }
    }
  }

  if ($action === "create_discipline") {
    $disciplineName = trim($_POST["discipline_name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $unit = trim($_POST["unit"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $direction = $_POST["rating_direction"] ?? "";
    $expectedMinRaw = trim($_POST["expected_min"] ?? "");
    $expectedMaxRaw = trim($_POST["expected_max"] ?? "");
    $bonusRelRaw = trim($_POST["bonus_relative"] ?? "");
    $bonusAbsRaw = trim($_POST["bonus_absolute"] ?? "");
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
      $category === "" ||
      !isset($validDirections[$direction]) ||
      ($expectedMinRaw !== "" && $expectedMin === false) ||
      ($expectedMaxRaw !== "" && $expectedMax === false) ||
      ($bonusRelRaw !== "" && ($bonusRel === false || $bonusRel <= 0)) ||
      ($bonusAbsRaw !== "" && ($bonusAbs === false || $bonusAbs <= 0)) ||
      (($expectedMin !== null || $expectedMax !== null) && ($expectedMin === null || $expectedMax === null))
    ) {
      $disciplineFeedback = "Bitte alle Felder für die Disziplin ausfüllen.";
    } elseif ($invalidExpectedRange) {
      if ($direction === "less") {
        $disciplineFeedback = "Bei \"weniger ist besser\" muss der beste Wert kleiner als der schlechteste Wert sein.";
      } else {
        $disciplineFeedback = "Bei \"mehr ist besser\" muss der beste Wert größer als der schlechteste Wert sein.";
      }
    } else {
      $stmt = $pdo->prepare(
        "SELECT 1
         FROM disciplines
         WHERE (team_id = :team_id OR team_id IS NULL)
           AND discipline_name = :discipline_name
         LIMIT 1"
      );
      $stmt->execute([
        ":team_id" => $teamId,
        ":discipline_name" => $disciplineName,
      ]);
      $exists = (bool)$stmt->fetchColumn();

      if ($exists) {
        $disciplineFeedback = "Diese Disziplin existiert bereits.";
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
        $disciplineFeedback = "Disziplin wurde angelegt.";
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
      $teamEditFeedback = "Bitte alle Felder für das Team ausfüllen.";
    } elseif (!filter_var($teamContactInput, FILTER_VALIDATE_EMAIL)) {
      $teamEditFeedback = "Bitte eine gültige E-Mail-Adresse angeben.";
    } elseif ($changeKey && ($oldKey === "" || $newKey === "" || $newKeyRepeat === "")) {
      $teamEditFeedback = "Bitte alle Felder für das Schluesselwort ausfüllen.";
    } elseif ($changeKey && $newKey !== $newKeyRepeat) {
      $teamEditFeedback = "Das neue Schlüsselwort stimmt nicht überein.";
    } elseif ($changeKey && (!$currentKeyHash || !password_verify($oldKey, $currentKeyHash))) {
      $teamEditFeedback = "Das aktuelle Schlüsselwort ist falsch.";
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
        $teamEditFeedback = "Dieser Teamname ist bereits vergeben.";
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
        $teamEditFeedback = "Team wurde aktualisiert.";
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
      $playerFeedback = "Bitte alle Pflichtfelder für den Spieler korrekt ausfüllen.";
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
        $playerFeedback = "Dieser Spieler existiert bereits.";
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
        $playerFeedback = "Spieler wurde aktualisiert.";
      }
    }
  }

  if ($action === "delete_player") {
    $editType = "player";
    $editId = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT);
    if (!$editId) {
      $playerFeedback = "Spieler konnte nicht gelöscht werden.";
    } else {
      $stmt = $pdo->prepare("DELETE FROM players WHERE id = :id AND team_id = :team_id");
      $stmt->execute([
        ":id" => $editId,
        ":team_id" => $teamId,
      ]);
      $playerFeedback = "Spieler wurde gelöscht.";
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
      $combineFeedback = "Bitte Name und Datum fuer das Combine angeben.";
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
        $combineFeedback = "Dieses Combine existiert bereits.";
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
        $combineFeedback = "Combine wurde aktualisiert.";
      }
    }
  }

  if ($action === "update_discipline") {
    $editType = "discipline";
    $editId = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT);
    $disciplineName = trim($_POST["discipline_name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $unit = trim($_POST["unit"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $direction = $_POST["rating_direction"] ?? "";
    $expectedMinRaw = trim($_POST["expected_min"] ?? "");
    $expectedMaxRaw = trim($_POST["expected_max"] ?? "");
    $bonusRelRaw = trim($_POST["bonus_relative"] ?? "");
    $bonusAbsRaw = trim($_POST["bonus_absolute"] ?? "");
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
      $category === "" ||
      !isset($validDirections[$direction]) ||
      ($expectedMinRaw !== "" && $expectedMin === false) ||
      ($expectedMaxRaw !== "" && $expectedMax === false) ||
      ($bonusRelRaw !== "" && ($bonusRel === false || $bonusRel <= 0)) ||
      ($bonusAbsRaw !== "" && ($bonusAbs === false || $bonusAbs <= 0)) ||
      (($expectedMin !== null || $expectedMax !== null) && ($expectedMin === null || $expectedMax === null))
    ) {
      $disciplineFeedback = "Bitte alle Felder für die Disziplin ausfüllen.";
    } elseif ($invalidExpectedRange) {
      if ($direction === "less") {
        $disciplineFeedback = "Bei \"weniger ist besser\" muss der beste Wert kleiner als der schlechteste Wert sein.";
      } else {
        $disciplineFeedback = "Bei \"mehr ist besser\" muss der beste Wert größer als der schlechteste Wert sein.";
      }
    } else {
      $stmt = $pdo->prepare(
        "SELECT 1
         FROM disciplines
         WHERE (team_id = :team_id OR team_id IS NULL)
           AND discipline_name = :discipline_name
           AND id <> :id
         LIMIT 1"
      );
      $stmt->execute([
        ":team_id" => $teamId,
        ":discipline_name" => $disciplineName,
        ":id" => $editId,
      ]);
      $exists = (bool)$stmt->fetchColumn();

      if ($exists) {
        $disciplineFeedback = "Diese Disziplin existiert bereits.";
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
        $disciplineFeedback = "Disziplin wurde aktualisiert.";
      }
    }
  }

  if ($action === "delete_discipline") {
    $editType = "discipline";
    $editId = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT);
    if (!$editId) {
      $disciplineFeedback = "Disziplin konnte nicht gelöscht werden.";
    } else {
      $stmt = $pdo->prepare("DELETE FROM disciplines WHERE id = :id AND team_id = :team_id");
      $stmt->execute([
        ":id" => $editId,
        ":team_id" => $teamId,
      ]);
      $disciplineFeedback = "Disziplin wurde gelöscht.";
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
    $pageError = "Team wurde nicht gefunden.";
  }

  if ($editType && $editId) {
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
      $stmt = $pdo->prepare(
        "SELECT id, discipline_name, description, unit, category, rating_direction, expected_min, expected_max, bonus_relative, bonus_absolute
         FROM disciplines
         WHERE id = :id AND team_id = :team_id"
      );
      $stmt->execute([
        ":id" => $editId,
        ":team_id" => $teamId,
      ]);
      $editRecord = $stmt->fetch();
    } else {
      $editError = "Unbekannter Eintrag.";
    }

    if ($editType && !$editRecord && !$editError) {
      $editError = "Eintrag wurde nicht gefunden.";
    }
  }

  $stmt = $pdo->prepare(
    "SELECT id, first_name, last_name, jersey_number, gender, position_handler, position_cutter, created_at
     FROM players
     WHERE team_id = :team_id
     ORDER BY created_at DESC"
  );
  $stmt->execute([":team_id" => $teamId]);
  $players = $stmt->fetchAll();

  $stmt = $pdo->prepare(
    "SELECT id, combine_name, event_date, combine_location, combine_notes, created_at
     FROM combines
     WHERE team_id = :team_id
     ORDER BY created_at DESC"
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
    "SELECT unit_name, unit_abbreviation
     FROM units
     ORDER BY unit_name ASC"
  );
  $stmt->execute();
  $units = $stmt->fetchAll();

  $disciplineCategories = [];
  $disciplinesByCategory = [];
  foreach ($disciplines as $discipline) {
    $category = trim((string)$discipline["category"]);
    if ($category === "") {
      $category = "Ohne Kategorie";
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
<html lang="de">
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
    <form method="post" action="">
      <input type="hidden" name="action" value="logout">
      <button class="pill-button is-logout" type="submit">Abmelden</button>
    </form>
    <div class="brand">
      <img class="brand-logo" src="assets/FrisbeeCatch.png" alt="Ultimate Combine">
      <span class="brand-text">Ultimate Combine</span>
      <span class="brand-sep">•</span>
      <span class="brand-team"><?php echo htmlspecialchars($teamName, ENT_QUOTES, "UTF-8"); ?></span>
    </div>
    <span></span>
  </header>

  <main class="team">
    <?php $teamEditOpen = ($teamEditFeedback !== null && !$teamEditSuccess); ?>
    <section class="auth-card">
      <div class="section-header">
        <h1><?php echo htmlspecialchars($teamName, ENT_QUOTES, "UTF-8"); ?>-Übersicht</h1>
        <div class="card-actions">
          <button
            class="pill-button is-primary<?php echo $teamEditOpen ? "" : " is-hidden"; ?> js-edit-save"
            type="submit"
            form="team-edit-form"
          >
            Speichern
          </button>
          <button
            class="pill-button js-toggle<?php echo $teamEditOpen ? " is-muted" : ""; ?>"
            type="button"
            data-target="edit-team"
            aria-expanded="<?php echo $teamEditOpen ? "true" : "false"; ?>"
            aria-controls="edit-team"
          >
            <?php echo $teamEditOpen ? "Abbrechen" : "Bearbeiten"; ?>
          </button>
        </div>
      </div>
      <p class="lead">Verwalte Spieler, Disziplinen und Combines für dein Team.</p>
      <?php if ($teamEditFeedback && $teamEditSuccess): ?>
        <p class="help js-flash"><?php echo htmlspecialchars($teamEditFeedback, ENT_QUOTES, "UTF-8"); ?></p>
      <?php endif; ?>
      <?php if ($pageError): ?>
        <p class="help"><?php echo htmlspecialchars($pageError, ENT_QUOTES, "UTF-8"); ?></p>
      <?php endif; ?>
    </section>

    <section class="auth-card<?php echo $teamEditOpen ? "" : " is-hidden"; ?>" id="edit-team">
      <h2>Team bearbeiten</h2>
      <form class="form" id="team-edit-form" method="post" action="">
        <input type="hidden" name="action" value="update_team">
        <label class="field">
          <span>Name</span>
          <input type="text" name="team_name" value="<?php echo htmlspecialchars($teamName, ENT_QUOTES, "UTF-8"); ?>" required>
        </label>
        <label class="field">
          <span>Kontakt</span>
          <input type="email" name="contact" value="<?php echo htmlspecialchars($teamContact, ENT_QUOTES, "UTF-8"); ?>" required>
        </label>
        <input type="hidden" name="change_key" value="0">
        <button class="pill-button js-toggle-key" type="button" aria-expanded="false">Schlüsselwort ändern</button>
        <div class="key-fields is-hidden">
          <label class="field">
            <span>Aktuelles Schlüsselwort</span>
            <input type="password" name="team_key_old" autocomplete="current-password">
          </label>
          <label class="field">
            <span>Neues Schlüsselwort</span>
            <input type="password" name="team_key_new" autocomplete="new-password">
          </label>
          <label class="field">
            <span>Neues Schlüsselwort wiederholen</span>
            <input type="password" name="team_key_repeat" autocomplete="new-password">
          </label>
        </div>
        <?php if ($teamEditFeedback && !$teamEditSuccess): ?>
          <p class="help"><?php echo htmlspecialchars($teamEditFeedback, ENT_QUOTES, "UTF-8"); ?></p>
        <?php endif; ?>
        <div class="form-actions">
          <button class="pill-button is-primary" type="submit">Speichern</button>
          <button class="pill-button is-danger" type="submit" form="delete-team-form">Team löschen</button>
          <button class="pill-button is-muted" type="button" onclick="window.location.href='team.php'">Abbrechen</button>
        </div>
      </form>
      <form id="delete-team-form" method="post" action="" onsubmit="return confirm('Team wirklich löschen? Alle Combines, Disziplinen und Spieler werden entfernt.') && confirm('Letzte Warnung: Dieser Vorgang kann nicht rückgängig gemacht werden. Wirklich löschen?');">
        <input type="hidden" name="action" value="delete_team">
      </form>
    </section>

    <section class="info">
      <h2>Bestehende Daten</h2>
      <div class="info-grid team-info-grid">
        <div class="info-card is-wide">
          <div class="card-header">
            <h3>Combines</h3>
            <div class="card-actions">
              <button class="info-icon js-info" type="button" aria-label="Erklärung: <?php echo $formatLabel($infoTexts["combines"]); ?>" aria-expanded="false" data-tooltip="<?php echo $formatTooltip($infoTexts["combines"]); ?>">i</button>
              <button class="icon-button small js-toggle" type="button" data-target="create-combine" aria-expanded="false" aria-controls="create-combine">+</button>
            </div>
          </div>
          <?php if (empty($combines)): ?>
            <p class="help">Noch keine Combines angelegt.</p>
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
            <h3>Spieler</h3>
            <div class="card-actions">
              <button class="info-icon js-info" type="button" aria-label="Erklärung: <?php echo $formatLabel($infoTexts["players"]); ?>" aria-expanded="false" data-tooltip="<?php echo $formatTooltip($infoTexts["players"]); ?>">i</button>
              <button class="icon-button small js-toggle" type="button" data-target="create-player" aria-expanded="false" aria-controls="create-player">+</button>
            </div>
          </div>
          <?php if (empty($players)): ?>
            <p class="help">Noch keine Spieler angelegt.</p>
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
            <h3>Disziplinen</h3>
            <div class="card-actions">
              <button class="info-icon js-info" type="button" aria-label="Erklärung: <?php echo $formatLabel($infoTexts["disciplines"]); ?>" aria-expanded="false" data-tooltip="<?php echo $formatTooltip($infoTexts["disciplines"]); ?>">i</button>
              <button class="icon-button small js-toggle" type="button" data-target="create-discipline" aria-expanded="false" aria-controls="create-discipline">+</button>
            </div>
          </div>
          <?php if (empty($disciplines)): ?>
            <p class="help">Noch keine Disziplinen angelegt.</p>
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
                          <?php echo htmlspecialchars($discipline["unit"], ENT_QUOTES, "UTF-8"); ?>
                          &middot;
                          <?php echo htmlspecialchars($validDirections[$discipline["rating_direction"]] ?? $discipline["rating_direction"], ENT_QUOTES, "UTF-8"); ?>
                          <?php if ($discipline["team_id"] === null): ?>
                            &middot;
                            Global
                          <?php endif; ?>
                        </span>
                        <div class="detail"><?php echo htmlspecialchars($discipline["description"], ENT_QUOTES, "UTF-8"); ?></div>
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

    <section class="team-grid">
      <div class="auth-card is-hidden" id="create-player">
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
            <input type="number" name="jersey_number" min="0">
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
          <div class="field">
            <span>Position</span>
            <div class="check-grid">
              <label class="check-item">
                <input type="checkbox" name="positions[]" value="handler">
                <span>Handler</span>
              </label>
              <label class="check-item">
                <input type="checkbox" name="positions[]" value="cutter">
                <span>Cutter</span>
              </label>
            </div>
          </div>
          <button class="primary-button" type="submit">Spieler speichern</button>
          <?php if ($playerFeedback): ?>
            <p class="help"><?php echo htmlspecialchars($playerFeedback, ENT_QUOTES, "UTF-8"); ?></p>
          <?php endif; ?>
        </form>
      </div>

      <div class="auth-card is-hidden" id="create-combine">
        <h2>Combine anlegen</h2>
        <form class="form" method="post" action="">
          <input type="hidden" name="action" value="create_combine">
          <label class="field">
            <span>Name</span>
            <input type="text" name="combine_name" required>
          </label>
          <label class="field">
            <span>Datum</span>
            <input type="date" name="event_date" required>
          </label>
          <label class="field">
            <span>Ort</span>
            <input type="text" name="combine_location" placeholder="z. B. Sportplatz Nord">
          </label>
          <label class="field">
            <span>Notizen</span>
            <textarea name="combine_notes" rows="3" placeholder="Optional"></textarea>
          </label>
          <button class="primary-button" type="submit">Combine speichern</button>
          <?php if ($combineFeedback): ?>
            <p class="help"><?php echo htmlspecialchars($combineFeedback, ENT_QUOTES, "UTF-8"); ?></p>
          <?php endif; ?>
        </form>
      </div>
    </section>

    <section class="auth-card is-hidden" id="create-discipline">
      <h2>Disziplin anlegen</h2>
      <form class="form" method="post" action="">
        <input type="hidden" name="action" value="create_discipline">
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
          <input type="text" name="unit" list="unit-options" placeholder="z. B. Meter (m)" required>
        </label>
        <label class="field">
          <span>Kategorie</span>
          <input type="text" name="category" list="discipline-categories" placeholder="z. B. Sprint, Sprung" required>
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
            <option value="<?php echo htmlspecialchars($unitLabel, ENT_QUOTES, "UTF-8"); ?>"></option>
          <?php endforeach; ?>
        </datalist>
        <label class="field">
          <span>Bewertung</span>
          <select name="rating_direction" required>
            <option value="">Bitte wählen</option>
            <option value="more">Mehr ist besser</option>
            <option value="less">Weniger ist besser</option>
          </select>
        </label>
        <label class="field">
          <span>Erwartung Schlechtester (1 Punkt)</span>
          <input type="number" name="expected_min" step="any" placeholder="Optional">
        </label>
        <label class="field">
          <span>Erwartung Bester (2 Punkte)</span>
          <input type="number" name="expected_max" step="any" placeholder="Optional">
        </label>
        <label class="field">
          <span>Bonus Platz 1 (Relativ)</span>
          <input type="number" name="bonus_relative" step="any" placeholder="Optional">
        </label>
        <label class="field">
          <span>Bonus Bestwert (Absolut)</span>
          <input type="number" name="bonus_absolute" step="any" placeholder="Optional">
        </label>
        <button class="primary-button" type="submit">Disziplin speichern</button>
        <?php if ($disciplineFeedback): ?>
          <p class="help"><?php echo htmlspecialchars($disciplineFeedback, ENT_QUOTES, "UTF-8"); ?></p>
        <?php endif; ?>
      </form>
    </section>

    <?php if ($editType && $editId): ?>
      <section class="auth-card" id="edit">
        <h2>
          <?php
          if ($editType === "player") {
            echo "Spieler bearbeiten";
          } elseif ($editType === "combine") {
            echo "Combine bearbeiten";
          } elseif ($editType === "discipline") {
            echo "Disziplin bearbeiten";
          } else {
            echo "Eintrag bearbeiten";
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
              <span>Vorname</span>
              <input type="text" name="first_name" value="<?php echo htmlspecialchars($editRecord["first_name"], ENT_QUOTES, "UTF-8"); ?>" required>
            </label>
            <label class="field">
              <span>Nachname</span>
              <input type="text" name="last_name" value="<?php echo htmlspecialchars($editRecord["last_name"], ENT_QUOTES, "UTF-8"); ?>" required>
            </label>
            <label class="field">
              <span>Trikotnummer</span>
              <input type="number" name="jersey_number" min="0" value="<?php echo $editRecord["jersey_number"] !== null ? (int)$editRecord["jersey_number"] : ""; ?>">
            </label>
            <label class="field">
              <span>Geschlecht</span>
              <select name="gender" required>
                <option value="">Bitte wählen</option>
                <?php foreach ($validGenders as $key => $label): ?>
                  <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, "UTF-8"); ?>"<?php echo $editRecord["gender"] === $key ? " selected" : ""; ?>>
                    <?php echo htmlspecialchars($label, ENT_QUOTES, "UTF-8"); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <div class="field">
              <span>Position</span>
              <div class="check-grid">
                <label class="check-item">
                  <input type="checkbox" name="positions[]" value="handler"<?php echo !empty($editRecord["position_handler"]) ? " checked" : ""; ?>>
                  <span>Handler</span>
                </label>
                <label class="check-item">
                  <input type="checkbox" name="positions[]" value="cutter"<?php echo !empty($editRecord["position_cutter"]) ? " checked" : ""; ?>>
                  <span>Cutter</span>
                </label>
              </div>
            </div>
            <div class="form-actions">
              <button class="primary-button" type="submit">Speichern</button>
              <a class="pill-button is-muted" href="team.php">Abbrechen</a>
              <button class="pill-button is-danger" type="submit" form="delete-player-form">Spieler löschen</button>
            </div>
            <?php if ($playerFeedback && $editType === "player"): ?>
              <p class="help"><?php echo htmlspecialchars($playerFeedback, ENT_QUOTES, "UTF-8"); ?></p>
            <?php endif; ?>
          </form>
          <form id="delete-player-form" method="post" action="" onsubmit="return confirm('Spieler wirklich löschen? Alle zugehörigen Ergebnisse werden entfernt.') && confirm('Letzte Warnung: Dieser Vorgang kann nicht rückgängig gemacht werden. Wirklich löschen?');">
            <input type="hidden" name="action" value="delete_player">
            <input type="hidden" name="id" value="<?php echo (int)$editRecord["id"]; ?>">
          </form>
        <?php elseif ($editType === "combine" && $editRecord): ?>
          <form class="form" method="post" action="">
            <input type="hidden" name="action" value="update_combine">
            <input type="hidden" name="id" value="<?php echo (int)$editRecord["id"]; ?>">
            <label class="field">
              <span>Name</span>
              <input type="text" name="combine_name" value="<?php echo htmlspecialchars($editRecord["combine_name"], ENT_QUOTES, "UTF-8"); ?>" required>
            </label>
            <label class="field">
              <span>Datum</span>
              <input type="date" name="event_date" value="<?php echo htmlspecialchars($editRecord["event_date"], ENT_QUOTES, "UTF-8"); ?>" required>
            </label>
            <label class="field">
              <span>Ort</span>
              <input type="text" name="combine_location" value="<?php echo htmlspecialchars($editRecord["combine_location"] ?? "", ENT_QUOTES, "UTF-8"); ?>">
            </label>
            <label class="field">
              <span>Notizen</span>
              <textarea name="combine_notes" rows="3"><?php echo htmlspecialchars($editRecord["combine_notes"] ?? "", ENT_QUOTES, "UTF-8"); ?></textarea>
            </label>
            <div class="form-actions">
              <button class="primary-button" type="submit">Speichern</button>
              <a class="pill-button is-muted" href="team.php">Abbrechen</a>
            </div>
            <?php if ($combineFeedback && $editType === "combine"): ?>
              <p class="help"><?php echo htmlspecialchars($combineFeedback, ENT_QUOTES, "UTF-8"); ?></p>
            <?php endif; ?>
          </form>
        <?php elseif ($editType === "discipline" && $editRecord): ?>
          <form class="form" method="post" action="">
            <input type="hidden" name="action" value="update_discipline">
            <input type="hidden" name="id" value="<?php echo (int)$editRecord["id"]; ?>">
            <label class="field">
              <span>Name</span>
              <input type="text" name="discipline_name" value="<?php echo htmlspecialchars($editRecord["discipline_name"], ENT_QUOTES, "UTF-8"); ?>" required>
            </label>
            <label class="field">
              <span>Beschreibung</span>
              <textarea name="description" rows="3" required><?php echo htmlspecialchars($editRecord["description"], ENT_QUOTES, "UTF-8"); ?></textarea>
            </label>
            <label class="field">
              <span>Einheit</span>
              <input type="text" name="unit" list="unit-options" value="<?php echo htmlspecialchars($editRecord["unit"], ENT_QUOTES, "UTF-8"); ?>" required>
            </label>
            <label class="field">
              <span>Kategorie</span>
              <input type="text" name="category" list="discipline-categories" value="<?php echo htmlspecialchars($editRecord["category"], ENT_QUOTES, "UTF-8"); ?>" required>
            </label>
            <label class="field">
              <span>Bewertung</span>
              <select name="rating_direction" required>
                <option value="">Bitte wählen</option>
                <?php foreach ($validDirections as $key => $label): ?>
                  <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, "UTF-8"); ?>"<?php echo $editRecord["rating_direction"] === $key ? " selected" : ""; ?>>
                    <?php echo htmlspecialchars($label, ENT_QUOTES, "UTF-8"); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="field">
              <span>Erwartung Schlechtester (1 Punkt)</span>
              <input type="number" name="expected_min" step="any" value="<?php echo htmlspecialchars($editRecord["expected_min"] ?? "", ENT_QUOTES, "UTF-8"); ?>" placeholder="Optional">
            </label>
            <label class="field">
              <span>Erwartung Bester (2 Punkte)</span>
              <input type="number" name="expected_max" step="any" value="<?php echo htmlspecialchars($editRecord["expected_max"] ?? "", ENT_QUOTES, "UTF-8"); ?>" placeholder="Optional">
            </label>
            <label class="field">
              <span>Bonus Platz 1 (Relativ)</span>
              <input type="number" name="bonus_relative" step="any" value="<?php echo htmlspecialchars($editRecord["bonus_relative"] ?? "", ENT_QUOTES, "UTF-8"); ?>" placeholder="Optional">
            </label>
            <label class="field">
              <span>Bonus Bestwert (Absolut)</span>
              <input type="number" name="bonus_absolute" step="any" value="<?php echo htmlspecialchars($editRecord["bonus_absolute"] ?? "", ENT_QUOTES, "UTF-8"); ?>" placeholder="Optional">
            </label>
            <div class="form-actions">
              <button class="primary-button" type="submit">Speichern</button>
              <a class="pill-button is-muted" href="team.php">Abbrechen</a>
              <button class="pill-button is-danger" type="submit" form="delete-discipline-form">Disziplin löschen</button>
            </div>
            <?php if ($disciplineFeedback && $editType === "discipline"): ?>
              <p class="help"><?php echo htmlspecialchars($disciplineFeedback, ENT_QUOTES, "UTF-8"); ?></p>
            <?php endif; ?>
          </form>
          <form id="delete-discipline-form" method="post" action="" onsubmit="return confirm('Disziplin wirklich löschen? Alle zugehörigen Ergebnisse werden entfernt.') && confirm('Letzte Warnung: Dieser Vorgang kann nicht rückgängig gemacht werden. Wirklich löschen?');">
            <input type="hidden" name="action" value="delete_discipline">
            <input type="hidden" name="id" value="<?php echo (int)$editRecord["id"]; ?>">
          </form>
        <?php endif; ?>
      </section>
    <?php endif; ?>

  </main>
  <footer class="site-footer">
    <script type="text/javascript" src="https://cdnjs.buymeacoffee.com/1.0.0/button.prod.min.js" data-name="bmc-button" data-slug="jakob.christen" data-color="#ff7b4b" data-emoji="☕" data-font="Inter" data-text="Buy me a coffee" data-outline-color="#000000" data-font-color="#000000" data-coffee-color="#FFDD00"></script>
  </footer>
  <script>
    const toggles = document.querySelectorAll(".js-toggle");
    toggles.forEach((btn) => {
      btn.addEventListener("click", () => {
        const targetId = btn.dataset.target;
        const target = document.getElementById(targetId);
        if (!target) return;
        const isHidden = target.classList.toggle("is-hidden");
        btn.setAttribute("aria-expanded", String(!isHidden));
        if (targetId === "edit-team") {
          const saveButton = document.querySelector(".js-edit-save");
          btn.textContent = isHidden ? "Bearbeiten" : "Abbrechen";
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
  </script>
</body>
</html>
