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

function uc_absolute_points($value, $min, $max, string $direction): ?float {
  if ($value === null || $min === null || $max === null) {
    return null;
  }
  $worst = (float)$min;
  $best = (float)$max;
  if ($worst == $best) {
    return null;
  }
  $numericValue = (float)$value;
  if ($direction === "less") {
    $numericValue = -$numericValue;
    $worst = -$best;
    $best = -$min;
  }
  if ($best < $worst) {
    $temp = $best;
    $best = $worst;
    $worst = $temp;
  }
  if ($numericValue >= $best) {
    return 2.0;
  }
  if ($numericValue <= $worst) {
    $points = 1 - (($worst - $numericValue) / ($best - $worst));
    return max(0.0, $points);
  }
  return 1 + (($numericValue - $worst) / ($best - $worst));
}

function uc_bonus_value($value): float {
  $value = uc_value_to_float($value);
  if ($value === null || $value <= 0) {
    return 0.0;
  }
  return (float)$value;
}

function uc_absolute_bonus_applies(?float $numericValue, ?float $bestExpected, string $direction): bool {
  if ($numericValue === null || $bestExpected === null) {
    return false;
  }
  if ($direction === "less") {
    return $numericValue <= $bestExpected;
  }
  return $numericValue >= $bestExpected;
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

function uc_format_unit_label($unit, array $unitMap): string {
  $unitName = trim((string)$unit);
  if ($unitName === "") {
    return "";
  }
  $abbr = "";
  if (isset($unitMap[$unitName])) {
    $abbr = trim((string)$unitMap[$unitName]);
  }
  if ($abbr === "" && preg_match('/\(([^)]+)\)\s*$/', $unitName, $matches)) {
    $abbr = trim($matches[1]);
    $unitName = trim(preg_replace('/\s*\([^)]+\)\s*$/', '', $unitName));
  }
  if ($abbr === "" || $abbr === $unitName) {
    return $unitName;
  }
  return $unitName . " (" . $abbr . ")";
}

function uc_csv_escape($value): string {
  $value = (string)$value;
  if (strpbrk($value, "\",\r\n") !== false) {
    $value = str_replace("\"", "\"\"", $value);
    return "\"" . $value . "\"";
  }
  return $value;
}

function uc_slug($value): string {
  $value = (string)$value;
  $value = preg_replace('/[^A-Za-z0-9]+/', '-', $value);
  $value = trim($value ?? "", "-");
  return $value === "" ? "value" : $value;
}

function uc_gd_color($image, int $r, int $g, int $b) {
  return imagecolorallocate($image, $r, $g, $b);
}

function uc_gd_text($image, int $x, int $y, string $text, $color, int $size = 12, string $align = "left") {
  $fontPath = __DIR__ . "/assets/SpaceGrotesk-Regular.ttf";
  if (file_exists($fontPath)) {
    $bbox = imagettfbbox($size, 0, $fontPath, $text);
    $textWidth = $bbox[2] - $bbox[0];
    if ($align === "center") {
      $x -= (int)round($textWidth / 2);
    } elseif ($align === "right") {
      $x -= $textWidth;
    }
    imagettftext($image, $size, 0, $x, $y + $size, $color, $fontPath, $text);
    return;
  }
  $font = 3;
  if ($size >= 16) {
    $font = 5;
  } elseif ($size >= 14) {
    $font = 4;
  }
  $textWidth = imagefontwidth($font) * strlen($text);
  $textHeight = imagefontheight($font);
  if ($align === "center") {
    $x -= (int)round($textWidth / 2);
  } elseif ($align === "right") {
    $x -= $textWidth;
  }
  imagestring($image, $font, $x, $y, $text, $color);
}

function uc_gd_text_box(string $text, int $size): array {
  $fontPath = __DIR__ . "/assets/SpaceGrotesk-Regular.ttf";
  if (file_exists($fontPath)) {
    $bbox = imagettfbbox($size, 0, $fontPath, $text);
    $width = abs($bbox[2] - $bbox[0]);
    $height = abs($bbox[7] - $bbox[1]);
    return [$width, $height];
  }
  $font = 3;
  if ($size >= 16) {
    $font = 5;
  } elseif ($size >= 14) {
    $font = 4;
  }
  return [imagefontwidth($font) * strlen($text), imagefontheight($font)];
}

function uc_truncate_text(string $text, int $maxChars): string {
  $text = trim($text);
  if ($maxChars <= 0 || $text === "") {
    return $text;
  }
  if (strlen($text) <= $maxChars) {
    return $text;
  }
  if ($maxChars <= 3) {
    return substr($text, 0, $maxChars);
  }
  return substr($text, 0, $maxChars - 3) . "...";
}

function uc_gd_color_alpha($image, int $r, int $g, int $b, float $opacity) {
  $opacity = max(0.0, min(1.0, $opacity));
  $alpha = (int)round(127 * (1 - $opacity));
  return imagecolorallocatealpha($image, $r, $g, $b, $alpha);
}

function uc_gd_draw_radar($image, int $centerX, int $centerY, int $size, array $data, float $scale, array $colors): void {
  if ($size <= 0 || empty($data)) {
    return;
  }
  $count = count($data);
  if ($count < 3) {
    return;
  }
  $radius = ($size / 2) - (int)round(60 * $scale);
  if ($radius <= 0) {
    return;
  }
  $maxValue = 2.0;
  $midValue = 1.0;
  $midRatio = 0.4;
  $upperRings = 3;
  $angleStep = (M_PI * 2) / $count;

  $ringColor = $colors["grid"] ?? null;
  $axisColor = $colors["axis"] ?? null;
  $teamStroke = $colors["teamStroke"] ?? null;
  $teamFill = $colors["teamFill"] ?? null;
  $compareStroke = $colors["compareStroke"] ?? null;
  $compareFill = $colors["compareFill"] ?? null;
  $playerStroke = $colors["playerStroke"] ?? null;
  $playerFill = $colors["playerFill"] ?? null;
  $labelColor = $colors["label"] ?? null;

  $rings = [$midRatio];
  for ($i = 1; $i <= $upperRings; $i += 1) {
    $rings[] = $midRatio + ($i / ($upperRings + 1)) * (1 - $midRatio);
  }
  $rings[] = 1;

  foreach ($rings as $ratio) {
    $r = $radius * $ratio;
    $points = [];
    for ($i = 0; $i < $count; $i += 1) {
      $angle = ($i * $angleStep) - (M_PI / 2);
      $points[] = (int)round($centerX + cos($angle) * $r);
      $points[] = (int)round($centerY + sin($angle) * $r);
    }
    if ($ringColor !== null) {
      imagepolygon($image, $points, $count, $ringColor);
    }
  }

  if ($axisColor !== null) {
    for ($i = 0; $i < $count; $i += 1) {
      $angle = ($i * $angleStep) - (M_PI / 2);
      $x = (int)round($centerX + cos($angle) * $radius);
      $y = (int)round($centerY + sin($angle) * $radius);
      imageline($image, $centerX, $centerY, $x, $y, $axisColor);
    }
  }

  $normalizeValue = function (float $value) use ($midValue, $midRatio, $maxValue): float {
    if ($value <= $midValue) {
      return ($value / $midValue) * $midRatio;
    }
    return $midRatio + (($value - $midValue) / ($maxValue - $midValue)) * (1 - $midRatio);
  };

  $drawShape = function (array $values, $stroke, $fill) use ($image, $count, $angleStep, $centerX, $centerY, $radius, $normalizeValue) {
    if ($stroke === null || $fill === null) {
      return;
    }
    $points = [];
    for ($i = 0; $i < $count; $i += 1) {
      $value = isset($values[$i]) ? (float)$values[$i] : 0.0;
      $normalized = max(0.0, min($normalizeValue($value), 1.0));
      $angle = ($i * $angleStep) - (M_PI / 2);
      $points[] = (int)round($centerX + cos($angle) * $radius * $normalized);
      $points[] = (int)round($centerY + sin($angle) * $radius * $normalized);
    }
    imagefilledpolygon($image, $points, $count, $fill);
    imagepolygon($image, $points, $count, $stroke);
  };

  $teamValues = [];
  $compareValues = [];
  $playerValues = [];
  $hasCompare = false;
  foreach ($data as $item) {
    $teamValues[] = isset($item["team"]) ? (float)$item["team"] : 0.0;
    if (isset($item["playerB"])) {
      $hasCompare = true;
    }
    $compareValues[] = isset($item["playerB"]) ? (float)$item["playerB"] : 0.0;
    $playerValues[] = isset($item["player"]) ? (float)$item["player"] : 0.0;
  }
  if ($hasCompare) {
    $drawShape($teamValues, $teamStroke, $teamFill);
    $drawShape($compareValues, $compareStroke, $compareFill);
    $drawShape($playerValues, $playerStroke, $playerFill);
  } else {
    $drawShape($teamValues, $teamStroke, $teamFill);
    $drawShape($playerValues, $playerStroke, $playerFill);
  }

  if ($labelColor !== null) {
    $labelSize = (int)round(11 * $scale);
    $labelOffset = (int)round(6 * $scale);
    foreach ($data as $index => $item) {
      $label = trim((string)($item["label"] ?? ""));
      if ($label === "") {
        continue;
      }
      $angle = ($index * $angleStep) - (M_PI / 2);
      $x = $centerX + cos($angle) * ($radius + $labelOffset);
      $y = $centerY + sin($angle) * ($radius + $labelOffset);
      $align = "center";
      if ($x > $centerX + 5) {
        $align = "left";
      } elseif ($x < $centerX - 5) {
        $align = "right";
      }
      $textY = (int)round($y);
      if ($y > $centerY + 5) {
        $textY += (int)round(6 * $scale);
      } elseif ($y < $centerY - 5) {
        $textY -= (int)round(16 * $scale);
      } else {
        $textY -= (int)round(8 * $scale);
      }
      uc_gd_text($image, (int)round($x), $textY, $label, $labelColor, $labelSize, $align);
    }
  }
}

function uc_wrap_text(string $text, int $maxChars): array {
  $wrapped = wordwrap($text, $maxChars, "\n", true);
  return explode("\n", $wrapped);
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
if (!in_array($mode, ["view", "start", "results", "h2h"], true)) {
  $mode = "view";
}
$shareFormat = $_GET["share"] ?? "";
if (!in_array($shareFormat, ["csv", "img"], true)) {
  $shareFormat = "";
}
if ($shareFormat !== "" && $mode !== "h2h") {
  $mode = "results";
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
$infoTexts = [
  "weights" => "Gewichtungen legen fest, wie stark Kategorien und Disziplinen in die Gesamtwertung einfließen.\nKategorien Gewichtung beeinflussen den Einfluss auf den Gesamtscore, Disziplinen Gewichtung die Zusammensetzung des Scores dieser Kategorie.",
];
$formatTooltip = static function (string $text): string {
  return str_replace("\n", "&#10;", htmlspecialchars($text, ENT_QUOTES, "UTF-8"));
};
$formatLabel = static function (string $text): string {
  return htmlspecialchars(str_replace("\n", " ", $text), ENT_QUOTES, "UTF-8");
};

$filterGender = $_GET["gender"] ?? "";
if (!isset($genderOptions[$filterGender])) {
  $filterGender = "";
}
$filterPosition = $_GET["position"] ?? "";
if (!in_array($filterPosition, ["handler", "cutter"], true)) {
  $filterPosition = "";
}
$overallMode = $_GET["overall"] ?? "sum";
if (!in_array($overallMode, ["sum", "avg", "abs"], true)) {
  $overallMode = "sum";
}
$h2hPlayerAId = filter_var($_GET["player_a"] ?? null, FILTER_VALIDATE_INT);
$h2hPlayerBId = filter_var($_GET["player_b"] ?? null, FILTER_VALIDATE_INT);

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

  if ($action === "delete_combine") {
    $deleteId = filter_var($_POST["combine_id"] ?? null, FILTER_VALIDATE_INT);
    if ($deleteId) {
      $stmt = $pdo->prepare("DELETE FROM combines WHERE id = :id AND team_id = :team_id");
      $stmt->execute([
        ":id" => $deleteId,
        ":team_id" => $teamId,
      ]);
      header("Location: team.php");
      exit;
    }
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
     ORDER BY first_name ASC, last_name ASC"
  );
  $stmt->execute([":team_id" => $teamId]);
  $players = $stmt->fetchAll();

  try {
    $stmt = $pdo->prepare(
      "SELECT id, team_id, discipline_name, description, category, unit, rating_direction, expected_min, expected_max, bonus_relative, bonus_absolute
       FROM disciplines
       WHERE team_id = :team_id OR team_id IS NULL
       ORDER BY created_at DESC"
    );
    $stmt->execute([":team_id" => $teamId]);
    $disciplines = $stmt->fetchAll();
  } catch (Throwable $e) {
    $stmt = $pdo->prepare(
      "SELECT id, discipline_name, description, category, unit, rating_direction
       FROM disciplines
       WHERE team_id = :team_id OR team_id IS NULL
       ORDER BY created_at DESC"
    );
    $stmt->execute([":team_id" => $teamId]);
    $disciplines = $stmt->fetchAll();
  }

  $stmt = $pdo->prepare(
    "SELECT unit_name, unit_abbreviation, team_id
     FROM units
     WHERE team_id = :team_id OR team_id IS NULL
     ORDER BY (team_id IS NULL) ASC, unit_name ASC"
  );
  $stmt->execute([":team_id" => $teamId]);
  $units = $stmt->fetchAll();
  foreach ($units as $unitRow) {
    $unitName = trim((string)($unitRow["unit_name"] ?? ""));
    $unitAbbr = trim((string)($unitRow["unit_abbreviation"] ?? ""));
    if ($unitName === "" || $unitAbbr === "") {
      continue;
    }
    if (!isset($unitAbbrMap[$unitName])) {
      $unitAbbrMap[$unitName] = $unitAbbr;
    }
    if (!isset($unitAbbrMap[$unitAbbr])) {
      $unitAbbrMap[$unitAbbr] = $unitAbbr;
    }
    $comboKey = $unitName . " (" . $unitAbbr . ")";
    if (!isset($unitAbbrMap[$comboKey])) {
      $unitAbbrMap[$comboKey] = $unitAbbr;
    }
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
  $activeDisciplineUnitAbbr = "";
  foreach ($assignedDisciplines as $discipline) {
    if ((int)$discipline["id"] === (int)$activeDisciplineId) {
      $activeDisciplineDescription = $discipline["description"] ?? "";
      $activeDisciplineUnit = uc_format_unit_label($discipline["unit"] ?? "", $unitAbbrMap);
      $activeDisciplineUnitAbbr = uc_format_unit($discipline["unit"] ?? "", $unitAbbrMap);
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
    $activeDisciplineUnitAbbr = "";
    foreach ($assignedDisciplines as $discipline) {
      if ((int)$discipline["id"] === (int)$activeDisciplineId) {
        $activeDisciplineDescription = $discipline["description"] ?? "";
        $activeDisciplineUnit = uc_format_unit_label($discipline["unit"] ?? "", $unitAbbrMap);
        $activeDisciplineUnitAbbr = uc_format_unit($discipline["unit"] ?? "", $unitAbbrMap);
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

if (!$pageError && !$combineError && in_array($mode, ["results", "h2h"], true)) {
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

if ($shareFormat !== "" && !$pageError && !$combineError) {
  $shareTeam = uc_slug($teamName);
  $shareCombine = uc_slug($combine["combine_name"] ?? "combine");
  $shareDate = uc_slug($combine["event_date"] ?? "");
  $shareFileBase = $shareTeam . "-" . $shareCombine;
  if ($shareDate !== "value") {
    $shareFileBase .= "-" . $shareDate;
  }
  $disciplinesForExport = $assignedDisciplines;
  $headers = ["Spieler", "Trikotnummer", "Geschlecht", "Positionen"];
  foreach ($disciplinesForExport as $discipline) {
    $label = $discipline["discipline_name"] ?? "Disziplin";
    $unitLabel = uc_format_unit($discipline["unit"] ?? "", $unitAbbrMap);
    if ($unitLabel !== "") {
      $label .= " (" . $unitLabel . ")";
    }
    $headers[] = $label;
  }
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
  if ($shareFormat === "csv") {
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"" . $shareFileBase . ".csv\"");
    echo implode(",", array_map("uc_csv_escape", $headers)) . "\r\n";
    foreach ($filteredPlayers as $player) {
      $playerId = (int)$player["id"];
      $positions = [];
      if (!empty($player["position_handler"])) {
        $positions[] = "Handler";
      }
      if (!empty($player["position_cutter"])) {
        $positions[] = "Cutter";
      }
      $positionsLabel = empty($positions) ? "-" : implode(" / ", $positions);
      $row = [
        trim(($player["first_name"] ?? "") . " " . ($player["last_name"] ?? "")),
        $player["jersey_number"] !== null ? (string)$player["jersey_number"] : "-",
        $player["gender"] ?? "-",
        $positionsLabel,
      ];
      foreach ($disciplinesForExport as $discipline) {
        $discId = (int)$discipline["id"];
        $value = $resultsByDiscipline[$discId][$playerId] ?? null;
        $row[] = uc_display_value($value, "-");
      }
      echo implode(",", array_map("uc_csv_escape", $row)) . "\r\n";
    }
    exit;
  }

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
  $overallScoresAbs = [];
  $overallCategoryCounts = [];
  $categoryAverages = [];
  $categoryAveragesAbs = [];
  $categoryAveragesAvg = [];
  $categoryTeamAverages = [];
  $categoryTeamWeightedAverages = [];
  $categoryTeamAveragesAbs = [];
  $categoryTeamWeightedAveragesAbs = [];
  $categoryTeamAveragesAvg = [];
  foreach ($filteredPlayers as $player) {
    $playerId = (int)$player["id"];
    $overallScoresSum[$playerId] = 0;
    $overallScoresAvg[$playerId] = 0;
    $overallScoresAbs[$playerId] = 0;
    $overallCategoryCounts[$playerId] = 0;
  }

  foreach ($assignedDisciplinesByCategory as $category => $categoryDisciplines) {
    $categoryWeight = $combineCategoryWeights[$category] ?? 1.0;
    if ($categoryWeight <= 0) {
      $categoryWeight = 1.0;
    }
    $disciplineCount = 0;
    $categoryTotals = [];
    $categoryTotalsAbs = [];
    $categoryTotalsAvg = [];
    $categoryWeightSumAll = 0.0;
    $categoryWeightSumAllAbs = 0.0;
    $categoryWeightSumsAvg = [];
    foreach ($filteredPlayers as $player) {
      $playerId = (int)$player["id"];
      $categoryTotals[$playerId] = 0;
      $categoryTotalsAbs[$playerId] = 0;
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
      $expectedMinValue = uc_value_to_float($discipline["expected_min"] ?? null);
      $expectedMaxValue = uc_value_to_float($discipline["expected_max"] ?? null);
      $bonusRel = uc_bonus_value($discipline["bonus_relative"] ?? null);
      $bonusAbs = uc_bonus_value($discipline["bonus_absolute"] ?? null);
      $hasAbsolute = $expectedMinValue !== null && $expectedMaxValue !== null;
      if ($overallMode === "abs" && !$hasAbsolute) {
        continue;
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
      if ($hasAbsolute) {
        $categoryWeightSumAllAbs += $disciplineWeight;
      }
      foreach ($filteredPlayers as $player) {
        $playerId = (int)$player["id"];
        $numericValue = $rankValues[$playerId] ?? null;
        $pointsBase = 0;
        if ($numericValue === null || $bestValue === null || $worstValue === null) {
          $pointsBase = 0;
        } elseif ($bestValue == $worstValue) {
          $pointsBase = 2;
        } else {
          $ratio = ($numericValue - $worstValue) / ($bestValue - $worstValue);
          $pointsBase = 1 + $ratio;
        }
        $pointsSum = $pointsBase;
        if ($overallMode === "sum" && $bonusRel > 0 && $numericValue !== null && $bestValue !== null && $numericValue == $bestValue) {
          $pointsSum += $bonusRel;
        }
        $categoryTotals[$playerId] += $pointsSum * $disciplineWeight;
        if ($hasAbsolute) {
          $absolutePoints = uc_absolute_points($numericValue, $expectedMinValue, $expectedMaxValue, $direction);
          if ($absolutePoints === null) {
            $absolutePoints = 0;
          }
          if ($bonusAbs > 0 && uc_absolute_bonus_applies($numericValue, $expectedMaxValue, $direction)) {
            $absolutePoints += $bonusAbs;
          }
          $categoryTotalsAbs[$playerId] += $absolutePoints * $disciplineWeight;
        }
        if ($numericValue !== null && $bestValue !== null && $worstValue !== null) {
          $categoryTotalsAvg[$playerId] += $pointsBase * $disciplineWeight;
          $categoryWeightSumsAvg[$playerId] += $disciplineWeight;
        }
      }
    }
    if ($disciplineCount === 0 || $categoryWeightSumAll <= 0) {
      continue;
    }
    $teamSum = 0;
    $teamCount = 0;
    $teamSumAbs = 0;
    $teamCountAbs = 0;
    $teamSumAvg = 0;
    $teamCountAvg = 0;
    $hasAbsoluteCategory = $categoryWeightSumAllAbs > 0;
    foreach ($filteredPlayers as $player) {
      $playerId = (int)$player["id"];
      $categoryAverage = $categoryTotals[$playerId] / $categoryWeightSumAll;
      $overallScoresSum[$playerId] += $categoryAverage * $categoryWeight;
      $categoryAverages[$category][$playerId] = $categoryAverage;
      $teamSum += $categoryAverage;
      $teamCount++;
      if ($categoryWeightSumAllAbs > 0) {
        $categoryAverageAbs = $categoryTotalsAbs[$playerId] / $categoryWeightSumAllAbs;
        $overallScoresAbs[$playerId] += $categoryAverageAbs * $categoryWeight;
        $categoryAveragesAbs[$category][$playerId] = $categoryAverageAbs;
        if ($hasAbsoluteCategory) {
          $teamSumAbs += $categoryAverageAbs;
          $teamCountAbs++;
        }
      }
      $avgWeightSum = $categoryWeightSumsAvg[$playerId] ?? 0.0;
      if ($avgWeightSum > 0) {
        $categoryAverageAvg = $categoryTotalsAvg[$playerId] / $avgWeightSum;
        $overallScoresAvg[$playerId] += $categoryAverageAvg;
        $overallCategoryCounts[$playerId] += 1;
        $categoryAveragesAvg[$category][$playerId] = $categoryAverageAvg;
        $teamSumAvg += $categoryAverageAvg;
        $teamCountAvg++;
      }
    }
    if ($teamCount > 0) {
      $categoryTeamAverages[$category] = $teamSum / $teamCount;
      $categoryTeamWeightedAverages[$category] = ($teamSum / $teamCount) * $categoryWeight;
    }
    if ($teamCountAbs > 0) {
      $categoryTeamAveragesAbs[$category] = $teamSumAbs / $teamCountAbs;
      $categoryTeamWeightedAveragesAbs[$category] = ($teamSumAbs / $teamCountAbs) * $categoryWeight;
    }
    if ($teamCountAvg > 0) {
      $categoryTeamAveragesAvg[$category] = $teamSumAvg / $teamCountAvg;
    }
  }
  foreach ($overallScoresAvg as $playerId => $score) {
    $count = $overallCategoryCounts[$playerId] ?? 0;
    if ($count > 0) {
      $overallScoresAvg[$playerId] = $score / $count;
    }
  }
  if ($overallMode === "avg") {
    $overallScores = $overallScoresAvg;
  } elseif ($overallMode === "abs") {
    $overallScores = $overallScoresAbs;
  } else {
    $overallScores = $overallScoresSum;
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

  $baseWidth = 720;
  $scale = 2;
  $imageWidth = (int)round($baseWidth * $scale);
  $padding = (int)round(40 * $scale);
  $lineHeight = (int)round(20 * $scale);
  $unitLineHeight = (int)round(14 * $scale);
  $cardGap = (int)round(22 * $scale);
  $headerHeight = (int)round(96 * $scale);
  $cardPadding = (int)round(20 * $scale);
  $cardWidth = $imageWidth - ($padding * 2);
  $categoryTitleHeight = (int)round(22 * $scale);
  $disciplineTitleHeight = (int)round(22 * $scale);
  $disciplineGap = (int)round(14 * $scale);

  $rowsOverall = max(1, count($filteredPlayers));
  $heightOverall = 48 + ($rowsOverall * $lineHeight) + $cardPadding * 2;

  $categoryBlocks = [];
  foreach ($assignedDisciplinesByCategory as $category => $categoryDisciplines) {
    $displayDisciplines = $categoryDisciplines;
    if ($overallMode === "abs") {
      $displayDisciplines = array_values(array_filter($categoryDisciplines, function ($discipline) {
        $minValue = uc_value_to_float($discipline["expected_min"] ?? null);
        $maxValue = uc_value_to_float($discipline["expected_max"] ?? null);
        return $minValue !== null && $maxValue !== null;
      }));
    }
    if (empty($displayDisciplines)) {
      continue;
    }
    $categoryWeight = $combineCategoryWeights[$category] ?? 1.0;
    if ($categoryWeight <= 0) {
      $categoryWeight = 1.0;
    }
    $showCategoryWeight = (float)$categoryWeight !== 1.0;
    $discEntries = [];
    $blockHeight = $cardPadding * 2 + $categoryTitleHeight;
    foreach ($displayDisciplines as $discipline) {
      $discId = (int)$discipline["id"];
      $disciplineWeight = $combineDisciplineWeights[$discId] ?? 1.0;
      if ($disciplineWeight <= 0) {
        $disciplineWeight = 1.0;
      }
      $showDisciplineWeight = (float)$disciplineWeight !== 1.0;
      $direction = $discipline["rating_direction"] ?? "more";
      if ($direction !== "less" && $direction !== "more") {
        $direction = "more";
      }
      $expectedMinValue = uc_value_to_float($discipline["expected_min"] ?? null);
      $expectedMaxValue = uc_value_to_float($discipline["expected_max"] ?? null);
      $bonusRel = uc_bonus_value($discipline["bonus_relative"] ?? null);
      $bonusAbs = uc_bonus_value($discipline["bonus_absolute"] ?? null);
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
        $values = array_values($rankValues);
        if ($direction === "less") {
          $bestValue = min($values);
          $worstValue = max($values);
        } else {
          $bestValue = max($values);
          $worstValue = min($values);
        }
      }
      $rows = [];
      foreach ($filteredPlayers as $player) {
        $playerId = (int)$player["id"];
        $playerName = trim(($player["first_name"] ?? "") . " " . ($player["last_name"] ?? ""));
        $value = $resultsByDiscipline[$discId][$playerId] ?? null;
        $numericValue = uc_value_to_float($value);
        if ($overallMode === "abs") {
          $points = uc_absolute_points($numericValue, $expectedMinValue, $expectedMaxValue, $direction);
          if ($points === null) {
            $points = 0;
          }
          if ($bonusAbs > 0 && uc_absolute_bonus_applies($numericValue, $expectedMaxValue, $direction)) {
            $points += $bonusAbs;
          }
        } elseif ($numericValue === null || $bestValue === null || $worstValue === null) {
          $points = 0;
        } elseif ($bestValue == $worstValue) {
          $points = 2;
        } else {
          $ratio = ($numericValue - $worstValue) / ($bestValue - $worstValue);
          $points = 1 + $ratio;
        }
        if ($overallMode === "sum" && $bonusRel > 0 && $numericValue !== null && $bestValue !== null && $numericValue == $bestValue) {
          $points += $bonusRel;
        }
        $rows[] = [
          "player_id" => $playerId,
          "name" => $playerName,
          "value" => $value,
          "points" => $points,
        ];
      }
      usort($rows, function ($a, $b) {
        if ($a["points"] == $b["points"]) {
          return strcasecmp($a["name"], $b["name"]);
        }
        return $a["points"] < $b["points"] ? 1 : -1;
      });
      $ranks = [];
      $pos = 0;
      $rank = 0;
      $prev = null;
      foreach ($rows as $row) {
        $pos++;
        if ($prev === null || $row["points"] != $prev) {
          $rank = $pos;
          $prev = $row["points"];
        }
        $ranks[$row["player_id"]] = $rank;
      }
      $unitAbbr = uc_format_unit($discipline["unit"] ?? "", $unitAbbrMap);
      $unitLabel = uc_format_unit_label($discipline["unit"] ?? "", $unitAbbrMap);
      $discLabel = $discipline["discipline_name"] ?? "Disziplin";
      $discHeight = $disciplineTitleHeight + (max(1, count($rows)) * $lineHeight);
      if ($unitLabel !== "") {
        $discHeight += $unitLineHeight;
      }
      $blockHeight += $discHeight + $disciplineGap;
      $discEntries[] = [
        "label" => $discLabel,
        "weight" => $disciplineWeight,
        "show_weight" => $showDisciplineWeight,
        "rows" => $rows,
        "ranks" => $ranks,
        "unit_abbr" => $unitAbbr,
        "unit_label" => $unitLabel,
      ];
    }
    $categoryBlocks[] = [
      "category" => $category,
      "weight" => $categoryWeight,
      "show_weight" => $showCategoryWeight,
      "disciplines" => $discEntries,
      "discipline_count" => count($discEntries),
      "height" => $blockHeight,
    ];
  }
  $modeLabel = $overallMode === "abs" ? "Absolut" : ($overallMode === "avg" ? "Ø Relativ" : "Relativ");
  $modeHelp = $overallMode === "abs"
    ? "Absolut: Punkte anhand Erwartungs-Min/Max. Disziplinen ohne Erwartungswerte werden nicht berücksichtigt."
    : ($overallMode === "avg"
      ? "Ø Relativ: Es zählen nur Kategorien und Disziplinen, die dieser Spieler absolviert hat. Punkte werden relativ zu den Teilnehmern berechnet."
      : "Relativ: Punkte werden relativ zu den Teilnehmern berechnet. Nicht absolvierte Disziplinen zählen als 0 in den Kategorien.");
  $modeHelpLines = uc_wrap_text($modeHelp, 80);
  $headerExtraLines = count($modeHelpLines);
  $filterLinesCount = 0;
  if ($filterGender !== "" || $filterPosition !== "") {
    $filterParts = [];
    if ($filterGender !== "") {
      $filterParts[] = "Geschlecht: " . ($genderOptions[$filterGender] ?? $filterGender);
    }
    if ($filterPosition !== "") {
      $filterParts[] = "Position: " . ($filterPosition === "handler" ? "Handler" : "Cutter");
    }
    $filterLabel = "Filter: " . implode(" · ", $filterParts);
    $filterLinesCount = count(uc_wrap_text($filterLabel, 80));
  }
  $headerHeight = (int)round(96 * $scale) + (($headerExtraLines + $filterLinesCount) * (int)round(14 * $scale));

  $playerShareRequested = $shareFormat === "img" && $selectedPlayerId && $selectedPlayer;
  if ($playerShareRequested) {
    $playerName = trim(($selectedPlayer["first_name"] ?? "") . " " . ($selectedPlayer["last_name"] ?? ""));
    $playerSlug = uc_slug($playerName);
    $overallPoints = $overallScores[$selectedPlayerId] ?? 0;
    $overallRank = $overallRanks[$selectedPlayerId] ?? "-";
    $overallPointsPrefix = $overallMode === "avg" ? "Ø " : "";
    $overallPointsLabel = $overallPointsPrefix . uc_format_points($overallPoints) . " P";
    $overallRankLabel = "Platz " . $overallRank;

    $radarData = [];
    $radarPlayerAverages = $categoryAverages;
    $radarTeamAverages = $categoryTeamWeightedAverages;
    $radarApplyWeight = true;
    if ($overallMode === "abs") {
      $radarPlayerAverages = $categoryAveragesAbs;
      $radarTeamAverages = $categoryTeamWeightedAveragesAbs;
      $radarApplyWeight = true;
    } elseif ($overallMode === "avg") {
      $radarPlayerAverages = $categoryAveragesAvg;
      $radarTeamAverages = $categoryTeamAveragesAvg;
      $radarApplyWeight = false;
    }
    foreach ($radarPlayerAverages as $category => $playerAverages) {
      $categoryWeight = $combineCategoryWeights[$category] ?? 1;
      if ($categoryWeight <= 0) {
        $categoryWeight = 1;
      }
      $playerAverage = $playerAverages[$selectedPlayerId] ?? 0;
      if ($radarApplyWeight) {
        $playerAverage *= $categoryWeight;
      }
      $teamAverage = $radarTeamAverages[$category] ?? 0;
      $radarData[] = [
        "label" => $category,
        "player" => $playerAverage,
        "team" => $teamAverage,
      ];
    }

    $playerCategoryBlocks = [];
    $playerLineHeight = (int)round(20 * $scale);
    $playerTitleHeight = (int)round(20 * $scale);
    $playerScoreHeight = (int)round(18 * $scale);
    foreach ($assignedDisciplinesByCategory as $category => $categoryDisciplines) {
      $displayDisciplines = $categoryDisciplines;
      if ($overallMode === "abs") {
        $displayDisciplines = array_values(array_filter($categoryDisciplines, function ($discipline) {
          $minValue = uc_value_to_float($discipline["expected_min"] ?? null);
          $maxValue = uc_value_to_float($discipline["expected_max"] ?? null);
          return $minValue !== null && $maxValue !== null;
        }));
      }
      if (empty($displayDisciplines)) {
        continue;
      }
      $categoryWeight = $combineCategoryWeights[$category] ?? 1.0;
      if ($categoryWeight <= 0) {
        $categoryWeight = 1.0;
      }
      $showCategoryWeight = (float)$categoryWeight !== 1.0;
      $showDisciplineWeights = false;
      foreach ($displayDisciplines as $discipline) {
        $discId = (int)$discipline["id"];
        $discWeight = $combineDisciplineWeights[$discId] ?? 1.0;
        if ((float)$discWeight !== 1.0) {
          $showDisciplineWeights = true;
          break;
        }
      }
      $rows = [];
      $rowsHeightSum = 0;
      foreach ($displayDisciplines as $discipline) {
        $discId = (int)$discipline["id"];
        $direction = $discipline["rating_direction"] ?? "more";
        if ($direction !== "less" && $direction !== "more") {
          $direction = "more";
        }
        $unitAbbr = uc_format_unit($discipline["unit"] ?? "", $unitAbbrMap);
        $unitLabel = uc_format_unit_label($discipline["unit"] ?? "", $unitAbbrMap);
        $disciplineWeight = $combineDisciplineWeights[$discId] ?? 1.0;
        if ($disciplineWeight <= 0) {
          $disciplineWeight = 1.0;
        }
        $expectedMinValue = uc_value_to_float($discipline["expected_min"] ?? null);
        $expectedMaxValue = uc_value_to_float($discipline["expected_max"] ?? null);
        $bonusRel = uc_bonus_value($discipline["bonus_relative"] ?? null);
        $bonusAbs = uc_bonus_value($discipline["bonus_absolute"] ?? null);
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
        $completedCount = count($rankValues);
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
        if ($display !== "-" && $unitAbbr !== "") {
          $display .= " " . $unitAbbr;
        }
        $numericValue = $rankValues[$selectedPlayerId] ?? null;
        if ($overallMode === "abs") {
          $points = uc_absolute_points($numericValue, $expectedMinValue, $expectedMaxValue, $direction);
          if ($points === null) {
            $points = 0;
          }
          if ($bonusAbs > 0 && uc_absolute_bonus_applies($numericValue, $expectedMaxValue, $direction)) {
            $points += $bonusAbs;
          }
        } elseif ($numericValue === null || $bestValue === null || $worstValue === null) {
          $points = 0;
        } elseif ($bestValue == $worstValue) {
          $points = 2;
        } else {
          $ratio = ($numericValue - $worstValue) / ($bestValue - $worstValue);
          $points = 1 + $ratio;
        }
        if ($overallMode === "sum" && $bonusRel > 0 && $numericValue !== null && $bestValue !== null && $numericValue == $bestValue) {
          $points += $bonusRel;
        }
        $pointsLabel = uc_format_points($points) . " P";
        $rankLabel = isset($ranks[$selectedPlayerId]) ? (string)$ranks[$selectedPlayerId] : "-";
        $discLabel = $discipline["discipline_name"] ?? "Disziplin";
        $leftText = $discLabel;
        if ($showDisciplineWeights) {
          $leftText .= " (" . uc_display_value($disciplineWeight, "") . "x)";
        }
        if ($overallMode !== "abs" && $numericValue === null) {
          $rightText = "0 P";
        } else {
          $rightText = "Platz " . $rankLabel . " (" . $completedCount . ") · " . $pointsLabel;
        }
        $rows[] = [
          "left" => $leftText,
          "right" => $rightText,
          "value" => $display,
          "unit_label" => $unitLabel,
        ];
        $rowsHeightSum += (int)round($playerLineHeight * 2);
        if ($unitLabel !== "") {
          $rowsHeightSum += $unitLineHeight;
        }
      }
      if (empty($rows)) {
        continue;
      }
      $categoryLabel = $category;
      if ($showCategoryWeight) {
        $categoryLabel .= " (" . uc_display_value($categoryWeight, "") . "x)";
      }
      $categoryScore = $categoryAverages[$category][$selectedPlayerId] ?? null;
      $categoryScoreLabel = $categoryScore === null ? "-" : uc_format_points($categoryScore) . " P";
      $blockHeight = $cardPadding * 2 + $playerTitleHeight + $rowsHeightSum;
      if (count($rows) > 1) {
        $blockHeight += $playerScoreHeight;
      }
      $playerCategoryBlocks[] = [
        "category" => $categoryLabel,
        "score" => $categoryScoreLabel,
        "show_score" => count($rows) > 1,
        "rows" => $rows,
        "rows_count" => count($rows),
        "height" => $blockHeight,
      ];
    }

    $colGap = (int)round(20 * $scale);
    $colWidth = (int)floor(($cardWidth - $colGap) / 2);
    $colHeights = [0, 0];
    $colRowCounts = [0, 0];
    $totalRows = 0;
    foreach ($playerCategoryBlocks as $block) {
      $totalRows += $block["rows_count"];
    }
    $targetLeft = (int)ceil($totalRows / 2);
    $playerColumns = [[], []];
    foreach ($playerCategoryBlocks as $block) {
      $takeLeft = ($colRowCounts[0] + $block["rows_count"]) <= $targetLeft;
      $colIndex = $takeLeft ? 0 : 1;
      $playerColumns[$colIndex][] = $block;
      $colHeights[$colIndex] += $block["height"] + $cardGap;
      $colRowCounts[$colIndex] += $block["rows_count"];
    }
    $categoriesHeight = max($colHeights[0], $colHeights[1]);

    $radarSize = (int)round(320 * $scale);
    $summaryGap = (int)round(24 * $scale);
    $summaryNameSize = (int)round(20 * $scale);
    $summaryMetaSize = (int)round(14 * $scale);
    $summaryInfoSize = (int)round(12 * $scale);
    $summaryLineGap = (int)round(10 * $scale);
    $nameLines = uc_wrap_text($playerName, 26);
    $summaryHeight = 0;
    $summaryHeight += count($nameLines) * ($summaryNameSize + $summaryLineGap);
    $summaryHeight += ($summaryMetaSize + $summaryLineGap);
    $summaryHeight = max(0, $summaryHeight - $summaryLineGap);

    $playerCardHeight = max($radarSize + ($cardPadding * 2), $summaryHeight + ($cardPadding * 2));
    $height = $padding + $headerHeight + $cardGap + $playerCardHeight;
    if ($categoriesHeight > 0) {
      $height += $cardGap + $categoriesHeight;
    }
    $height += $padding;

    $image = imagecreatetruecolor($imageWidth, $height);
    imageantialias($image, true);
    imagealphablending($image, true);
    imagesavealpha($image, true);
    $bg = uc_gd_color($image, 247, 244, 239);
    $white = uc_gd_color($image, 255, 255, 255);
    $ink = uc_gd_color($image, 31, 26, 20);
    $muted = uc_gd_color($image, 111, 98, 89);
    $accent = uc_gd_color($image, 255, 123, 75);
    $accentDark = uc_gd_color($image, 44, 42, 74);
    $whiteText = uc_gd_color($image, 255, 255, 255);

    imagefilledrectangle($image, 0, 0, $imageWidth, $height, $bg);

    $x = $padding;
    $y = $padding;
    $title = $combine["combine_name"] ?? "Combine";
    $metaParts = [];
    if ($teamName) {
      $metaParts[] = $teamName;
    }
    if (!empty($combine["event_date"])) {
      $metaParts[] = $combine["event_date"];
    }
    if (!empty($combine["combine_location"])) {
      $metaParts[] = $combine["combine_location"];
    }
    $subtitle = implode(" · ", $metaParts);
    $filterParts = [];
    if ($filterGender !== "") {
      $filterParts[] = "Geschlecht: " . ($genderOptions[$filterGender] ?? $filterGender);
    }
    if ($filterPosition !== "") {
      $filterParts[] = "Position: " . ($filterPosition === "handler" ? "Handler" : "Cutter");
    }
    $filterLabel = "";
    if (!empty($filterParts)) {
      $filterLabel = "Filter: " . implode(" · ", $filterParts);
    }
    $brandX = $x;
    $brandY = $y;
    $logoPath = __DIR__ . "/assets/FrisbeeCatch.png";
    if (file_exists($logoPath)) {
      $logo = @imagecreatefrompng($logoPath);
      if ($logo) {
        $logoSize = (int)round(36 * $scale);
        imagecopyresampled($image, $logo, $brandX, $brandY, 0, 0, $logoSize, $logoSize, imagesx($logo), imagesy($logo));
        if ($logo instanceof GdImage || is_resource($logo)) {
          imagedestroy($logo);
        } else {
          unset($logo);
        }
        $brandX += $logoSize + (int)round(10 * $scale);
      }
    }
    uc_gd_text($image, $brandX, $brandY + (int)round(4 * $scale), "Ultimate-Combine.de", $accentDark, (int)round(16 * $scale), "left");
    uc_gd_text($image, $x, $y + (int)round(36 * $scale), $title, $ink, (int)round(26 * $scale), "left");
    uc_gd_text($image, $x, $y + (int)round(66 * $scale), $subtitle, $muted, (int)round(13 * $scale), "left");
    $helpY = $y + (int)round(88 * $scale);
    foreach ($modeHelpLines as $line) {
      uc_gd_text($image, $x, $helpY, $line, $muted, (int)round(11 * $scale), "left");
      $helpY += (int)round(14 * $scale);
    }
    if ($filterLabel !== "") {
      $filterLines = uc_wrap_text($filterLabel, 80);
      foreach ($filterLines as $line) {
        uc_gd_text($image, $x, $helpY, $line, $muted, (int)round(11 * $scale), "left");
        $helpY += (int)round(14 * $scale);
      }
    }
    uc_gd_text($image, $imageWidth - $padding, $y, $modeLabel, $accentDark, (int)round(13 * $scale), "right");

    $y += $headerHeight + $cardGap;
    $cardY = $y;
    imagefilledrectangle($image, $x, $cardY, $x + $cardWidth, $cardY + $playerCardHeight, $white);

    $radarX = $x + $cardPadding;
    $radarY = $cardY + $cardPadding;
    $radarCenterX = $radarX + (int)round($radarSize / 2);
    $radarCenterY = $radarY + (int)round($radarSize / 2);
    $radarColors = [
      "grid" => uc_gd_color_alpha($image, 44, 42, 74, 0.2),
      "axis" => uc_gd_color_alpha($image, 44, 42, 74, 0.25),
      "teamStroke" => $accentDark,
      "teamFill" => uc_gd_color_alpha($image, 44, 42, 74, 0.15),
      "playerStroke" => $accent,
      "playerFill" => uc_gd_color_alpha($image, 255, 123, 75, 0.22),
      "label" => $muted,
    ];
    if (!empty($radarData)) {
      uc_gd_draw_radar($image, $radarCenterX, $radarCenterY, $radarSize, $radarData, $scale, $radarColors);
    } else {
      uc_gd_text($image, $radarCenterX, $radarCenterY - (int)round(6 * $scale), "Keine Daten", $muted, (int)round(12 * $scale), "center");
    }

    $legendX = $radarX + (int)round(12 * $scale);
    $legendY = $radarY + (int)round(12 * $scale);
    $legendDot = (int)round(8 * $scale);
    imagefilledellipse($image, $legendX, $legendY, $legendDot, $legendDot, $accent);
    uc_gd_text($image, $legendX + (int)round(10 * $scale), $legendY - (int)round(10 * $scale), "Spieler", $muted, (int)round(11 * $scale), "left");
    $legendY += (int)round(18 * $scale);
    imagefilledellipse($image, $legendX, $legendY, $legendDot, $legendDot, $accentDark);
    uc_gd_text($image, $legendX + (int)round(10 * $scale), $legendY - (int)round(10 * $scale), "Team", $muted, (int)round(11 * $scale), "left");

    $summaryX = $radarX + $radarSize + $summaryGap;
    $summaryY = $cardY + $cardPadding;
    foreach ($nameLines as $line) {
      uc_gd_text($image, $summaryX, $summaryY, $line, $ink, $summaryNameSize, "left");
      $summaryY += $summaryNameSize + $summaryLineGap;
    }
    uc_gd_text($image, $summaryX, $summaryY, $overallRankLabel . " · " . $overallPointsLabel, $accentDark, $summaryMetaSize, "left");

    $y = $cardY + $playerCardHeight + $cardGap;
    for ($col = 0; $col < 2; $col++) {
      $colX = $x + ($col === 0 ? 0 : $colWidth + $colGap);
      $colY = $y;
      foreach ($playerColumns[$col] as $block) {
        imagefilledrectangle($image, $colX, $colY, $colX + $colWidth, $colY + $block["height"], $white);
        uc_gd_text($image, $colX + $cardPadding, $colY + $cardPadding, strtoupper($block["category"]), $accentDark, (int)round(11 * $scale), "left");
        $cursorY = $colY + $cardPadding + (int)round(18 * $scale);
        if ($block["show_score"]) {
          uc_gd_text($image, $colX + $cardPadding, $cursorY, "Kategorie-Score: " . $block["score"], $muted, (int)round(11 * $scale), "left");
          $cursorY += $playerScoreHeight;
        }
        foreach ($block["rows"] as $row) {
          $leftText = uc_truncate_text($row["left"], 46);
          uc_gd_text($image, $colX + $cardPadding, $cursorY, $leftText, $ink, (int)round(11 * $scale), "left");
          $cursorY += (int)round($playerLineHeight * 0.9);
          if (!empty($row["unit_label"])) {
            uc_gd_text($image, $colX + $cardPadding, $cursorY, (string)$row["unit_label"], $muted, (int)round(10 * $scale), "left");
            $cursorY += $unitLineHeight;
          }
          $detailText = $row["value"];
          if ($detailText !== "" && $row["right"] !== "") {
            $detailText .= " · " . $row["right"];
          } elseif ($row["right"] !== "") {
            $detailText = $row["right"];
          }
          uc_gd_text($image, $colX + $cardPadding, $cursorY, $detailText, $muted, (int)round(11 * $scale), "left");
          $cursorY += (int)round($playerLineHeight * 1.1);
        }
        $colY += $block["height"] + $cardGap;
      }
    }

    $shareFile = $shareFileBase . "-" . $playerSlug;
    header("Content-Type: image/png");
    header("Content-Disposition: attachment; filename=\"" . $shareFile . ".png\"");
    imagepng($image);
    imagedestroy($image);
    exit;
  }

  $h2hShareRequested = $shareFormat === "img" && $mode === "h2h" && $h2hPlayerAId && $h2hPlayerBId;
  if ($h2hShareRequested) {
    $playerMap = [];
    foreach ($assignedPlayers as $player) {
      $playerMap[(int)$player["id"]] = $player;
    }
    $h2hPlayerA = $playerMap[$h2hPlayerAId] ?? null;
    $h2hPlayerB = $playerMap[$h2hPlayerBId] ?? null;
    if (!$h2hPlayerA || !$h2hPlayerB || $h2hPlayerAId === $h2hPlayerBId) {
      $h2hShareRequested = false;
    } else {
      $overallScoresSum = [];
      $overallScoresAvg = [];
      $overallScoresAbs = [];
      $overallCategoryCounts = [];
      foreach ($assignedPlayers as $player) {
        $playerId = (int)$player["id"];
        $overallScoresSum[$playerId] = 0;
        $overallScoresAvg[$playerId] = 0;
        $overallScoresAbs[$playerId] = 0;
        $overallCategoryCounts[$playerId] = 0;
      }
      foreach ($assignedDisciplinesByCategory as $category => $categoryDisciplines) {
        $categoryWeight = $combineCategoryWeights[$category] ?? 1.0;
        if ($categoryWeight <= 0) {
          $categoryWeight = 1.0;
        }
        $disciplineCount = 0;
        $categoryTotals = [];
        $categoryTotalsAbs = [];
        $categoryTotalsAvg = [];
        $categoryWeightSumAll = 0.0;
        $categoryWeightSumAllAbs = 0.0;
        $categoryWeightSumsAvg = [];
        foreach ($assignedPlayers as $player) {
          $playerId = (int)$player["id"];
          $categoryTotals[$playerId] = 0;
          $categoryTotalsAbs[$playerId] = 0;
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
          $expectedMinValue = uc_value_to_float($discipline["expected_min"] ?? null);
          $expectedMaxValue = uc_value_to_float($discipline["expected_max"] ?? null);
          $bonusRel = uc_bonus_value($discipline["bonus_relative"] ?? null);
          $bonusAbs = uc_bonus_value($discipline["bonus_absolute"] ?? null);
          $hasAbsolute = $expectedMinValue !== null && $expectedMaxValue !== null;
          if ($overallMode === "abs" && !$hasAbsolute) {
            continue;
          }
          $rankValues = [];
          foreach ($assignedPlayers as $player) {
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
          if ($hasAbsolute) {
            $categoryWeightSumAllAbs += $disciplineWeight;
          }
          foreach ($assignedPlayers as $player) {
            $playerId = (int)$player["id"];
            $numericValue = $rankValues[$playerId] ?? null;
            $pointsBase = 0;
            if ($numericValue === null || $bestValue === null || $worstValue === null) {
              $pointsBase = 0;
            } elseif ($bestValue == $worstValue) {
              $pointsBase = 2;
            } else {
              $ratio = ($numericValue - $worstValue) / ($bestValue - $worstValue);
              $pointsBase = 1 + $ratio;
            }
            $pointsSum = $pointsBase;
            if ($overallMode === "sum" && $bonusRel > 0 && $numericValue !== null && $bestValue !== null && $numericValue == $bestValue) {
              $pointsSum += $bonusRel;
            }
            $categoryTotals[$playerId] += $pointsSum * $disciplineWeight;
            if ($hasAbsolute) {
              $absolutePoints = uc_absolute_points($numericValue, $expectedMinValue, $expectedMaxValue, $direction);
              if ($absolutePoints === null) {
                $absolutePoints = 0;
              }
              if ($bonusAbs > 0 && uc_absolute_bonus_applies($numericValue, $expectedMaxValue, $direction)) {
                $absolutePoints += $bonusAbs;
              }
              $categoryTotalsAbs[$playerId] += $absolutePoints * $disciplineWeight;
            }
            if ($numericValue !== null && $bestValue !== null && $worstValue !== null) {
              $categoryTotalsAvg[$playerId] += $pointsBase * $disciplineWeight;
              $categoryWeightSumsAvg[$playerId] += $disciplineWeight;
            }
          }
        }
        if ($disciplineCount === 0 || $categoryWeightSumAll <= 0) {
          continue;
        }
        foreach ($assignedPlayers as $player) {
          $playerId = (int)$player["id"];
          $categoryAverage = $categoryTotals[$playerId] / $categoryWeightSumAll;
          $overallScoresSum[$playerId] += $categoryAverage * $categoryWeight;
          if ($categoryWeightSumAllAbs > 0) {
            $categoryAverageAbs = $categoryTotalsAbs[$playerId] / $categoryWeightSumAllAbs;
            $overallScoresAbs[$playerId] += $categoryAverageAbs * $categoryWeight;
          }
          $avgWeightSum = $categoryWeightSumsAvg[$playerId] ?? 0.0;
          if ($avgWeightSum > 0) {
            $categoryAverageAvg = $categoryTotalsAvg[$playerId] / $avgWeightSum;
            $overallScoresAvg[$playerId] += $categoryAverageAvg;
            $overallCategoryCounts[$playerId] += 1;
          }
        }
      }
      foreach ($overallScoresAvg as $playerId => $score) {
        $count = $overallCategoryCounts[$playerId] ?? 0;
        if ($count > 0) {
          $overallScoresAvg[$playerId] = $score / $count;
        }
      }
      if ($overallMode === "avg") {
        $overallScores = $overallScoresAvg;
      } elseif ($overallMode === "abs") {
        $overallScores = $overallScoresAbs;
      } else {
        $overallScores = $overallScoresSum;
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

      $playerALabel = trim(($h2hPlayerA["first_name"] ?? "") . " " . ($h2hPlayerA["last_name"] ?? ""));
      $playerBLabel = trim(($h2hPlayerB["first_name"] ?? "") . " " . ($h2hPlayerB["last_name"] ?? ""));
      $overallPointsPrefix = $overallMode === "avg" ? "Ø " : "";
      $overallPointsA = $overallScores[$h2hPlayerAId] ?? 0;
      $overallPointsB = $overallScores[$h2hPlayerBId] ?? 0;
      $overallRankA = $overallRanks[$h2hPlayerAId] ?? "-";
      $overallRankB = $overallRanks[$h2hPlayerBId] ?? "-";

      $h2hRadarData = [];
      foreach ($assignedDisciplinesByCategory as $category => $categoryDisciplines) {
        $displayDisciplines = $categoryDisciplines;
        if ($overallMode === "abs") {
          $displayDisciplines = array_values(array_filter($categoryDisciplines, function ($discipline) {
            $minValue = uc_value_to_float($discipline["expected_min"] ?? null);
            $maxValue = uc_value_to_float($discipline["expected_max"] ?? null);
            return $minValue !== null && $maxValue !== null;
          }));
        }
        if (empty($displayDisciplines)) {
          continue;
        }
        $categoryWeight = $combineCategoryWeights[$category] ?? 1.0;
        if ($categoryWeight <= 0) {
          $categoryWeight = 1.0;
        }
        $weightSum = 0.0;
        $weightSumAbs = 0.0;
        $sumA = 0.0;
        $sumB = 0.0;
        $sumAbsA = 0.0;
        $sumAbsB = 0.0;
        $sumAvgA = 0.0;
        $sumAvgB = 0.0;
        $sumAvgWeightA = 0.0;
        $sumAvgWeightB = 0.0;
        $categoryTotalsTeam = [];
        $categoryTotalsAbsTeam = [];
        $categoryTotalsAvgTeam = [];
        $categoryWeightSumsAvgTeam = [];
        foreach ($assignedPlayers as $player) {
          $playerId = (int)$player["id"];
          $categoryTotalsTeam[$playerId] = 0.0;
          $categoryTotalsAbsTeam[$playerId] = 0.0;
          $categoryTotalsAvgTeam[$playerId] = 0.0;
          $categoryWeightSumsAvgTeam[$playerId] = 0.0;
        }
        foreach ($displayDisciplines as $discipline) {
          $discId = (int)$discipline["id"];
          $disciplineWeight = $combineDisciplineWeights[$discId] ?? 1.0;
          if ($disciplineWeight <= 0) {
            $disciplineWeight = 1.0;
          }
          $direction = $discipline["rating_direction"] ?? "more";
          if ($direction !== "less" && $direction !== "more") {
            $direction = "more";
          }
          $expectedMinValue = uc_value_to_float($discipline["expected_min"] ?? null);
          $expectedMaxValue = uc_value_to_float($discipline["expected_max"] ?? null);
          $bonusRel = uc_bonus_value($discipline["bonus_relative"] ?? null);
          $bonusAbs = uc_bonus_value($discipline["bonus_absolute"] ?? null);
          $hasAbsolute = $expectedMinValue !== null && $expectedMaxValue !== null;
          $rankValues = [];
          foreach ($assignedPlayers as $player) {
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
            $weightSum += $disciplineWeight;
            if ($hasAbsolute) {
              $weightSumAbs += $disciplineWeight;
            }
            $values = array_values($rankValues);
            if ($direction === "less") {
              $bestValue = min($values);
              $worstValue = max($values);
            } else {
              $bestValue = max($values);
              $worstValue = min($values);
            }
          }
          $numericA = isset($rankValues[$h2hPlayerAId]) ? $rankValues[$h2hPlayerAId] : null;
          $numericB = isset($rankValues[$h2hPlayerBId]) ? $rankValues[$h2hPlayerBId] : null;
          $pointsBaseA = 0;
          $pointsBaseB = 0;
          if ($numericA === null || $bestValue === null || $worstValue === null) {
            $pointsBaseA = 0;
          } elseif ($bestValue == $worstValue) {
            $pointsBaseA = 2;
          } else {
            $ratioA = ($numericA - $worstValue) / ($bestValue - $worstValue);
            $pointsBaseA = 1 + $ratioA;
          }
          if ($numericB === null || $bestValue === null || $worstValue === null) {
            $pointsBaseB = 0;
          } elseif ($bestValue == $worstValue) {
            $pointsBaseB = 2;
          } else {
            $ratioB = ($numericB - $worstValue) / ($bestValue - $worstValue);
            $pointsBaseB = 1 + $ratioB;
          }
          $pointsSumA = $pointsBaseA;
          $pointsSumB = $pointsBaseB;
          if ($overallMode === "sum" && $bonusRel > 0 && $bestValue !== null) {
            if ($numericA !== null && $numericA == $bestValue) {
              $pointsSumA += $bonusRel;
            }
            if ($numericB !== null && $numericB == $bestValue) {
              $pointsSumB += $bonusRel;
            }
          }
          $sumA += $pointsSumA * $disciplineWeight;
          $sumB += $pointsSumB * $disciplineWeight;
          if ($hasAbsolute) {
            $pointsAbsA = uc_absolute_points($numericA, $expectedMinValue, $expectedMaxValue, $direction);
            $pointsAbsB = uc_absolute_points($numericB, $expectedMinValue, $expectedMaxValue, $direction);
            if ($pointsAbsA === null) { $pointsAbsA = 0; }
            if ($pointsAbsB === null) { $pointsAbsB = 0; }
            if ($bonusAbs > 0 && uc_absolute_bonus_applies($numericA, $expectedMaxValue, $direction)) {
              $pointsAbsA += $bonusAbs;
            }
            if ($bonusAbs > 0 && uc_absolute_bonus_applies($numericB, $expectedMaxValue, $direction)) {
              $pointsAbsB += $bonusAbs;
            }
            $sumAbsA += $pointsAbsA * $disciplineWeight;
            $sumAbsB += $pointsAbsB * $disciplineWeight;
          }
          if ($numericA !== null && $bestValue !== null && $worstValue !== null) {
            $sumAvgA += $pointsBaseA * $disciplineWeight;
            $sumAvgWeightA += $disciplineWeight;
          }
          if ($numericB !== null && $bestValue !== null && $worstValue !== null) {
            $sumAvgB += $pointsBaseB * $disciplineWeight;
            $sumAvgWeightB += $disciplineWeight;
          }
          foreach ($assignedPlayers as $player) {
            $playerId = (int)$player["id"];
            $numeric = $rankValues[$playerId] ?? null;
            $pointsBase = 0;
            if ($numeric === null || $bestValue === null || $worstValue === null) {
              $pointsBase = 0;
            } elseif ($bestValue == $worstValue) {
              $pointsBase = 2;
            } else {
              $ratio = ($numeric - $worstValue) / ($bestValue - $worstValue);
              $pointsBase = 1 + $ratio;
            }
            $pointsSum = $pointsBase;
            if ($overallMode === "sum" && $bonusRel > 0 && $bestValue !== null && $numeric !== null && $numeric == $bestValue) {
              $pointsSum += $bonusRel;
            }
            $categoryTotalsTeam[$playerId] += $pointsSum * $disciplineWeight;
            if ($hasAbsolute) {
              $pointsAbs = uc_absolute_points($numeric, $expectedMinValue, $expectedMaxValue, $direction);
              if ($pointsAbs === null) {
                $pointsAbs = 0;
              }
              if ($bonusAbs > 0 && uc_absolute_bonus_applies($numeric, $expectedMaxValue, $direction)) {
                $pointsAbs += $bonusAbs;
              }
              $categoryTotalsAbsTeam[$playerId] += $pointsAbs * $disciplineWeight;
            }
            if ($numeric !== null && $bestValue !== null && $worstValue !== null) {
              $categoryTotalsAvgTeam[$playerId] += $pointsBase * $disciplineWeight;
              $categoryWeightSumsAvgTeam[$playerId] += $disciplineWeight;
            }
          }
        }
        $radarA = 0.0;
        $radarB = 0.0;
        $radarTeam = 0.0;
        $hasRadar = false;
        if ($overallMode === "avg") {
          if ($sumAvgWeightA > 0 || $sumAvgWeightB > 0) {
            $radarA = $sumAvgWeightA > 0 ? $sumAvgA / $sumAvgWeightA : 0;
            $radarB = $sumAvgWeightB > 0 ? $sumAvgB / $sumAvgWeightB : 0;
            $hasRadar = true;
          }
          $teamSum = 0.0;
          $teamCount = 0;
          foreach ($assignedPlayers as $player) {
            $playerId = (int)$player["id"];
            $teamWeightSum = $categoryWeightSumsAvgTeam[$playerId] ?? 0.0;
            if ($teamWeightSum > 0) {
              $teamSum += $categoryTotalsAvgTeam[$playerId] / $teamWeightSum;
              $teamCount++;
            }
          }
          if ($teamCount > 0) {
            $radarTeam = $teamSum / $teamCount;
            $hasRadar = true;
          }
        } elseif ($overallMode === "abs") {
          if ($weightSumAbs > 0) {
            $radarA = $sumAbsA / $weightSumAbs;
            $radarB = $sumAbsB / $weightSumAbs;
            $hasRadar = true;
          }
          if ($weightSumAbs > 0) {
            $teamSum = 0.0;
            $teamCount = 0;
            foreach ($assignedPlayers as $player) {
              $playerId = (int)$player["id"];
              $teamSum += $categoryTotalsAbsTeam[$playerId] / $weightSumAbs;
              $teamCount++;
            }
            if ($teamCount > 0) {
              $radarTeam = $teamSum / $teamCount;
              $hasRadar = true;
            }
          }
        } else {
          if ($weightSum > 0) {
            $radarA = $sumA / $weightSum;
            $radarB = $sumB / $weightSum;
            $hasRadar = true;
          }
          if ($weightSum > 0) {
            $teamSum = 0.0;
            $teamCount = 0;
            foreach ($assignedPlayers as $player) {
              $playerId = (int)$player["id"];
              $teamSum += $categoryTotalsTeam[$playerId] / $weightSum;
              $teamCount++;
            }
            if ($teamCount > 0) {
              $radarTeam = $teamSum / $teamCount;
              $hasRadar = true;
            }
          }
        }
        if ($hasRadar) {
          if ($overallMode !== "avg") {
            $radarA *= $categoryWeight;
            $radarB *= $categoryWeight;
            $radarTeam *= $categoryWeight;
          }
          $h2hRadarData[] = [
            "label" => $category,
            "player" => $radarA,
            "playerB" => $radarB,
            "team" => $radarTeam,
          ];
        }
      }

      $h2hCategoryBlocks = [];
      $rowHeight = (int)round(20 * $scale);
      $unitLineHeight = (int)round(14 * $scale);
      $categoryTitleHeight = (int)round(22 * $scale);
      $barHeight = (int)round(18 * $scale);
      $barGap = (int)round(10 * $scale);
      $barValueOffset = (int)round(4 * $scale);
      foreach ($assignedDisciplinesByCategory as $category => $categoryDisciplines) {
        $displayDisciplines = $categoryDisciplines;
        if ($overallMode === "abs") {
          $displayDisciplines = array_values(array_filter($categoryDisciplines, function ($discipline) {
            $minValue = uc_value_to_float($discipline["expected_min"] ?? null);
            $maxValue = uc_value_to_float($discipline["expected_max"] ?? null);
            return $minValue !== null && $maxValue !== null;
          }));
        }
        if (empty($displayDisciplines)) {
          continue;
        }
        $rows = [];
        foreach ($displayDisciplines as $discipline) {
          $discId = (int)$discipline["id"];
          $direction = $discipline["rating_direction"] ?? "more";
          if ($direction !== "less" && $direction !== "more") {
            $direction = "more";
          }
          $unitAbbr = uc_format_unit($discipline["unit"] ?? "", $unitAbbrMap);
          $unitLabel = uc_format_unit_label($discipline["unit"] ?? "", $unitAbbrMap);
          $expectedMinValue = uc_value_to_float($discipline["expected_min"] ?? null);
          $expectedMaxValue = uc_value_to_float($discipline["expected_max"] ?? null);
          $bonusRel = uc_bonus_value($discipline["bonus_relative"] ?? null);
          $bonusAbs = uc_bonus_value($discipline["bonus_absolute"] ?? null);
          $rankValues = [];
          foreach ($assignedPlayers as $player) {
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
            $values = array_values($rankValues);
            if ($direction === "less") {
              $bestValue = min($values);
              $worstValue = max($values);
            } else {
              $bestValue = max($values);
              $worstValue = min($values);
            }
          }
          $playerAValue = $resultsByDiscipline[$discId][$h2hPlayerAId] ?? null;
          $playerBValue = $resultsByDiscipline[$discId][$h2hPlayerBId] ?? null;
          $numericA = uc_value_to_float($playerAValue);
          $numericB = uc_value_to_float($playerBValue);
          if ($overallMode === "abs") {
            $pointsA = uc_absolute_points($numericA, $expectedMinValue, $expectedMaxValue, $direction);
            $pointsB = uc_absolute_points($numericB, $expectedMinValue, $expectedMaxValue, $direction);
            if ($pointsA === null) { $pointsA = 0; }
            if ($pointsB === null) { $pointsB = 0; }
            if ($bonusAbs > 0 && uc_absolute_bonus_applies($numericA, $expectedMaxValue, $direction)) {
              $pointsA += $bonusAbs;
            }
            if ($bonusAbs > 0 && uc_absolute_bonus_applies($numericB, $expectedMaxValue, $direction)) {
              $pointsB += $bonusAbs;
            }
          } else {
            if ($numericA === null || $bestValue === null || $worstValue === null) {
              $pointsA = 0;
            } elseif ($bestValue == $worstValue) {
              $pointsA = 2;
            } else {
              $ratioA = ($numericA - $worstValue) / ($bestValue - $worstValue);
              $pointsA = 1 + $ratioA;
            }
            if ($numericB === null || $bestValue === null || $worstValue === null) {
              $pointsB = 0;
            } elseif ($bestValue == $worstValue) {
              $pointsB = 2;
            } else {
              $ratioB = ($numericB - $worstValue) / ($bestValue - $worstValue);
              $pointsB = 1 + $ratioB;
            }
            if ($overallMode === "sum" && $bonusRel > 0 && $bestValue !== null) {
              if ($numericA !== null && $numericA == $bestValue) {
                $pointsA += $bonusRel;
              }
              if ($numericB !== null && $numericB == $bestValue) {
                $pointsB += $bonusRel;
              }
            }
          }
          $displayA = uc_display_value($playerAValue, "-");
          $displayB = uc_display_value($playerBValue, "-");
          if ($displayA !== "-" && $unitAbbr !== "") { $displayA .= " " . $unitAbbr; }
          if ($displayB !== "-" && $unitAbbr !== "") { $displayB .= " " . $unitAbbr; }
          $scaleScore = function ($value) {
            $value = max(0.0, min(2.0, (float)$value));
            if ($value <= 1) {
              return ($value / 1) * 30;
            }
            return 30 + (($value - 1) / 1) * 70;
          };
          $percentA = $scaleScore($pointsA);
          $percentB = $scaleScore($pointsB);
          $rows[] = [
            "label" => $discipline["discipline_name"] ?? "Disziplin",
            "a" => $displayA,
            "b" => $displayB,
            "percentA" => $percentA,
            "percentB" => $percentB,
            "unit_label" => $unitLabel,
          ];
        }
        if (empty($rows)) {
          continue;
        }
        $unitRows = 0;
        foreach ($rows as $row) {
          if (!empty($row["unit_label"])) {
            $unitRows++;
          }
        }
        $blockHeight = $cardPadding * 2 + $categoryTitleHeight + (count($rows) * ($rowHeight + ($barHeight * 2) + $barGap)) + ($unitRows * $unitLineHeight);
        $h2hCategoryBlocks[] = [
          "category" => $category,
          "rows" => $rows,
          "height" => $blockHeight,
        ];
      }

      $radarSize = (int)round(320 * $scale);
      $summaryGap = (int)round(24 * $scale);
      $summaryNameSize = (int)round(18 * $scale);
      $summaryMetaSize = (int)round(13 * $scale);
      $summaryLineGap = (int)round(8 * $scale);
      $summaryHeight = ($summaryNameSize + $summaryLineGap) * 4;
      $summaryHeight = max(0, $summaryHeight - $summaryLineGap);
      $summaryCardHeight = max($radarSize + ($cardPadding * 2), $summaryHeight + ($cardPadding * 2));

      $h2hTitleSize = (int)round(16 * $scale);
      $h2hTitleHeight = (int)round(24 * $scale);
      $height = $padding + $headerHeight + $cardGap + $h2hTitleHeight + $summaryCardHeight;
      foreach ($h2hCategoryBlocks as $block) {
        $height += $cardGap + $block["height"];
      }
      $height += $padding;

      $image = imagecreatetruecolor($imageWidth, $height);
      imageantialias($image, true);
      imagealphablending($image, true);
      imagesavealpha($image, true);
      $bg = uc_gd_color($image, 247, 244, 239);
      $white = uc_gd_color($image, 255, 255, 255);
      $ink = uc_gd_color($image, 31, 26, 20);
      $muted = uc_gd_color($image, 111, 98, 89);
      $accent = uc_gd_color($image, 255, 123, 75);
      $accentDark = uc_gd_color($image, 44, 42, 74);
      $whiteText = uc_gd_color($image, 255, 255, 255);

      imagefilledrectangle($image, 0, 0, $imageWidth, $height, $bg);

      $x = $padding;
      $y = $padding;
      $title = $combine["combine_name"] ?? "Combine";
      $metaParts = [];
      if ($teamName) {
        $metaParts[] = $teamName;
      }
      if (!empty($combine["event_date"])) {
        $metaParts[] = $combine["event_date"];
      }
      if (!empty($combine["combine_location"])) {
        $metaParts[] = $combine["combine_location"];
      }
      $subtitle = implode(" · ", $metaParts);
      $brandX = $x;
      $brandY = $y;
      $logoPath = __DIR__ . "/assets/FrisbeeCatch.png";
      if (file_exists($logoPath)) {
        $logo = @imagecreatefrompng($logoPath);
        if ($logo) {
          $logoSize = (int)round(36 * $scale);
          imagecopyresampled($image, $logo, $brandX, $brandY, 0, 0, $logoSize, $logoSize, imagesx($logo), imagesy($logo));
          if ($logo instanceof GdImage || is_resource($logo)) {
            imagedestroy($logo);
          } else {
            unset($logo);
          }
          $brandX += $logoSize + (int)round(10 * $scale);
        }
      }
      uc_gd_text($image, $brandX, $brandY + (int)round(4 * $scale), "Ultimate-Combine.de", $accentDark, (int)round(16 * $scale), "left");
      uc_gd_text($image, $x, $y + (int)round(36 * $scale), $title, $ink, (int)round(26 * $scale), "left");
      uc_gd_text($image, $x, $y + (int)round(66 * $scale), $subtitle, $muted, (int)round(13 * $scale), "left");
      $helpY = $y + (int)round(88 * $scale);
      foreach ($modeHelpLines as $line) {
        uc_gd_text($image, $x, $helpY, $line, $muted, (int)round(11 * $scale), "left");
        $helpY += (int)round(14 * $scale);
      }
      uc_gd_text($image, $imageWidth - $padding, $y, $modeLabel, $accentDark, (int)round(13 * $scale), "right");

      $y += $headerHeight + $cardGap;
      $nameLine = $playerALabel . " vs. " . $playerBLabel;
      $nameSize = $h2hTitleSize;
      $minNameSize = (int)round(11 * $scale);
      $maxNameWidth = $radarSize - (int)round(20 * $scale);
      while ($nameSize > $minNameSize) {
        [$nameWidth] = uc_gd_text_box($nameLine, $nameSize);
        if ($nameWidth <= $maxNameWidth) {
          break;
        }
        $nameSize -= 1;
      }
      $nameX = $x + (int)round($radarSize / 2) + $cardPadding;
      $nameY = $y + (int)round(2 * $scale);
      uc_gd_text($image, $nameX, $nameY, $nameLine, $accentDark, $nameSize, "center");

      $y += $h2hTitleHeight;
      imagefilledrectangle($image, $x, $y, $x + $cardWidth, $y + $summaryCardHeight, $white);

      $radarX = $x + $cardPadding;
      $radarY = $y + $cardPadding;
      $radarCenterX = $radarX + (int)round($radarSize / 2);
      $radarCenterY = $radarY + (int)round($radarSize / 2);
      $radarColors = [
        "grid" => uc_gd_color_alpha($image, 44, 42, 74, 0.2),
        "axis" => uc_gd_color_alpha($image, 44, 42, 74, 0.25),
        "teamStroke" => $muted,
        "teamFill" => uc_gd_color_alpha($image, 111, 98, 89, 0.18),
        "compareStroke" => $accentDark,
        "compareFill" => uc_gd_color_alpha($image, 44, 42, 74, 0.2),
        "playerStroke" => $accent,
        "playerFill" => uc_gd_color_alpha($image, 255, 123, 75, 0.22),
        "label" => $muted,
      ];
      if (!empty($h2hRadarData)) {
        uc_gd_draw_radar($image, $radarCenterX, $radarCenterY, $radarSize, $h2hRadarData, $scale, $radarColors);
      } else {
        uc_gd_text($image, $radarCenterX, $radarCenterY - (int)round(6 * $scale), "Keine Daten", $muted, (int)round(12 * $scale), "center");
      }

      $legendX = $radarX + (int)round(12 * $scale);
      $legendY = $radarY + (int)round(12 * $scale);
      $legendDot = (int)round(8 * $scale);
      imagefilledellipse($image, $legendX, $legendY, $legendDot, $legendDot, $accent);
      uc_gd_text($image, $legendX + (int)round(10 * $scale), $legendY - (int)round(10 * $scale), $playerALabel, $muted, (int)round(11 * $scale), "left");
      $legendY += (int)round(18 * $scale);
      imagefilledellipse($image, $legendX, $legendY, $legendDot, $legendDot, $accentDark);
      uc_gd_text($image, $legendX + (int)round(10 * $scale), $legendY - (int)round(10 * $scale), $playerBLabel, $muted, (int)round(11 * $scale), "left");
      $legendY += (int)round(18 * $scale);
      imagefilledellipse($image, $legendX, $legendY, $legendDot, $legendDot, $muted);
      uc_gd_text($image, $legendX + (int)round(10 * $scale), $legendY - (int)round(10 * $scale), "Team", $muted, (int)round(11 * $scale), "left");

      $summaryX = $radarX + $radarSize + $summaryGap;
      $summaryY = $y + $cardPadding;
      uc_gd_text($image, $summaryX, $summaryY, $playerALabel, $ink, $summaryNameSize, "left");
      $summaryY += $summaryNameSize + $summaryLineGap;
      uc_gd_text($image, $summaryX, $summaryY, "Platz " . $overallRankA . " · " . $overallPointsPrefix . uc_format_points($overallPointsA) . " P", $accentDark, $summaryMetaSize, "left");
      $summaryY += $summaryMetaSize + (int)round(14 * $scale);
      uc_gd_text($image, $summaryX, $summaryY, $playerBLabel, $ink, $summaryNameSize, "left");
      $summaryY += $summaryNameSize + $summaryLineGap;
      uc_gd_text($image, $summaryX, $summaryY, "Platz " . $overallRankB . " · " . $overallPointsPrefix . uc_format_points($overallPointsB) . " P", $accentDark, $summaryMetaSize, "left");

      $y += $summaryCardHeight + $cardGap;
      foreach ($h2hCategoryBlocks as $block) {
        imagefilledrectangle($image, $x, $y, $x + $cardWidth, $y + $block["height"], $white);
        uc_gd_text($image, $x + $cardPadding, $y + $cardPadding, strtoupper($block["category"]), $accentDark, (int)round(11 * $scale), "left");
        $cursorY = $y + $cardPadding + (int)round(18 * $scale);
        foreach ($block["rows"] as $row) {
          $label = uc_truncate_text($row["label"], 40);
          uc_gd_text($image, $x + $cardPadding, $cursorY, $label, $ink, (int)round(11 * $scale), "left");
          $cursorY += $rowHeight;
          if (!empty($row["unit_label"])) {
            uc_gd_text($image, $x + $cardPadding, $cursorY, (string)$row["unit_label"], $muted, (int)round(10 * $scale), "left");
            $cursorY += $unitLineHeight;
          }

          $barX = $x + $cardPadding;
          $barWidth = $cardWidth - ($cardPadding * 2);
          $barY = $cursorY;
          imagefilledrectangle($image, $barX, $barY, $barX + $barWidth, $barY + $barHeight, uc_gd_color_alpha($image, 44, 42, 74, 0.08));
          $fillA = (int)round($barWidth * ($row["percentA"] / 100));
          imagefilledrectangle($image, $barX, $barY, $barX + $fillA, $barY + $barHeight, $accent);
          $valueSize = (int)round(10 * $scale);
          $valuePaddingX = (int)round(6 * $scale);
          $valuePaddingY = (int)round(3 * $scale);
          $valueX = $barX + $barValueOffset;
          $valueY = $barY + (int)round(2 * $scale);
          $valueText = (string)$row["a"];
          [$valueW, $valueH] = uc_gd_text_box($valueText, $valueSize);
          $valueBg = uc_gd_color_alpha($image, 255, 255, 255, 0.92);
          imagefilledrectangle(
            $image,
            $valueX,
            $valueY,
            $valueX + $valueW + ($valuePaddingX * 2),
            $valueY + $valueH + ($valuePaddingY * 2),
            $valueBg
          );
          uc_gd_text($image, $valueX + $valuePaddingX, $valueY + $valuePaddingY, $valueText, $ink, $valueSize, "left");
          $cursorY += $barHeight + $barGap;

          $barY = $cursorY;
          imagefilledrectangle($image, $barX, $barY, $barX + $barWidth, $barY + $barHeight, uc_gd_color_alpha($image, 44, 42, 74, 0.08));
          $fillB = (int)round($barWidth * ($row["percentB"] / 100));
          imagefilledrectangle($image, $barX, $barY, $barX + $fillB, $barY + $barHeight, $accentDark);
          $valueText = (string)$row["b"];
          [$valueW, $valueH] = uc_gd_text_box($valueText, $valueSize);
          imagefilledrectangle(
            $image,
            $valueX,
            $barY + (int)round(2 * $scale),
            $valueX + $valueW + ($valuePaddingX * 2),
            $barY + (int)round(2 * $scale) + $valueH + ($valuePaddingY * 2),
            $valueBg
          );
          uc_gd_text($image, $valueX + $valuePaddingX, $barY + (int)round(2 * $scale) + $valuePaddingY, $valueText, $ink, $valueSize, "left");
          $cursorY += $barHeight + $barGap;
        }
        $y += $block["height"] + $cardGap;
      }

      $shareFile = $shareFileBase . "-h2h-" . uc_slug($playerALabel) . "-" . uc_slug($playerBLabel);
      header("Content-Type: image/png");
      header("Content-Disposition: attachment; filename=\"" . $shareFile . ".png\"");
      imagepng($image);
      imagedestroy($image);
      exit;
    }
  }

  $height = $padding + $headerHeight + $cardGap + $heightOverall;
  foreach ($categoryBlocks as $block) {
    $height += $cardGap + $block["height"];
  }
  $height += $padding;

  $colGap = (int)round(20 * $scale);
  $colWidth = (int)floor(($cardWidth - $colGap) / 2);
  $colHeights = [0, 0];
  $colDisciplineCounts = [0, 0];
  $totalDisciplines = 0;
  foreach ($categoryBlocks as $block) {
    $totalDisciplines += $block["discipline_count"];
  }
  $targetLeft = (int)ceil($totalDisciplines / 2);
  $categoryColumns = [[], []];
  foreach ($categoryBlocks as $block) {
    $takeLeft = ($colDisciplineCounts[0] + $block["discipline_count"]) <= $targetLeft;
    $colIndex = $takeLeft ? 0 : 1;
    $categoryColumns[$colIndex][] = $block;
    $colHeights[$colIndex] += $block["height"] + $cardGap;
    $colDisciplineCounts[$colIndex] += $block["discipline_count"];
  }
  $categoriesHeight = max($colHeights[0], $colHeights[1]);
  $height = $padding + $headerHeight + $cardGap + $heightOverall + $cardGap + $categoriesHeight + $padding;

  $image = imagecreatetruecolor($imageWidth, $height);
  imageantialias($image, true);
  $bg = uc_gd_color($image, 247, 244, 239);
  $white = uc_gd_color($image, 255, 255, 255);
  $ink = uc_gd_color($image, 31, 26, 20);
  $muted = uc_gd_color($image, 111, 98, 89);
  $accent = uc_gd_color($image, 255, 123, 75);
  $accentDark = uc_gd_color($image, 44, 42, 74);
  $rankGold = uc_gd_color($image, 212, 175, 55);
  $rankSilver = uc_gd_color($image, 192, 192, 192);
  $rankBronze = uc_gd_color($image, 205, 127, 50);
  $whiteText = uc_gd_color($image, 255, 255, 255);

  imagefilledrectangle($image, 0, 0, $imageWidth, $height, $bg);

  $x = $padding;
  $y = $padding;
  $title = $combine["combine_name"] ?? "Combine";
  $metaParts = [];
  if ($teamName) {
    $metaParts[] = $teamName;
  }
  if (!empty($combine["event_date"])) {
    $metaParts[] = $combine["event_date"];
  }
  if (!empty($combine["combine_location"])) {
    $metaParts[] = $combine["combine_location"];
  }
  $subtitle = implode(" · ", $metaParts);
  $filterParts = [];
  if ($filterGender !== "") {
    $filterParts[] = "Geschlecht: " . ($genderOptions[$filterGender] ?? $filterGender);
  }
  if ($filterPosition !== "") {
    $filterParts[] = "Position: " . ($filterPosition === "handler" ? "Handler" : "Cutter");
  }
  $filterLabel = "";
  if (!empty($filterParts)) {
    $filterLabel = "Filter: " . implode(" · ", $filterParts);
  }
  $brandX = $x;
  $brandY = $y;
  $logoPath = __DIR__ . "/assets/FrisbeeCatch.png";
  if (file_exists($logoPath)) {
    $logo = @imagecreatefrompng($logoPath);
    if ($logo) {
      $logoSize = (int)round(36 * $scale);
      imagecopyresampled($image, $logo, $brandX, $brandY, 0, 0, $logoSize, $logoSize, imagesx($logo), imagesy($logo));
      if ($logo instanceof GdImage || is_resource($logo)) {
        imagedestroy($logo);
      } else {
        unset($logo);
      }
      $brandX += $logoSize + (int)round(10 * $scale);
    }
  }
  uc_gd_text($image, $brandX, $brandY + (int)round(4 * $scale), "Ultimate-Combine.de", $accentDark, (int)round(16 * $scale), "left");
  uc_gd_text($image, $x, $y + (int)round(36 * $scale), $title, $ink, (int)round(26 * $scale), "left");
  uc_gd_text($image, $x, $y + (int)round(66 * $scale), $subtitle, $muted, (int)round(13 * $scale), "left");
  $helpY = $y + (int)round(88 * $scale);
  foreach ($modeHelpLines as $line) {
    uc_gd_text($image, $x, $helpY, $line, $muted, (int)round(11 * $scale), "left");
    $helpY += (int)round(14 * $scale);
  }
  if ($filterLabel !== "") {
    $filterLines = uc_wrap_text($filterLabel, 80);
    foreach ($filterLines as $line) {
      uc_gd_text($image, $x, $helpY, $line, $muted, (int)round(11 * $scale), "left");
      $helpY += (int)round(14 * $scale);
    }
  }
  uc_gd_text($image, $imageWidth - $padding, $y, $modeLabel, $accentDark, (int)round(13 * $scale), "right");

  $y += $headerHeight + $cardGap;

  $cardY = $y;
  imagefilledrectangle($image, $x, $cardY, $x + $cardWidth, $cardY + $heightOverall, $white);
  uc_gd_text($image, $x + $cardPadding, $cardY + $cardPadding, "Overall", $accentDark, (int)round(16 * $scale), "left");
  $rowY = $cardY + $cardPadding + (int)round(26 * $scale);
  foreach ($overallRankValues as $playerId => $score) {
    foreach ($filteredPlayers as $player) {
      if ((int)$player["id"] === (int)$playerId) {
        $playerName = trim(($player["first_name"] ?? "") . " " . ($player["last_name"] ?? ""));
        $rankLabel = $overallRanks[$playerId] ?? "-";
        $scoreLabel = ($overallMode === "avg" ? "Ø " : "") . uc_format_points($score) . " P";
        $textX = $x + $cardPadding;
        if (in_array((int)$rankLabel, [1, 2, 3], true)) {
          $rankColor = $rankGold;
          if ((int)$rankLabel === 2) {
            $rankColor = $rankSilver;
          } elseif ((int)$rankLabel === 3) {
            $rankColor = $rankBronze;
          }
          $circleX = $textX + (int)round(8 * $scale);
          $circleY = $rowY + (int)round(6 * $scale);
          $circleSize = (int)round(18 * $scale);
          imagefilledellipse($image, $circleX, $circleY, $circleSize, $circleSize, $rankColor);
          uc_gd_text($image, $circleX, $circleY - (int)round(6 * $scale), (string)$rankLabel, $whiteText, (int)round(11 * $scale), "center");
          $textX += (int)round(22 * $scale);
          uc_gd_text($image, $textX, $rowY, $playerName, $ink, (int)round(12 * $scale), "left");
        } else {
          uc_gd_text($image, $textX, $rowY, $rankLabel . ". " . $playerName, $ink, (int)round(12 * $scale), "left");
        }
        uc_gd_text($image, $x + $cardWidth - $cardPadding, $rowY, $scoreLabel, $ink, (int)round(12 * $scale), "right");
        $rowY += $lineHeight;
        break;
      }
    }
  }

  $y = $cardY + $heightOverall + $cardGap;
  for ($col = 0; $col < 2; $col++) {
    $colX = $x + ($col === 0 ? 0 : $colWidth + $colGap);
    $colY = $y;
    foreach ($categoryColumns[$col] as $block) {
      $categoryLabel = $block["category"];
      if ($block["show_weight"]) {
        $categoryLabel .= " (" . uc_display_value($block["weight"], "") . "x)";
      }
      imagefilledrectangle($image, $colX, $colY, $colX + $colWidth, $colY + $block["height"], $white);
      uc_gd_text($image, $colX + $cardPadding, $colY + $cardPadding, strtoupper($categoryLabel), $accentDark, (int)round(11 * $scale), "left");
      $cursorY = $colY + $cardPadding + (int)round(18 * $scale);
      foreach ($block["disciplines"] as $disc) {
        $discLabel = $disc["label"];
        if ($disc["show_weight"]) {
          $discLabel .= " (" . uc_display_value($disc["weight"], "") . "x)";
        }
        uc_gd_text($image, $colX + $cardPadding, $cursorY, $discLabel, $accentDark, (int)round(13 * $scale), "left");
        $rowY = $cursorY + (int)round(18 * $scale);
        if (!empty($disc["unit_label"])) {
          uc_gd_text($image, $colX + $cardPadding, $rowY, (string)$disc["unit_label"], $muted, (int)round(10 * $scale), "left");
          $rowY += $unitLineHeight;
        }
        foreach ($disc["rows"] as $row) {
          $rankLabel = $disc["ranks"][$row["player_id"]] ?? "-";
          $playerName = $row["name"];
          $display = uc_display_value($row["value"], "-");
          if ($display !== "-" && $disc["unit_abbr"] !== "") {
            $display .= " " . $disc["unit_abbr"];
          }
          $pointsLabel = uc_format_points($row["points"]) . " P";
          $textX = $colX + $cardPadding;
          if (in_array((int)$rankLabel, [1, 2, 3], true)) {
            $rankColor = $rankGold;
            if ((int)$rankLabel === 2) {
              $rankColor = $rankSilver;
            } elseif ((int)$rankLabel === 3) {
              $rankColor = $rankBronze;
            }
            $circleX = $textX + (int)round(8 * $scale);
            $circleY = $rowY + (int)round(6 * $scale);
            $circleSize = (int)round(18 * $scale);
            imagefilledellipse($image, $circleX, $circleY, $circleSize, $circleSize, $rankColor);
            uc_gd_text($image, $circleX, $circleY - (int)round(6 * $scale), (string)$rankLabel, $whiteText, (int)round(10 * $scale), "center");
            $textX += (int)round(22 * $scale);
            uc_gd_text($image, $textX, $rowY, $playerName, $ink, (int)round(11 * $scale), "left");
          } else {
            uc_gd_text($image, $textX, $rowY, $rankLabel . ". " . $playerName, $ink, (int)round(11 * $scale), "left");
          }
          uc_gd_text($image, $colX + $colWidth - $cardPadding, $rowY, $display . " · " . $pointsLabel, $ink, (int)round(11 * $scale), "right");
          $rowY += $lineHeight;
        }
        $cursorY = $rowY + $disciplineGap;
      }
      $colY += $block["height"] + $cardGap;
    }
  }

  header("Content-Type: image/png");
  header("Content-Disposition: attachment; filename=\"" . $shareFileBase . ".png\"");
  imagepng($image);
  imagedestroy($image);
  exit;
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($combine["combine_name"] ?? "Combine", ENT_QUOTES, "UTF-8"); ?></title>
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
      <button class="pill-button is-logout" type="submit">Abmelden</button>
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
            <button class="pill-button" type="button" onclick="window.location.href='combine.php?id=<?php echo (int)$combineId; ?>&edit=1'">Bearbeiten</button>
          <?php else: ?>
            <button class="pill-button is-danger" type="submit" form="delete-combine-form">Combine löschen</button>
          <?php endif; ?>
        </div>
        <?php if ($editMode): ?>
          <form id="delete-combine-form" method="post" action="" onsubmit="return confirm('Combine wirklich löschen? Alle zugehörigen Ergebnisse werden entfernt.') && confirm('Letzte Warnung: Dieser Vorgang kann nicht rückgängig gemacht werden. Wirklich löschen?');">
            <input type="hidden" name="action" value="delete_combine">
            <input type="hidden" name="combine_id" value="<?php echo (int)$combineId; ?>">
          </form>
        <?php endif; ?>
        <p class="lead">Datum: <?php echo htmlspecialchars($combine["event_date"], ENT_QUOTES, "UTF-8"); ?></p>
        <?php if (!empty($combine["combine_location"])): ?>
          <p class="lead">Ort: <?php echo htmlspecialchars($combine["combine_location"], ENT_QUOTES, "UTF-8"); ?></p>
        <?php endif; ?>
        <?php if (!empty($combine["combine_notes"])): ?>
          <p class="help"><?php echo htmlspecialchars($combine["combine_notes"], ENT_QUOTES, "UTF-8"); ?></p>
        <?php endif; ?>
        <?php if (!$editMode): ?>
          <div class="action-row">
            <button class="pill-button" type="button" onclick="window.location.href='combine.php?id=<?php echo (int)$combineId; ?>'">Setup</button>
            <button class="pill-button" type="button" onclick="window.location.href='combine.php?id=<?php echo (int)$combineId; ?>&mode=start'">Eintragen</button>
            <button class="pill-button" type="button" onclick="window.location.href='combine.php?id=<?php echo (int)$combineId; ?>&mode=results'">Ergebnisse</button>
            <button class="pill-button" type="button" onclick="window.location.href='combine.php?id=<?php echo (int)$combineId; ?>&mode=h2h'">H2H</button>
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
              <a class="pill-button is-muted" href="combine.php?id=<?php echo (int)$combineId; ?>&mode=start&discipline_id=<?php echo (int)$activeDisciplineId; ?>">Abbrechen</a>
            </div>
          </form>
        </section>
      <?php endif; ?>

      <?php if (!$startError && !empty($assignedDisciplines) && !empty($assignedPlayers) && $activeDisciplineId): ?>
        <section class="auth-card">
          <h3>Ergebnisse erfassen</h3>
          <?php if (!empty($activeDisciplineUnit)): ?>
            <p class="help"><?php echo htmlspecialchars($activeDisciplineUnit, ENT_QUOTES, "UTF-8"); ?></p>
          <?php endif; ?>
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
                    <?php if (!empty($activeDisciplineUnitAbbr)): ?>
                      <span class="unit-tag"><?php echo htmlspecialchars($activeDisciplineUnitAbbr, ENT_QUOTES, "UTF-8"); ?></span>
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
          $overallScoresAbs = [];
          $overallCategoryCounts = [];
          $categoryAverages = [];
          $categoryAveragesAbs = [];
          $categoryAveragesAvg = [];
          $categoryTeamAverages = [];
          $categoryTeamWeightedAverages = [];
          $categoryTeamAveragesAbs = [];
          $categoryTeamWeightedAveragesAbs = [];
          $categoryTeamAveragesAvg = [];
          $categoryWeights = [];
          foreach ($filteredPlayers as $player) {
            $playerId = (int)$player["id"];
            $overallScoresSum[$playerId] = 0;
            $overallScoresAvg[$playerId] = 0;
            $overallScoresAbs[$playerId] = 0;
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
            $categoryTotalsAbs = [];
            $categoryTotalsAvg = [];
            $categoryWeightSumAll = 0.0;
            $categoryWeightSumAllAbs = 0.0;
            $categoryWeightSumsAvg = [];
            foreach ($filteredPlayers as $player) {
              $playerId = (int)$player["id"];
              $categoryTotals[$playerId] = 0;
              $categoryTotalsAbs[$playerId] = 0;
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
              $expectedMinValue = uc_value_to_float($discipline["expected_min"] ?? null);
              $expectedMaxValue = uc_value_to_float($discipline["expected_max"] ?? null);
              $bonusRel = uc_bonus_value($discipline["bonus_relative"] ?? null);
              $bonusAbs = uc_bonus_value($discipline["bonus_absolute"] ?? null);
              $hasAbsolute = $expectedMinValue !== null && $expectedMaxValue !== null;
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
              if ($hasAbsolute) {
                $categoryWeightSumAllAbs += $disciplineWeight;
              }
              foreach ($filteredPlayers as $player) {
                $playerId = (int)$player["id"];
                $numericValue = $rankValues[$playerId] ?? null;
                $pointsBase = 0;
                if ($numericValue === null || $bestValue === null || $worstValue === null) {
                  $pointsBase = 0;
                } elseif ($bestValue == $worstValue) {
                  $pointsBase = 2;
                } else {
                  $ratio = ($numericValue - $worstValue) / ($bestValue - $worstValue);
                  $pointsBase = 1 + $ratio;
                }
                $pointsSum = $pointsBase;
                if ($overallMode === "sum" && $bonusRel > 0 && $numericValue !== null && $bestValue !== null && $numericValue == $bestValue) {
                  $pointsSum += $bonusRel;
                }
                $categoryTotals[$playerId] += $pointsSum * $disciplineWeight;
                if ($hasAbsolute) {
                  $absolutePoints = uc_absolute_points($numericValue, $expectedMinValue, $expectedMaxValue, $direction);
                  if ($absolutePoints === null) {
                    $absolutePoints = 0;
                  }
                  if ($bonusAbs > 0 && uc_absolute_bonus_applies($numericValue, $expectedMaxValue, $direction)) {
                    $absolutePoints += $bonusAbs;
                  }
                  $categoryTotalsAbs[$playerId] += $absolutePoints * $disciplineWeight;
                }

                if ($numericValue !== null && $bestValue !== null && $worstValue !== null) {
                  $categoryTotalsAvg[$playerId] += $pointsBase * $disciplineWeight;
                  $categoryWeightSumsAvg[$playerId] += $disciplineWeight;
                }
              }
            }
            if ($disciplineCount === 0 || $categoryWeightSumAll <= 0) {
              continue;
            }
            $teamSum = 0;
            $teamCount = 0;
            $teamSumAbs = 0;
            $teamCountAbs = 0;
            $teamSumAvg = 0;
            $teamCountAvg = 0;
            $hasAbsoluteCategory = $categoryWeightSumAllAbs > 0;
            foreach ($filteredPlayers as $player) {
              $playerId = (int)$player["id"];
              $categoryAverage = $categoryTotals[$playerId] / $categoryWeightSumAll;
              $overallScoresSum[$playerId] += $categoryAverage * $categoryWeight;
              if ($hasAbsoluteCategory) {
                $categoryAverageAbs = $categoryTotalsAbs[$playerId] / $categoryWeightSumAllAbs;
                $overallScoresAbs[$playerId] += $categoryAverageAbs * $categoryWeight;
                $categoryAveragesAbs[$category][$playerId] = $categoryAverageAbs;
                $teamSumAbs += $categoryAverageAbs;
                $teamCountAbs++;
              }
              $avgWeightSum = $categoryWeightSumsAvg[$playerId] ?? 0.0;
              if ($avgWeightSum > 0) {
                $categoryAverageAvg = $categoryTotalsAvg[$playerId] / $avgWeightSum;
                $overallScoresAvg[$playerId] += $categoryAverageAvg;
                $overallCategoryCounts[$playerId] += 1;
                $categoryAveragesAvg[$category][$playerId] = $categoryAverageAvg;
                $teamSumAvg += $categoryAverageAvg;
                $teamCountAvg++;
              }
              $categoryAverages[$category][$playerId] = $categoryAverage;
              $teamSum += $categoryAverage;
              $teamCount++;
            }
            if ($teamCount > 0) {
              $categoryTeamAverages[$category] = $teamSum / $teamCount;
              $categoryTeamWeightedAverages[$category] = ($teamSum / $teamCount) * $categoryWeight;
            }
            if ($teamCountAbs > 0) {
              $categoryTeamAveragesAbs[$category] = $teamSumAbs / $teamCountAbs;
              $categoryTeamWeightedAveragesAbs[$category] = ($teamSumAbs / $teamCountAbs) * $categoryWeight;
            }
            if ($teamCountAvg > 0) {
              $categoryTeamAveragesAvg[$category] = $teamSumAvg / $teamCountAvg;
            }
          }
          foreach ($overallScoresAvg as $playerId => $score) {
            $count = $overallCategoryCounts[$playerId] ?? 0;
            if ($count > 0) {
              $overallScoresAvg[$playerId] = $score / $count;
            }
          }
          if ($overallMode === "avg") {
            $overallScores = $overallScoresAvg;
          } elseif ($overallMode === "abs") {
            $overallScores = $overallScoresAbs;
          } else {
            $overallScores = $overallScoresSum;
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
          $overallAbsUrl = $overallBaseUrl . "&overall=abs";
        ?>
        <div class="section-header">
          <div class="card-actions">
            <button class="pill-button is-muted" type="button" data-target="results-filters" aria-expanded="false">Filter</button>
            <button class="pill-button is-share" type="button" data-target="share-combine" aria-expanded="false">Teilen</button>
          </div>
        </div>
        <?php if ($filterGender !== "" || $filterPosition !== ""): ?>
          <?php
            $activeFilters = [];
            if ($filterGender !== "") {
              $activeFilters[] = "Geschlecht: " . ($genderOptions[$filterGender] ?? $filterGender);
            }
            if ($filterPosition !== "") {
              $activeFilters[] = "Position: " . ($filterPosition === "handler" ? "Handler" : "Cutter");
            }
          ?>
          <p class="help">Filter aktiv: <?php echo htmlspecialchars(implode(" · ", $activeFilters), ENT_QUOTES, "UTF-8"); ?></p>
        <?php endif; ?>
        <?php
          $shareBaseParams = [
            "id" => (int)$combineId,
            "mode" => "results",
            "overall" => $overallMode,
          ];
          if ($filterGender !== "") {
            $shareBaseParams["gender"] = $filterGender;
          }
          if ($filterPosition !== "") {
            $shareBaseParams["position"] = $filterPosition;
          }
          $shareBaseUrl = "combine.php?" . http_build_query($shareBaseParams);
        ?>
        <div class="share-panel is-hidden" id="share-combine">
          <button class="pill-button is-muted" type="button" onclick="window.location.href='<?php echo htmlspecialchars($shareBaseUrl . "&share=csv", ENT_QUOTES, "UTF-8"); ?>'">CSV herunterladen</button>
          <button class="pill-button is-muted" type="button" onclick="window.location.href='<?php echo htmlspecialchars($shareBaseUrl . "&share=img", ENT_QUOTES, "UTF-8"); ?>'">Bild herunterladen</button>
        </div>
        <div class="info-card is-hidden" id="results-filters">
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
                <button class="pill-button is-muted" type="button" onclick="window.location.href='<?php echo htmlspecialchars($overallBaseUrl . "&overall=" . urlencode($overallMode), ENT_QUOTES, "UTF-8"); ?>'">Zurücksetzen</button>
              <?php endif; ?>
            </div>
          </form>
        </div>
        <div class="info-card">
          <div class="card-header">
            <h3>Overall Ranking</h3>
            <div class="card-actions">
              <button class="pill-button<?php echo $overallMode === "sum" ? " is-active" : ""; ?>" type="button" onclick="window.location.href='<?php echo htmlspecialchars($overallSumUrl, ENT_QUOTES, "UTF-8"); ?>'">Relativ</button>
              <button class="pill-button<?php echo $overallMode === "avg" ? " is-active" : ""; ?>" type="button" onclick="window.location.href='<?php echo htmlspecialchars($overallAvgUrl, ENT_QUOTES, "UTF-8"); ?>'">Ø Relativ</button>
              <button class="pill-button<?php echo $overallMode === "abs" ? " is-active" : ""; ?>" type="button" onclick="window.location.href='<?php echo htmlspecialchars($overallAbsUrl, ENT_QUOTES, "UTF-8"); ?>'">Absolut</button>
            </div>
          </div>
          <?php if ($overallMode === "sum"): ?>
            <p class="help">Relativ: Punkte werden relativ zu den Teilnehmern berechnet. Nicht absolvierte Disziplinen zählen als 0 in den Kategorien.</p>
          <?php elseif ($overallMode === "avg"): ?>
            <p class="help">Ø Relativ: Es zählen nur Kategorien und Disziplinen, die dieser Spieler absolviert hat. Punkte werden relativ zu den Teilnehmern berechnet.</p>
          <?php else: ?>
            <p class="help">Absolut: Punkte anhand Erwartungs-Min/Max. Disziplinen ohne Erwartungswerte werden nicht berücksichtigt.</p>
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
            <ul class="list overall-ranking-list">
              <?php foreach ($overallOrderedPlayers as $player): ?>
                <?php $playerId = (int)$player["id"]; ?>
                <?php $overallPoints = $overallScores[$playerId] ?? 0; ?>
                <?php $rankLabel = isset($overallRanks[$playerId]) ? (string)$overallRanks[$playerId] : "-"; ?>
                <?php $overallPointsPrefix = $overallMode === "avg" ? "Ø " : ""; ?>
                <?php
                  $nameParts = [(string)($player["first_name"] ?? ""), (string)($player["last_name"] ?? "")];
                  $hasLongNamePart = false;
                  foreach ($nameParts as $part) {
                    $partLength = function_exists("mb_strlen") ? mb_strlen($part) : strlen($part);
                    if ($partLength >= 16) {
                      $hasLongNamePart = true;
                      break;
                    }
                  }
                ?>
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
                      <strong class="player-name<?php echo $hasLongNamePart ? " is-condensed" : ""; ?>">
                        <?php echo htmlspecialchars($player["first_name"], ENT_QUOTES, "UTF-8"); ?>
                        <?php echo " " . htmlspecialchars($player["last_name"], ENT_QUOTES, "UTF-8"); ?>
                      </strong>
                    </div>
                    <span class="badge"><?php echo htmlspecialchars($overallPointsPrefix . uc_format_points($overallPoints) . " P", ENT_QUOTES, "UTF-8"); ?></span>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
        <?php if ($selectedPlayerId && $selectedPlayer): ?>
          <?php
            $radarData = [];
            $radarPlayerAverages = $categoryAverages;
            $radarTeamAverages = $categoryTeamWeightedAverages;
            $radarApplyWeight = true;
            if ($overallMode === "abs") {
              $radarPlayerAverages = $categoryAveragesAbs;
              $radarTeamAverages = $categoryTeamWeightedAveragesAbs;
              $radarApplyWeight = true;
            } elseif ($overallMode === "avg") {
              $radarPlayerAverages = $categoryAveragesAvg;
              $radarTeamAverages = $categoryTeamAveragesAvg;
              $radarApplyWeight = false;
            }
            foreach ($radarPlayerAverages as $category => $playerAverages) {
              $categoryWeight = $combineCategoryWeights[$category] ?? 1;
              if ($categoryWeight <= 0) {
                $categoryWeight = 1;
              }
              $playerAverage = $playerAverages[$selectedPlayerId] ?? 0;
              if ($radarApplyWeight) {
                $playerAverage *= $categoryWeight;
              }
              $teamAverage = $radarTeamAverages[$category] ?? 0;
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
            $playerShareUrl = $resetUrl . "&player_id=" . (int)$selectedPlayerId . "&share=img";
          ?>
          <div class="info-card player-detail">
            <div class="card-header">
              <h3>
                Ergebnisse: <?php echo htmlspecialchars($selectedPlayer["first_name"], ENT_QUOTES, "UTF-8"); ?>
                <?php echo " " . htmlspecialchars($selectedPlayer["last_name"], ENT_QUOTES, "UTF-8"); ?>
              </h3>
              <div class="card-actions">
                <a class="pill-button is-share" href="<?php echo htmlspecialchars($playerShareUrl, ENT_QUOTES, "UTF-8"); ?>">Teilen</a>
                <a class="pill-button is-muted" href="<?php echo htmlspecialchars($resetUrl, ENT_QUOTES, "UTF-8"); ?>">Schließen</a>
              </div>
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
                      $displayDisciplines = $categoryDisciplines;
                      if ($overallMode === "abs") {
                        $displayDisciplines = array_values(array_filter($categoryDisciplines, function ($discipline) {
                          $minValue = uc_value_to_float($discipline["expected_min"] ?? null);
                          $maxValue = uc_value_to_float($discipline["expected_max"] ?? null);
                          return $minValue !== null && $maxValue !== null;
                        }));
                      }
                      if (empty($displayDisciplines)) {
                        continue;
                      }
                      $showDisciplineWeights = false;
                      foreach ($displayDisciplines as $discipline) {
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
                      <?php if (count($displayDisciplines) > 1): ?>
                        <?php
                          $categoryScore = $categoryAverages[$category][$selectedPlayerId] ?? null;
                          $categoryScoreLabel = $categoryScore === null ? "-" : uc_format_points($categoryScore) . " P";
                        ?>
                        <p class="help">Kategorie-Score: <?php echo htmlspecialchars($categoryScoreLabel, ENT_QUOTES, "UTF-8"); ?></p>
                      <?php endif; ?>
                      <ul class="list">
                        <?php foreach ($displayDisciplines as $discipline): ?>
                          <?php
                            $discId = (int)$discipline["id"];
                            $direction = $discipline["rating_direction"] ?? "more";
                            if ($direction !== "less" && $direction !== "more") {
                              $direction = "more";
                            }
                            $unit = uc_format_unit($discipline["unit"] ?? "", $unitAbbrMap);
                            $disciplineWeight = $combineDisciplineWeights[$discId] ?? 1;
                            $expectedMinValue = uc_value_to_float($discipline["expected_min"] ?? null);
                            $expectedMaxValue = uc_value_to_float($discipline["expected_max"] ?? null);
                            $bonusRel = uc_bonus_value($discipline["bonus_relative"] ?? null);
                            $bonusAbs = uc_bonus_value($discipline["bonus_absolute"] ?? null);
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
                            $bestExpected = $expectedMinValue;
                            $worstExpected = $expectedMaxValue;
                            if ($direction === "less") {
                              $bestExpected = $expectedMaxValue;
                              $worstExpected = $expectedMinValue;
                            }
                            $minLabel = $worstExpected === null ? "-" : uc_display_value($worstExpected, "-");
                            $maxLabel = $bestExpected === null ? "-" : uc_display_value($bestExpected, "-");
                            if ($overallMode === "abs" && $unit !== "") {
                              if ($minLabel !== "-") { $minLabel .= " " . $unit; }
                              if ($maxLabel !== "-") { $maxLabel .= " " . $unit; }
                            }
                            if ($overallMode === "abs") {
                              $points = uc_absolute_points($numericValue, $expectedMinValue, $expectedMaxValue, $direction);
                              if ($points === null) {
                                $points = 0;
                              }
                              if ($bonusAbs > 0 && uc_absolute_bonus_applies($numericValue, $expectedMaxValue, $direction)) {
                                $points += $bonusAbs;
                              }
                            } elseif ($numericValue === null || $bestValue === null || $worstValue === null) {
                              $points = 0;
                            } elseif ($bestValue == $worstValue) {
                              $points = 2;
                            } else {
                              $ratio = ($numericValue - $worstValue) / ($bestValue - $worstValue);
                              $points = 1 + $ratio;
                            }
                            if ($overallMode === "sum" && $bonusRel > 0 && $numericValue !== null && $bestValue !== null && $numericValue == $bestValue) {
                              $points += $bonusRel;
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
                              <?php if ($overallMode === "abs"): ?>
                                <span class="meta">Schlechtester: <?php echo htmlspecialchars($minLabel, ENT_QUOTES, "UTF-8"); ?> · Bester: <?php echo htmlspecialchars($maxLabel, ENT_QUOTES, "UTF-8"); ?></span>
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
              $displayDisciplines = $categoryDisciplines;
              if ($overallMode === "abs") {
                $displayDisciplines = array_values(array_filter($categoryDisciplines, function ($discipline) {
                  $minValue = uc_value_to_float($discipline["expected_min"] ?? null);
                  $maxValue = uc_value_to_float($discipline["expected_max"] ?? null);
                  return $minValue !== null && $maxValue !== null;
                }));
              }
              if (empty($displayDisciplines)) {
                continue;
              }
              $showDisciplineWeights = false;
              foreach ($displayDisciplines as $discipline) {
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
              <?php foreach ($displayDisciplines as $discipline): ?>
                <?php
                  $discId = (int)$discipline["id"];
                  $direction = $discipline["rating_direction"] ?? "more";
                  if ($direction !== "less" && $direction !== "more") {
                    $direction = "more";
                  }
                  $unit = uc_format_unit($discipline["unit"] ?? "", $unitAbbrMap);
                  $unitLabel = uc_format_unit_label($discipline["unit"] ?? "", $unitAbbrMap);
                  $disciplineWeight = $combineDisciplineWeights[$discId] ?? 1;
                  $expectedMinValue = uc_value_to_float($discipline["expected_min"] ?? null);
                  $expectedMaxValue = uc_value_to_float($discipline["expected_max"] ?? null);
                  $bonusRel = uc_bonus_value($discipline["bonus_relative"] ?? null);
                  $bonusAbs = uc_bonus_value($discipline["bonus_absolute"] ?? null);
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
                  $bestExpected = $expectedMinValue;
                  $worstExpected = $expectedMaxValue;
                  if ($direction === "less") {
                    $bestExpected = $expectedMaxValue;
                    $worstExpected = $expectedMinValue;
                  }
                  $minLabel = $worstExpected === null ? "-" : uc_display_value($worstExpected, "-");
                  $maxLabel = $bestExpected === null ? "-" : uc_display_value($bestExpected, "-");
                  if ($overallMode === "abs" && $unit !== "") {
                    if ($minLabel !== "-") { $minLabel .= " " . $unit; }
                    if ($maxLabel !== "-") { $maxLabel .= " " . $unit; }
                  }
                ?>
                <div class="info-card">
                  <details>
                    <summary>
                      <strong><?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?></strong>
                      <?php if ($showDisciplineWeights): ?>
                        <span class="meta">(<?php echo htmlspecialchars($disciplineWeight, ENT_QUOTES, "UTF-8"); ?>x)</span>
                      <?php endif; ?>
                      <?php if ($overallMode === "abs"): ?>
                        <span class="meta">Schlechtester: <?php echo htmlspecialchars($minLabel, ENT_QUOTES, "UTF-8"); ?> · Bester: <?php echo htmlspecialchars($maxLabel, ENT_QUOTES, "UTF-8"); ?></span>
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
                      <?php if ($unitLabel !== ""): ?>
                        <p class="help">Einheit: <?php echo htmlspecialchars($unitLabel, ENT_QUOTES, "UTF-8"); ?></p>
                      <?php endif; ?>
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
                            if ($overallMode === "abs") {
                              $points = uc_absolute_points($numericValue, $expectedMinValue, $expectedMaxValue, $direction);
                              if ($points === null) {
                                $points = 0;
                              }
                              if ($bonusAbs > 0 && uc_absolute_bonus_applies($numericValue, $expectedMaxValue, $direction)) {
                                $points += $bonusAbs;
                              }
                            } elseif ($numericValue === null || $bestValue === null || $worstValue === null) {
                              $points = 0;
                            } elseif ($bestValue == $worstValue) {
                              $points = 2;
                            } else {
                              $ratio = ($numericValue - $worstValue) / ($bestValue - $worstValue);
                              $points = 1 + $ratio;
                            }
                            if ($overallMode === "sum" && $bonusRel > 0 && $numericValue !== null && $bestValue !== null && $numericValue == $bestValue) {
                              $points += $bonusRel;
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

    <?php if (!$pageError && !$combineError && $mode === "h2h"): ?>
      <section class="info">
        <h2>Head 2 Head</h2>
        <?php
          $playerMap = [];
          foreach ($assignedPlayers as $player) {
            $playerMap[(int)$player["id"]] = $player;
          }
          $h2hPlayerA = $h2hPlayerAId ? ($playerMap[$h2hPlayerAId] ?? null) : null;
          $h2hPlayerB = $h2hPlayerBId ? ($playerMap[$h2hPlayerBId] ?? null) : null;
          $h2hReady = $h2hPlayerA && $h2hPlayerB && $h2hPlayerAId !== $h2hPlayerBId;
          $h2hBaseParams = [
            "id" => (int)$combineId,
            "mode" => "h2h",
          ];
          if ($h2hPlayerAId) {
            $h2hBaseParams["player_a"] = $h2hPlayerAId;
          }
          if ($h2hPlayerBId) {
            $h2hBaseParams["player_b"] = $h2hPlayerBId;
          }
          $h2hBaseUrl = "combine.php?" . http_build_query($h2hBaseParams);
          $h2hSumUrl = $h2hBaseUrl . "&overall=sum";
          $h2hAvgUrl = $h2hBaseUrl . "&overall=avg";
          $h2hAbsUrl = $h2hBaseUrl . "&overall=abs";
        ?>
        <div class="info-card">
          <div class="card-header">
            <div class="card-actions">
              <button class="pill-button<?php echo $overallMode === "sum" ? " is-active" : ""; ?>" type="button" onclick="window.location.href='<?php echo htmlspecialchars($h2hSumUrl, ENT_QUOTES, "UTF-8"); ?>'">Relativ</button>
              <button class="pill-button<?php echo $overallMode === "avg" ? " is-active" : ""; ?>" type="button" onclick="window.location.href='<?php echo htmlspecialchars($h2hAvgUrl, ENT_QUOTES, "UTF-8"); ?>'">Ø Relativ</button>
              <button class="pill-button<?php echo $overallMode === "abs" ? " is-active" : ""; ?>" type="button" onclick="window.location.href='<?php echo htmlspecialchars($h2hAbsUrl, ENT_QUOTES, "UTF-8"); ?>'">Absolut</button>
              <?php if ($h2hReady): ?>
                <?php
                  $h2hShareUrl = $h2hBaseUrl . "&overall=" . urlencode($overallMode) . "&share=img";
                ?>
                <button class="pill-button is-share" type="button" onclick="window.location.href='<?php echo htmlspecialchars($h2hShareUrl, ENT_QUOTES, "UTF-8"); ?>'">Teilen</button>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($overallMode === "sum"): ?>
            <p class="help">Relativ: Punkte werden relativ zu allen Teilnehmern berechnet.</p>
          <?php elseif ($overallMode === "avg"): ?>
            <p class="help">Ø Relativ: Punkte werden relativ zu allen Teilnehmern berechnet.</p>
          <?php else: ?>
            <p class="help">Absolut: Punkte anhand Erwartungs-Min/Max. Es werden nur Disziplinen mit Erwartungswerten angezeigt.</p>
          <?php endif; ?>
          <form class="form" method="get" action="combine.php">
            <input type="hidden" name="id" value="<?php echo (int)$combineId; ?>">
            <input type="hidden" name="mode" value="h2h">
            <input type="hidden" name="overall" value="<?php echo htmlspecialchars($overallMode, ENT_QUOTES, "UTF-8"); ?>">
            <label class="field">
              <select name="player_a" required>
                <option value="">Bitte wählen</option>
                <?php foreach ($assignedPlayers as $player): ?>
                  <?php $isDisabled = (int)$player["id"] === (int)$h2hPlayerBId; ?>
                  <option value="<?php echo (int)$player["id"]; ?>"<?php echo (int)$player["id"] === (int)$h2hPlayerAId ? " selected" : ""; ?><?php echo $isDisabled ? " disabled" : ""; ?>>
                    <?php echo htmlspecialchars($player["first_name"] . " " . $player["last_name"], ENT_QUOTES, "UTF-8"); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="field">
              <select name="player_b" required>
                <option value="">Bitte wählen</option>
                <?php foreach ($assignedPlayers as $player): ?>
                  <?php $isDisabled = (int)$player["id"] === (int)$h2hPlayerAId; ?>
                  <option value="<?php echo (int)$player["id"]; ?>"<?php echo (int)$player["id"] === (int)$h2hPlayerBId ? " selected" : ""; ?><?php echo $isDisabled ? " disabled" : ""; ?>>
                    <?php echo htmlspecialchars($player["first_name"] . " " . $player["last_name"], ENT_QUOTES, "UTF-8"); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <div class="form-actions">
              <button class="primary-button" type="submit">Vergleichen</button>
            </div>
          </form>
          <?php if ($h2hPlayerAId && $h2hPlayerBId && $h2hPlayerAId === $h2hPlayerBId): ?>
            <p class="help">Bitte zwei unterschiedliche Spieler auswählen.</p>
          <?php endif; ?>
        </div>

        <?php if ($h2hReady): ?>
          <?php
            $overallScoresSum = [];
            $overallScoresAvg = [];
            $overallScoresAbs = [];
            $overallCategoryCounts = [];
            foreach ($assignedPlayers as $player) {
              $playerId = (int)$player["id"];
              $overallScoresSum[$playerId] = 0;
              $overallScoresAvg[$playerId] = 0;
              $overallScoresAbs[$playerId] = 0;
              $overallCategoryCounts[$playerId] = 0;
            }
            foreach ($assignedDisciplinesByCategory as $category => $categoryDisciplines) {
              $categoryWeight = $combineCategoryWeights[$category] ?? 1.0;
              if ($categoryWeight <= 0) {
                $categoryWeight = 1.0;
              }
              $disciplineCount = 0;
              $categoryTotals = [];
              $categoryTotalsAbs = [];
              $categoryTotalsAvg = [];
              $categoryWeightSumAll = 0.0;
              $categoryWeightSumAllAbs = 0.0;
              $categoryWeightSumsAvg = [];
              foreach ($assignedPlayers as $player) {
                $playerId = (int)$player["id"];
                $categoryTotals[$playerId] = 0;
                $categoryTotalsAbs[$playerId] = 0;
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
                $expectedMinValue = uc_value_to_float($discipline["expected_min"] ?? null);
                $expectedMaxValue = uc_value_to_float($discipline["expected_max"] ?? null);
                $bonusRel = uc_bonus_value($discipline["bonus_relative"] ?? null);
                $bonusAbs = uc_bonus_value($discipline["bonus_absolute"] ?? null);
                $hasAbsolute = $expectedMinValue !== null && $expectedMaxValue !== null;
                if ($overallMode === "abs" && !$hasAbsolute) {
                  continue;
                }
                $rankValues = [];
                foreach ($assignedPlayers as $player) {
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
                if ($hasAbsolute) {
                  $categoryWeightSumAllAbs += $disciplineWeight;
                }
                foreach ($assignedPlayers as $player) {
                  $playerId = (int)$player["id"];
                  $numericValue = $rankValues[$playerId] ?? null;
                  $pointsBase = 0;
                  if ($numericValue === null || $bestValue === null || $worstValue === null) {
                    $pointsBase = 0;
                  } elseif ($bestValue == $worstValue) {
                    $pointsBase = 2;
                  } else {
                    $ratio = ($numericValue - $worstValue) / ($bestValue - $worstValue);
                    $pointsBase = 1 + $ratio;
                  }
                  $pointsSum = $pointsBase;
                  if ($overallMode === "sum" && $bonusRel > 0 && $numericValue !== null && $bestValue !== null && $numericValue == $bestValue) {
                    $pointsSum += $bonusRel;
                  }
                  $categoryTotals[$playerId] += $pointsSum * $disciplineWeight;
                  if ($hasAbsolute) {
                    $absolutePoints = uc_absolute_points($numericValue, $expectedMinValue, $expectedMaxValue, $direction);
                    if ($absolutePoints === null) {
                      $absolutePoints = 0;
                    }
                    if ($bonusAbs > 0 && uc_absolute_bonus_applies($numericValue, $expectedMaxValue, $direction)) {
                      $absolutePoints += $bonusAbs;
                    }
                    $categoryTotalsAbs[$playerId] += $absolutePoints * $disciplineWeight;
                  }
                  if ($numericValue !== null && $bestValue !== null && $worstValue !== null) {
                    $categoryTotalsAvg[$playerId] += $pointsBase * $disciplineWeight;
                    $categoryWeightSumsAvg[$playerId] += $disciplineWeight;
                  }
                }
              }
              if ($disciplineCount === 0 || $categoryWeightSumAll <= 0) {
                continue;
              }
              foreach ($assignedPlayers as $player) {
                $playerId = (int)$player["id"];
                $categoryAverage = $categoryTotals[$playerId] / $categoryWeightSumAll;
                $overallScoresSum[$playerId] += $categoryAverage * $categoryWeight;
                if ($categoryWeightSumAllAbs > 0) {
                  $categoryAverageAbs = $categoryTotalsAbs[$playerId] / $categoryWeightSumAllAbs;
                  $overallScoresAbs[$playerId] += $categoryAverageAbs * $categoryWeight;
                }
                $avgWeightSum = $categoryWeightSumsAvg[$playerId] ?? 0.0;
                if ($avgWeightSum > 0) {
                  $categoryAverageAvg = $categoryTotalsAvg[$playerId] / $avgWeightSum;
                  $overallScoresAvg[$playerId] += $categoryAverageAvg;
                  $overallCategoryCounts[$playerId] += 1;
                }
              }
            }
            foreach ($overallScoresAvg as $playerId => $score) {
              $count = $overallCategoryCounts[$playerId] ?? 0;
              if ($count > 0) {
                $overallScoresAvg[$playerId] = $score / $count;
              }
            }
            if ($overallMode === "avg") {
              $overallScores = $overallScoresAvg;
            } elseif ($overallMode === "abs") {
              $overallScores = $overallScoresAbs;
            } else {
              $overallScores = $overallScoresSum;
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
            $playerALabel = $h2hPlayerA["first_name"] . " " . $h2hPlayerA["last_name"];
            $playerBLabel = $h2hPlayerB["first_name"] . " " . $h2hPlayerB["last_name"];
            $overallPointsPrefix = $overallMode === "avg" ? "Ø " : "";
            $overallPointsA = $overallScores[$h2hPlayerAId] ?? 0;
            $overallPointsB = $overallScores[$h2hPlayerBId] ?? 0;
            $overallRankA = $overallRanks[$h2hPlayerAId] ?? "-";
            $overallRankB = $overallRanks[$h2hPlayerBId] ?? "-";
            $h2hRadarData = [];
            foreach ($assignedDisciplinesByCategory as $category => $categoryDisciplines) {
              $displayDisciplines = $categoryDisciplines;
              if ($overallMode === "abs") {
                $displayDisciplines = array_values(array_filter($categoryDisciplines, function ($discipline) {
                  $minValue = uc_value_to_float($discipline["expected_min"] ?? null);
                  $maxValue = uc_value_to_float($discipline["expected_max"] ?? null);
                  return $minValue !== null && $maxValue !== null;
                }));
              }
              if (empty($displayDisciplines)) {
                continue;
              }
              $categoryWeight = $combineCategoryWeights[$category] ?? 1.0;
              if ($categoryWeight <= 0) {
                $categoryWeight = 1.0;
              }
              $weightSum = 0.0;
              $weightSumAbs = 0.0;
              $sumA = 0.0;
              $sumB = 0.0;
              $sumAbsA = 0.0;
              $sumAbsB = 0.0;
              $sumAvgA = 0.0;
              $sumAvgB = 0.0;
              $sumAvgWeightA = 0.0;
              $sumAvgWeightB = 0.0;
              $categoryTotalsTeam = [];
              $categoryTotalsAbsTeam = [];
              $categoryTotalsAvgTeam = [];
              $categoryWeightSumsAvgTeam = [];
              foreach ($assignedPlayers as $player) {
                $playerId = (int)$player["id"];
                $categoryTotalsTeam[$playerId] = 0.0;
                $categoryTotalsAbsTeam[$playerId] = 0.0;
                $categoryTotalsAvgTeam[$playerId] = 0.0;
                $categoryWeightSumsAvgTeam[$playerId] = 0.0;
              }
              foreach ($displayDisciplines as $discipline) {
                $discId = (int)$discipline["id"];
                $disciplineWeight = $combineDisciplineWeights[$discId] ?? 1.0;
                if ($disciplineWeight <= 0) {
                  $disciplineWeight = 1.0;
                }
                $direction = $discipline["rating_direction"] ?? "more";
                if ($direction !== "less" && $direction !== "more") {
                  $direction = "more";
                }
                $expectedMinValue = uc_value_to_float($discipline["expected_min"] ?? null);
                $expectedMaxValue = uc_value_to_float($discipline["expected_max"] ?? null);
                $bonusRel = uc_bonus_value($discipline["bonus_relative"] ?? null);
                $bonusAbs = uc_bonus_value($discipline["bonus_absolute"] ?? null);
                $hasAbsolute = $expectedMinValue !== null && $expectedMaxValue !== null;
                $rankValues = [];
                foreach ($assignedPlayers as $player) {
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
                  $weightSum += $disciplineWeight;
                  if ($hasAbsolute) {
                    $weightSumAbs += $disciplineWeight;
                  }
                  $values = array_values($rankValues);
                  if ($direction === "less") {
                    $bestValue = min($values);
                    $worstValue = max($values);
                  } else {
                    $bestValue = max($values);
                    $worstValue = min($values);
                  }
                }
                $numericA = isset($rankValues[$h2hPlayerAId]) ? $rankValues[$h2hPlayerAId] : null;
                $numericB = isset($rankValues[$h2hPlayerBId]) ? $rankValues[$h2hPlayerBId] : null;
                $pointsBaseA = 0;
                $pointsBaseB = 0;
                if ($numericA === null || $bestValue === null || $worstValue === null) {
                  $pointsBaseA = 0;
                } elseif ($bestValue == $worstValue) {
                  $pointsBaseA = 2;
                } else {
                  $ratioA = ($numericA - $worstValue) / ($bestValue - $worstValue);
                  $pointsBaseA = 1 + $ratioA;
                }
                if ($numericB === null || $bestValue === null || $worstValue === null) {
                  $pointsBaseB = 0;
                } elseif ($bestValue == $worstValue) {
                  $pointsBaseB = 2;
                } else {
                  $ratioB = ($numericB - $worstValue) / ($bestValue - $worstValue);
                  $pointsBaseB = 1 + $ratioB;
                }
                $pointsSumA = $pointsBaseA;
                $pointsSumB = $pointsBaseB;
                if ($overallMode === "sum" && $bonusRel > 0 && $bestValue !== null) {
                  if ($numericA !== null && $numericA == $bestValue) {
                    $pointsSumA += $bonusRel;
                  }
                  if ($numericB !== null && $numericB == $bestValue) {
                    $pointsSumB += $bonusRel;
                  }
                }
                $sumA += $pointsSumA * $disciplineWeight;
                $sumB += $pointsSumB * $disciplineWeight;
                if ($hasAbsolute) {
                  $pointsAbsA = uc_absolute_points($numericA, $expectedMinValue, $expectedMaxValue, $direction);
                  $pointsAbsB = uc_absolute_points($numericB, $expectedMinValue, $expectedMaxValue, $direction);
                  if ($pointsAbsA === null) { $pointsAbsA = 0; }
                  if ($pointsAbsB === null) { $pointsAbsB = 0; }
                  if ($bonusAbs > 0 && uc_absolute_bonus_applies($numericA, $expectedMaxValue, $direction)) {
                    $pointsAbsA += $bonusAbs;
                  }
                  if ($bonusAbs > 0 && uc_absolute_bonus_applies($numericB, $expectedMaxValue, $direction)) {
                    $pointsAbsB += $bonusAbs;
                  }
                  $sumAbsA += $pointsAbsA * $disciplineWeight;
                  $sumAbsB += $pointsAbsB * $disciplineWeight;
                }
                if ($numericA !== null && $bestValue !== null && $worstValue !== null) {
                  $sumAvgA += $pointsBaseA * $disciplineWeight;
                  $sumAvgWeightA += $disciplineWeight;
                }
                if ($numericB !== null && $bestValue !== null && $worstValue !== null) {
                  $sumAvgB += $pointsBaseB * $disciplineWeight;
                  $sumAvgWeightB += $disciplineWeight;
                }
                foreach ($assignedPlayers as $player) {
                  $playerId = (int)$player["id"];
                  $numeric = $rankValues[$playerId] ?? null;
                  $pointsBase = 0;
                  if ($numeric === null || $bestValue === null || $worstValue === null) {
                    $pointsBase = 0;
                  } elseif ($bestValue == $worstValue) {
                    $pointsBase = 2;
                  } else {
                    $ratio = ($numeric - $worstValue) / ($bestValue - $worstValue);
                    $pointsBase = 1 + $ratio;
                  }
                  $pointsSum = $pointsBase;
                  if ($overallMode === "sum" && $bonusRel > 0 && $bestValue !== null && $numeric !== null && $numeric == $bestValue) {
                    $pointsSum += $bonusRel;
                  }
                  $categoryTotalsTeam[$playerId] += $pointsSum * $disciplineWeight;
                  if ($hasAbsolute) {
                    $pointsAbs = uc_absolute_points($numeric, $expectedMinValue, $expectedMaxValue, $direction);
                    if ($pointsAbs === null) {
                      $pointsAbs = 0;
                    }
                    if ($bonusAbs > 0 && uc_absolute_bonus_applies($numeric, $expectedMaxValue, $direction)) {
                      $pointsAbs += $bonusAbs;
                    }
                    $categoryTotalsAbsTeam[$playerId] += $pointsAbs * $disciplineWeight;
                  }
                  if ($numeric !== null && $bestValue !== null && $worstValue !== null) {
                    $categoryTotalsAvgTeam[$playerId] += $pointsBase * $disciplineWeight;
                    $categoryWeightSumsAvgTeam[$playerId] += $disciplineWeight;
                  }
                }
              }
              $radarA = 0.0;
              $radarB = 0.0;
              $radarTeam = 0.0;
              $hasRadar = false;
              if ($overallMode === "avg") {
                if ($sumAvgWeightA > 0 || $sumAvgWeightB > 0) {
                  $radarA = $sumAvgWeightA > 0 ? $sumAvgA / $sumAvgWeightA : 0;
                  $radarB = $sumAvgWeightB > 0 ? $sumAvgB / $sumAvgWeightB : 0;
                  $hasRadar = true;
                }
                $teamSum = 0.0;
                $teamCount = 0;
                foreach ($assignedPlayers as $player) {
                  $playerId = (int)$player["id"];
                  $teamWeightSum = $categoryWeightSumsAvgTeam[$playerId] ?? 0.0;
                  if ($teamWeightSum > 0) {
                    $teamSum += $categoryTotalsAvgTeam[$playerId] / $teamWeightSum;
                    $teamCount++;
                  }
                }
                if ($teamCount > 0) {
                  $radarTeam = $teamSum / $teamCount;
                  $hasRadar = true;
                }
              } elseif ($overallMode === "abs") {
                if ($weightSumAbs > 0) {
                  $radarA = $sumAbsA / $weightSumAbs;
                  $radarB = $sumAbsB / $weightSumAbs;
                  $hasRadar = true;
                }
                if ($weightSumAbs > 0) {
                  $teamSum = 0.0;
                  $teamCount = 0;
                  foreach ($assignedPlayers as $player) {
                    $playerId = (int)$player["id"];
                    $teamSum += $categoryTotalsAbsTeam[$playerId] / $weightSumAbs;
                    $teamCount++;
                  }
                  if ($teamCount > 0) {
                    $radarTeam = $teamSum / $teamCount;
                    $hasRadar = true;
                  }
                }
              } else {
                if ($weightSum > 0) {
                  $radarA = $sumA / $weightSum;
                  $radarB = $sumB / $weightSum;
                  $hasRadar = true;
                }
                if ($weightSum > 0) {
                  $teamSum = 0.0;
                  $teamCount = 0;
                  foreach ($assignedPlayers as $player) {
                    $playerId = (int)$player["id"];
                    $teamSum += $categoryTotalsTeam[$playerId] / $weightSum;
                    $teamCount++;
                  }
                  if ($teamCount > 0) {
                    $radarTeam = $teamSum / $teamCount;
                    $hasRadar = true;
                  }
                }
              }
              if ($hasRadar) {
                if ($overallMode !== "avg") {
                  $radarA *= $categoryWeight;
                  $radarB *= $categoryWeight;
                  $radarTeam *= $categoryWeight;
                }
                $h2hRadarData[] = [
                  "label" => $category,
                  "player" => $radarA,
                  "playerB" => $radarB,
                  "team" => $radarTeam,
                ];
              }
            }
          ?>
          <div class="info-card">
            <div class="card-header">
              <h3>Overall</h3>
            </div>
            <ul class="list">
              <li class="list-item">
                <div class="result-name">
                  <strong><?php echo htmlspecialchars($playerALabel, ENT_QUOTES, "UTF-8"); ?></strong>
                </div>
                <span class="badge">
                  <?php echo htmlspecialchars("Platz " . $overallRankA . " · " . $overallPointsPrefix . uc_format_points($overallPointsA) . " P", ENT_QUOTES, "UTF-8"); ?>
                </span>
              </li>
              <li class="list-item">
                <div class="result-name">
                  <strong><?php echo htmlspecialchars($playerBLabel, ENT_QUOTES, "UTF-8"); ?></strong>
                </div>
                <span class="badge">
                  <?php echo htmlspecialchars("Platz " . $overallRankB . " · " . $overallPointsPrefix . uc_format_points($overallPointsB) . " P", ENT_QUOTES, "UTF-8"); ?>
                </span>
              </li>
            </ul>
          </div>
          <?php if (empty($assignedDisciplines)): ?>
            <p class="help">Keine Disziplinen zugeordnet.</p>
          <?php else: ?>
            <div class="info-card">
              <div class="h2h-legend">
                <span class="legend-item legend-player"><?php echo htmlspecialchars($playerALabel, ENT_QUOTES, "UTF-8"); ?></span>
                <span class="legend-item legend-team"><?php echo htmlspecialchars($playerBLabel, ENT_QUOTES, "UTF-8"); ?></span>
              </div>
            </div>
            <?php foreach ($assignedDisciplinesByCategory as $category => $categoryDisciplines): ?>
              <?php
                $displayDisciplines = $categoryDisciplines;
                if ($overallMode === "abs") {
                  $displayDisciplines = array_values(array_filter($categoryDisciplines, function ($discipline) {
                    $minValue = uc_value_to_float($discipline["expected_min"] ?? null);
                    $maxValue = uc_value_to_float($discipline["expected_max"] ?? null);
                    return $minValue !== null && $maxValue !== null;
                  }));
                }
                if (empty($displayDisciplines)) {
                  continue;
                }
              ?>
              <div class="category-block">
                <h3 class="category-title"><?php echo htmlspecialchars($category, ENT_QUOTES, "UTF-8"); ?></h3>
                <ul class="list h2h-list">
                  <?php foreach ($displayDisciplines as $discipline): ?>
                    <?php
                      $discId = (int)$discipline["id"];
                      $direction = $discipline["rating_direction"] ?? "more";
                      if ($direction !== "less" && $direction !== "more") {
                        $direction = "more";
                      }
                      $unit = uc_format_unit($discipline["unit"] ?? "", $unitAbbrMap);
                      $unitLabel = uc_format_unit_label($discipline["unit"] ?? "", $unitAbbrMap);
                      $expectedMinValue = uc_value_to_float($discipline["expected_min"] ?? null);
                      $expectedMaxValue = uc_value_to_float($discipline["expected_max"] ?? null);
                      $rankValues = [];
                      foreach ($assignedPlayers as $player) {
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
                        $values = array_values($rankValues);
                        if ($direction === "less") {
                          $bestValue = min($values);
                          $worstValue = max($values);
                        } else {
                          $bestValue = max($values);
                          $worstValue = min($values);
                        }
                      }
                      $playerAValue = $resultsByDiscipline[$discId][$h2hPlayerAId] ?? null;
                      $playerBValue = $resultsByDiscipline[$discId][$h2hPlayerBId] ?? null;
                      $numericA = uc_value_to_float($playerAValue);
                      $numericB = uc_value_to_float($playerBValue);
                      if ($overallMode === "abs") {
                        $pointsA = uc_absolute_points($numericA, $expectedMinValue, $expectedMaxValue, $direction);
                        $pointsB = uc_absolute_points($numericB, $expectedMinValue, $expectedMaxValue, $direction);
                        if ($pointsA === null) { $pointsA = 0; }
                        if ($pointsB === null) { $pointsB = 0; }
                      } else {
                        if ($numericA === null || $bestValue === null || $worstValue === null) {
                          $pointsA = 0;
                        } elseif ($bestValue == $worstValue) {
                          $pointsA = 2;
                        } else {
                          $ratioA = ($numericA - $worstValue) / ($bestValue - $worstValue);
                          $pointsA = 1 + $ratioA;
                        }
                        if ($numericB === null || $bestValue === null || $worstValue === null) {
                          $pointsB = 0;
                        } elseif ($bestValue == $worstValue) {
                          $pointsB = 2;
                        } else {
                          $ratioB = ($numericB - $worstValue) / ($bestValue - $worstValue);
                          $pointsB = 1 + $ratioB;
                        }
                      }
                      $displayA = uc_display_value($playerAValue, "-");
                      $displayB = uc_display_value($playerBValue, "-");
                      if ($displayA !== "-" && $unit !== "") { $displayA .= " " . $unit; }
                      if ($displayB !== "-" && $unit !== "") { $displayB .= " " . $unit; }
                      $scaleScore = function ($value) {
                        $value = max(0, min(2, (float)$value));
                        if ($value <= 1) {
                          return ($value / 1) * 30;
                        }
                        return 30 + (($value - 1) / 1) * 70;
                      };
                      $percentA = $scaleScore($pointsA);
                      $percentB = $scaleScore($pointsB);
                    ?>
                    <li class="list-item">
                      <div class="h2h-discipline">
                        <div class="result-name">
                          <strong><?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?></strong>
                        </div>
                        <?php if (!empty($unitLabel)): ?>
                          <div class="detail h2h-unit">Einheit: <?php echo htmlspecialchars($unitLabel, ENT_QUOTES, "UTF-8"); ?></div>
                        <?php endif; ?>
                      </div>
                      <div class="h2h-bars">
                        <div class="h2h-bar is-a">
                          <div class="h2h-fill" style="width: <?php echo htmlspecialchars(number_format($percentA, 2, ".", ""), ENT_QUOTES, "UTF-8"); ?>%;"></div>
                          <span class="h2h-value"><?php echo htmlspecialchars($displayA, ENT_QUOTES, "UTF-8"); ?></span>
                        </div>
                        <div class="h2h-bar is-b">
                          <div class="h2h-fill" style="width: <?php echo htmlspecialchars(number_format($percentB, 2, ".", ""), ENT_QUOTES, "UTF-8"); ?>%;"></div>
                          <span class="h2h-value"><?php echo htmlspecialchars($displayB, ENT_QUOTES, "UTF-8"); ?></span>
                        </div>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endforeach; ?>
            <?php if (!empty($h2hRadarData)): ?>
              <div class="radar-grid">
                <div class="radar-chart is-stacked">
                  <canvas id="radar-chart-h2h" width="360" height="360"></canvas>
                  <div class="radar-legend">
                    <span class="legend-item legend-player"><?php echo htmlspecialchars($playerALabel, ENT_QUOTES, "UTF-8"); ?></span>
                    <span class="legend-item legend-team"><?php echo htmlspecialchars($playerBLabel, ENT_QUOTES, "UTF-8"); ?></span>
                    <span class="legend-item legend-average">Team</span>
                  </div>
                </div>
              </div>
              <script id="radar-data-h2h" type="application/json"><?php echo json_encode($h2hRadarData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
            <?php endif; ?>
          <?php endif; ?>
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
              <?php
                $globalDisciplines = [];
                $teamDisciplines = [];
                foreach ($disciplines as $discipline) {
                  if (empty($discipline["team_id"])) {
                    $globalDisciplines[] = $discipline;
                  } else {
                    $teamDisciplines[] = $discipline;
                  }
                }
              ?>
              <?php if (!empty($teamDisciplines)): ?>
                <p class="help">Team-Disziplinen</p>
                <div class="check-grid">
                  <?php foreach ($teamDisciplines as $discipline): ?>
                    <label class="check-item">
                      <input type="checkbox" name="disciplines[]" value="<?php echo (int)$discipline["id"]; ?>"<?php echo in_array((int)$discipline["id"], $formDisciplineIds, true) ? " checked" : ""; ?>>
                      <span>
                        <?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?>
                        <span class="meta">
                          <?php echo htmlspecialchars($discipline["category"], ENT_QUOTES, "UTF-8"); ?>
                          &middot;
                          <?php
                            $unitName = trim((string)($discipline["unit"] ?? ""));
                            $unitAbbr = uc_format_unit($unitName, $unitAbbrMap);
                            $unitLabel = $unitName;
                            if ($unitAbbr !== "" && $unitAbbr !== $unitName) {
                              $unitLabel .= " (" . $unitAbbr . ")";
                            }
                          ?>
                          <?php echo htmlspecialchars($unitLabel, ENT_QUOTES, "UTF-8"); ?>
                        </span>
                      </span>
                    </label>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <?php if (!empty($globalDisciplines)): ?>
                <p class="help">Globale Disziplinen</p>
                <div class="check-grid">
                  <?php foreach ($globalDisciplines as $discipline): ?>
                    <label class="check-item">
                      <input type="checkbox" name="disciplines[]" value="<?php echo (int)$discipline["id"]; ?>"<?php echo in_array((int)$discipline["id"], $formDisciplineIds, true) ? " checked" : ""; ?>>
                      <span>
                        <?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?>
                        <span class="meta">
                          <?php echo htmlspecialchars($discipline["category"], ENT_QUOTES, "UTF-8"); ?>
                          &middot;
                          <?php
                            $unitName = trim((string)($discipline["unit"] ?? ""));
                            $unitAbbr = uc_format_unit($unitName, $unitAbbrMap);
                            $unitLabel = $unitName;
                            if ($unitAbbr !== "" && $unitAbbr !== $unitName) {
                              $unitLabel .= " (" . $unitAbbr . ")";
                            }
                          ?>
                          <?php echo htmlspecialchars($unitLabel, ENT_QUOTES, "UTF-8"); ?>
                        </span>
                      </span>
                    </label>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
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
              <div class="section-header">
                <span>Gewichtungen</span>
                <button class="info-icon js-info" type="button" aria-label="Erklärung: <?php echo $formatLabel($infoTexts["weights"] ?? "Gewichtungen legen fest, wie stark Kategorien und Disziplinen in die Gesamtwertung einfließen. 1x entspricht der Standardgewichtung."); ?>" aria-expanded="false" data-tooltip="<?php echo $formatTooltip($infoTexts["weights"] ?? "Gewichtungen legen fest, wie stark Kategorien und Disziplinen in die Gesamtwertung einfließen.\n1x entspricht der Standardgewichtung.\nKategorien gewichten den Mittelwert der Disziplinen, Disziplinen gewichten innerhalb der Kategorie."); ?>">i</button>
              </div>
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
            <button class="pill-button is-muted" type="button" onclick="window.location.href='combine.php?id=<?php echo (int)$combineId; ?>'">Abbrechen</button>
          </div>
          <?php if ($combineFeedback): ?>
            <p class="help"><?php echo htmlspecialchars($combineFeedback, ENT_QUOTES, "UTF-8"); ?></p>
          <?php endif; ?>
        </form>
      </section>
    <?php endif; ?>
  </main>
  <footer class="site-footer">
    <a class="footer-link" href="impressum.php">Impressum</a>
    <script type="text/javascript" src="https://cdnjs.buymeacoffee.com/1.0.0/button.prod.min.js" data-name="bmc-button" data-slug="jakob.christen" data-color="#ff7b4b" data-emoji="☕" data-font="Inter" data-text="Buy me a coffee" data-outline-color="#000000" data-font-color="#000000" data-coffee-color="#FFDD00"></script>
  </footer>
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

    const drawRadarChart = (radarDataEl, radarCanvas) => {
      const data = JSON.parse(radarDataEl.textContent || "[]");
      if (!data.length) return;
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

        const hasCompare = data.some((item) => Object.prototype.hasOwnProperty.call(item, "playerB"));
        const hasTeam = data.some((item) => Object.prototype.hasOwnProperty.call(item, "team"));
        if (hasCompare) {
          const playerValues = data.map((item) => item.player || 0);
          const compareValues = data.map((item) => item.playerB || 0);
          if (hasTeam) {
            const teamValues = data.map((item) => item.team || 0);
            drawShape(teamValues, muted, "rgba(111, 98, 89, 0.18)");
          }
          drawShape(compareValues, accent2, "rgba(44, 42, 74, 0.2)");
          drawShape(playerValues, accent, "rgba(255, 123, 75, 0.22)");
        } else {
          const teamValues = data.map((item) => item.team || 0);
          const playerValues = data.map((item) => item.player || 0);
          drawShape(teamValues, accent2, "rgba(44, 42, 74, 0.15)");
          drawShape(playerValues, accent, "rgba(255, 123, 75, 0.22)");
        }

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
    };

    const radarDataEl = document.getElementById("radar-data");
    const radarCanvas = document.getElementById("radar-chart");
    if (radarDataEl && radarCanvas) {
      drawRadarChart(radarDataEl, radarCanvas);
    }

    const radarDataH2h = document.getElementById("radar-data-h2h");
    const radarCanvasH2h = document.getElementById("radar-chart-h2h");
    if (radarDataH2h && radarCanvasH2h) {
      drawRadarChart(radarDataH2h, radarCanvasH2h);
    }

    const h2hPlayerA = document.querySelector("select[name=\"player_a\"]");
    const h2hPlayerB = document.querySelector("select[name=\"player_b\"]");
    if (h2hPlayerA && h2hPlayerB) {
      const syncH2hOptions = () => {
        const aValue = h2hPlayerA.value;
        const bValue = h2hPlayerB.value;
        Array.from(h2hPlayerA.options).forEach((option) => {
          option.disabled = option.value !== "" && option.value === bValue;
        });
        Array.from(h2hPlayerB.options).forEach((option) => {
          option.disabled = option.value !== "" && option.value === aValue;
        });
        if (aValue !== "" && aValue === bValue) {
          h2hPlayerB.value = "";
        }
      };
      h2hPlayerA.addEventListener("change", syncH2hOptions);
      h2hPlayerB.addEventListener("change", syncH2hOptions);
      syncH2hOptions();
    }

    const toggleButtons = document.querySelectorAll("[data-target]");
    toggleButtons.forEach((button) => {
      const targetId = button.getAttribute("data-target");
      const target = targetId ? document.getElementById(targetId) : null;
      if (!target) return;
      button.addEventListener("click", () => {
        const isHidden = target.classList.toggle("is-hidden");
        button.setAttribute("aria-expanded", String(!isHidden));
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
  </script></body>
</html>
