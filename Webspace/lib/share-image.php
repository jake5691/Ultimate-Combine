<?php
  require_once __DIR__ . "/radar-service.php";

  /** @var array<int, array<string, mixed>> $assignedPlayers */
  /** @var array<int, array<string, mixed>> $assignedDisciplines */
  /** @var array<string, array<int, array<string, mixed>>> $assignedDisciplinesByCategory */
  /** @var array<string, float|int|string> $combineCategoryWeights */
  /** @var array<int, float|int|string> $combineDisciplineWeights */
  /** @var array<int, array<int, mixed>> $resultsByDiscipline */
  /** @var array<string, string> $unitAbbrMap */
  /** @var array<string, string> $genderOptions */
  /** @var array<string, mixed> $combine */
  /** @var string $teamName */
  /** @var string $filterGender */
  /** @var string $filterPosition */
  /** @var string $mode */
  /** @var string $overallMode */
  /** @var string $shareFormat */
  /** @var string $shareFileBase */
  /** @var int|null $h2hPlayerAId */
  /** @var int|null $h2hPlayerBId */

  if (!function_exists("uc_share_category_score_for_player")) {
    function uc_share_category_score_for_player(
      int $selectedPlayerId,
      array $players,
      array $disciplines,
      array $disciplineWeights,
      array $resultsByDiscipline,
      string $overallMode
    ): ?float {
      $weightSum = 0.0;
      $scoreSum = 0.0;
      foreach ($disciplines as $discipline) {
        $discId = (int)$discipline["id"];
        $disciplineWeight = uc_ranking_weight($disciplineWeights[$discId] ?? 1.0);
        $direction = $discipline["rating_direction"] ?? "more";
        if ($direction !== "less" && $direction !== "more") {
          $direction = "more";
        }
        $rankValues = [];
        foreach ($players as $player) {
          $playerId = (int)$player["id"];
          $numeric = uc_ranking_float($resultsByDiscipline[$discId][$playerId] ?? null);
          if ($numeric !== null) {
            $rankValues[$playerId] = $numeric;
          }
        }
        if (empty($rankValues)) {
          continue;
        }

        $weightSum += $disciplineWeight;
        $values = array_values($rankValues);
        $bestValue = $direction === "less" ? min($values) : max($values);
        $worstValue = $direction === "less" ? max($values) : min($values);
        $numericValue = $rankValues[$selectedPlayerId] ?? null;
        $points = uc_ranking_relative_points($numericValue, $bestValue, $worstValue);
        $bonusRel = uc_ranking_weight_or_zero($discipline["bonus_relative"] ?? null);
        if ($overallMode === "sum" && $bonusRel > 0 && $numericValue !== null && $numericValue == $bestValue) {
          $points += $bonusRel;
        }
        $scoreSum += $points * $disciplineWeight;
      }

      return $weightSum > 0 ? $scoreSum / $weightSum : null;
    }
  }

  $filteredPlayers = uc_filter_players($assignedPlayers, $filterGender, $filterPosition);
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

  $overallView = uc_ranking_overall_view(
    $filteredPlayers,
    $assignedDisciplinesByCategory,
    $combineCategoryWeights,
    $combineDisciplineWeights,
    $resultsByDiscipline,
    $overallMode
  );
  $overallScores = $overallView["overall_scores"];
  $overallRanks = $overallView["overall_ranks"];
  $overallRankValues = $overallScores;
  arsort($overallRankValues, SORT_NUMERIC);

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

  $overallNameWrap = 22;
  $disciplineNameWrap = 20;
  $overallRowsHeight = 0;
  foreach ($overallRankValues as $playerId => $score) {
    foreach ($filteredPlayers as $player) {
      if ((int)$player["id"] === (int)$playerId) {
        $playerName = trim(($player["first_name"] ?? "") . " " . ($player["last_name"] ?? ""));
        $nameLines = uc_wrap_text($playerName, $overallNameWrap);
        $lineCount = max(1, count($nameLines));
        $overallRowsHeight += $lineCount * $lineHeight;
        break;
      }
    }
  }
  if ($overallRowsHeight === 0) {
    $overallRowsHeight = $lineHeight;
  }
  $heightOverall = 48 + $overallRowsHeight + $cardPadding * 2;

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
        $nameLines = uc_wrap_text($playerName, $disciplineNameWrap);
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
          "name_lines" => $nameLines,
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
      $discLabel = $discipline["discipline_name"] ?? t("common.discipline", "Disziplin");
      $rowsHeight = 0;
      foreach ($rows as $row) {
        $lineCount = isset($row["name_lines"]) ? max(1, count($row["name_lines"])) : 1;
        $rowsHeight += $lineCount * $lineHeight;
      }
      if ($rowsHeight === 0) {
        $rowsHeight = $lineHeight;
      }
      $discHeight = $disciplineTitleHeight + $rowsHeight;
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
  $modeLabel = $overallMode === "abs"
    ? t("combine.mode.absolute", "Absolut")
    : ($overallMode === "avg"
      ? t("combine.mode.relative_avg", "Ø Relativ")
      : t("combine.mode.relative", "Relativ"));
  $modeHelp = $overallMode === "abs"
    ? t("combine.mode.help.absolute", "Absolut: Punkte anhand Erwartungs-Min/Max. Disziplinen ohne Erwartungswerte werden nicht berücksichtigt.")
    : ($overallMode === "avg"
      ? t("combine.mode.help.relative_avg", "Ø Relativ: Es zählen nur Kategorien und Disziplinen, die dieser Spieler absolviert hat. Punkte werden relativ zu den Teilnehmern berechnet.")
      : t("combine.mode.help.relative", "Relativ: Punkte werden relativ zu den Teilnehmern berechnet. Nicht absolvierte Disziplinen zählen als 0 in den Kategorien."));
  $modeHelpLines = uc_wrap_text($modeHelp, 80);
  $headerExtraLines = count($modeHelpLines);
  $filterLinesCount = 0;
  if ($filterGender !== "" || $filterPosition !== "") {
    $filterParts = [];
    if ($filterGender !== "") {
      $filterParts[] = t("combine.filter.gender", "Geschlecht") . ": " . ($genderOptions[$filterGender] ?? $filterGender);
    }
    if ($filterPosition !== "") {
      $filterParts[] = t("combine.filter.position", "Position") . ": " . ($filterPosition === "handler" ? t("team.players.position_handler", "Handler") : t("team.players.position_cutter", "Cutter"));
    }
    $filterLabel = t("combine.filter.label", "Filter") . ": " . implode(" · ", $filterParts);
    $filterLinesCount = count(uc_wrap_text($filterLabel, 80));
  }
  $headerHeight = (int)round(96 * $scale) + (($headerExtraLines + $filterLinesCount) * (int)round(14 * $scale));

  $playerShareRequested = $shareFormat === "img" && $selectedPlayerId && $selectedPlayer;
  if ($playerShareRequested) {
    $playerName = trim(($selectedPlayer["first_name"] ?? "") . " " . ($selectedPlayer["last_name"] ?? ""));
    $playerSlug = uc_slug($playerName);
    $overallPoints = $overallScores[$selectedPlayerId] ?? 0;
    $overallRank = $overallRanks[$selectedPlayerId] ?? "-";
    $overallPointsPrefix = $overallMode === "avg" ? t("common.avg_prefix", "Ø ") : "";
    $overallPointsLabel = $overallPointsPrefix . uc_format_points($overallPoints) . " " . t("common.points_abbr", "P");
    $overallRankLabel = t("common.place", "Platz") . " " . $overallRank;

    $radarData = uc_radar_for_player($overallView, $combineCategoryWeights, (int)$selectedPlayerId, $overallMode);

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
        $pointsLabel = uc_format_points($points) . " " . t("common.points_abbr", "P");
        $rankLabel = isset($ranks[$selectedPlayerId]) ? (string)$ranks[$selectedPlayerId] : "-";
        $discLabel = $discipline["discipline_name"] ?? t("common.discipline", "Disziplin");
        $leftText = $discLabel;
        if ($showDisciplineWeights) {
          $leftText .= " (" . uc_display_value($disciplineWeight, "") . "x)";
        }
        if ($overallMode !== "abs" && $numericValue === null) {
          $rightText = "0 " . t("common.points_abbr", "P");
        } else {
          $rightText = t("common.place", "Platz") . " " . $rankLabel . " (" . $completedCount . ") · " . $pointsLabel;
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
      $categoryScore = uc_share_category_score_for_player(
        (int)$selectedPlayerId,
        $filteredPlayers,
        $displayDisciplines,
        $combineDisciplineWeights,
        $resultsByDiscipline,
        $overallMode
      );
      $categoryScoreLabel = $categoryScore === null ? "-" : uc_format_points($categoryScore) . " " . t("common.points_abbr", "P");
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
    $title = $combine["combine_name"] ?? t("combine.title", "Combine");
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
      $filterParts[] = t("combine.filter.gender", "Geschlecht") . ": " . ($genderOptions[$filterGender] ?? $filterGender);
    }
    if ($filterPosition !== "") {
      $filterParts[] = t("combine.filter.position", "Position") . ": " . ($filterPosition === "handler" ? t("team.players.position_handler", "Handler") : t("team.players.position_cutter", "Cutter"));
    }
    $filterLabel = "";
    if (!empty($filterParts)) {
      $filterLabel = t("combine.filter.label", "Filter") . ": " . implode(" · ", $filterParts);
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
      if (count($radarData) <= 2) {
        uc_gd_draw_bar_chart($image, $radarX, $radarY, $radarSize, $radarData, $scale, $radarColors);
      } else {
        uc_gd_draw_radar($image, $radarCenterX, $radarCenterY, $radarSize, $radarData, $scale, $radarColors);
      }
    } else {
      uc_gd_text($image, $radarCenterX, $radarCenterY - (int)round(6 * $scale), t("combine.no_data", "Keine Daten"), $muted, (int)round(12 * $scale), "center");
    }

    $legendX = $radarX + (int)round(12 * $scale);
    $legendY = $radarY + (int)round(12 * $scale);
    $legendDot = (int)round(8 * $scale);
    imagefilledellipse($image, $legendX, $legendY, $legendDot, $legendDot, $accent);
    uc_gd_text($image, $legendX + (int)round(10 * $scale), $legendY - (int)round(10 * $scale), t("common.player", "Spieler"), $muted, (int)round(11 * $scale), "left");
    $legendY += (int)round(18 * $scale);
    imagefilledellipse($image, $legendX, $legendY, $legendDot, $legendDot, $accentDark);
    uc_gd_text($image, $legendX + (int)round(10 * $scale), $legendY - (int)round(10 * $scale), t("common.team", "Team"), $muted, (int)round(11 * $scale), "left");

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
          uc_gd_text($image, $colX + $cardPadding, $cursorY, t("combine.category.score", "Kategorie-Score") . ": " . $block["score"], $muted, (int)round(11 * $scale), "left");
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
      $h2hOverallView = uc_ranking_overall_view(
        $assignedPlayers,
        $assignedDisciplinesByCategory,
        $combineCategoryWeights,
        $combineDisciplineWeights,
        $resultsByDiscipline,
        $overallMode
      );
      $overallScores = $h2hOverallView["overall_scores"];
      $overallRanks = $h2hOverallView["overall_ranks"];

      $playerALabel = trim(($h2hPlayerA["first_name"] ?? "") . " " . ($h2hPlayerA["last_name"] ?? ""));
      $playerBLabel = trim(($h2hPlayerB["first_name"] ?? "") . " " . ($h2hPlayerB["last_name"] ?? ""));
      $overallPointsPrefix = $overallMode === "avg" ? t("common.avg_prefix", "Ø ") : "";
      $overallPointsA = $overallScores[$h2hPlayerAId] ?? 0;
      $overallPointsB = $overallScores[$h2hPlayerBId] ?? 0;
      $overallRankA = $overallRanks[$h2hPlayerAId] ?? "-";
      $overallRankB = $overallRanks[$h2hPlayerBId] ?? "-";

      $h2hRadarData = uc_radar_for_h2h_from_view(
        $h2hOverallView,
        $combineCategoryWeights,
        (int)$h2hPlayerAId,
        (int)$h2hPlayerBId,
        $overallMode
      );

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
            "label" => $discipline["discipline_name"] ?? t("common.discipline", "Disziplin"),
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
      $summaryNameLineHeight = $summaryNameSize + max($summaryLineGap, (int)round(8 * $scale));
      $summaryNameToMetaGap = (int)round(8 * $scale);

      $summaryMaxWidth = $cardWidth - ($radarSize + $summaryGap + ($cardPadding * 2));
      $summaryNameWrap = max(10, (int)floor($summaryMaxWidth / max(1, ($summaryNameSize * 0.6))));
      $summaryLinesA = uc_wrap_text($playerALabel, $summaryNameWrap);
      $summaryLinesB = uc_wrap_text($playerBLabel, $summaryNameWrap);
      $summaryBlockAHeight = max($summaryNameSize, $summaryNameLineHeight * count($summaryLinesA));
      $summaryBlockBHeight = max($summaryNameSize, $summaryNameLineHeight * count($summaryLinesB));
      $summaryHeight = $summaryBlockAHeight + $summaryNameToMetaGap + $summaryMetaSize + (int)round(14 * $scale) + $summaryBlockBHeight + $summaryNameToMetaGap + $summaryMetaSize;
      $summaryCardHeight = max($radarSize + ($cardPadding * 2), $summaryHeight + ($cardPadding * 2));

      $h2hTitleSize = (int)round(16 * $scale);
      $baseH2hTitleHeight = (int)round(24 * $scale);
      $h2hTitleHeight = $baseH2hTitleHeight;

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
      $nameLines = [$nameLine];
      [$nameWidth] = uc_gd_text_box($nameLine, $nameSize);
      if ($nameWidth > $maxNameWidth) {
        $headerWrap = max(12, (int)floor($maxNameWidth / max(1, ($nameSize * 0.6))));
        $nameLines = array_merge(
          uc_wrap_text($playerALabel, $headerWrap),
          ["vs. " . $playerBLabel]
        );
        $wrapped = [];
        foreach ($nameLines as $line) {
          foreach (uc_wrap_text($line, $headerWrap) as $wrappedLine) {
            $wrapped[] = $wrappedLine;
          }
        }
        $nameLines = $wrapped;
      }
      $nameLineHeight = (int)round($nameSize + (6 * $scale));
      $h2hTitleHeight = max($baseH2hTitleHeight, $nameLineHeight * count($nameLines));
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
      $title = $combine["combine_name"] ?? t("combine.title", "Combine");
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
      $nameX = $x + (int)round($radarSize / 2) + $cardPadding;
      $nameY = $y + (int)round(2 * $scale);
      foreach ($nameLines as $line) {
        uc_gd_text($image, $nameX, $nameY, $line, $accentDark, $nameSize, "center");
        $nameY += $nameLineHeight;
      }

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
        if (count($h2hRadarData) <= 2) {
          uc_gd_draw_bar_chart($image, $radarX, $radarY, $radarSize, $h2hRadarData, $scale, $radarColors);
        } else {
          uc_gd_draw_radar($image, $radarCenterX, $radarCenterY, $radarSize, $h2hRadarData, $scale, $radarColors);
        }
      } else {
        uc_gd_text($image, $radarCenterX, $radarCenterY - (int)round(6 * $scale), t("combine.no_data", "Keine Daten"), $muted, (int)round(12 * $scale), "center");
      }

      $legendX = $radarX + (int)round(12 * $scale);
      $legendY = $radarY + (int)round(12 * $scale);
      $legendDot = (int)round(8 * $scale);
      $legendFontSize = (int)round(11 * $scale);
      $legendLineHeight = (int)round(16 * $scale);
      $legendTextOffset = (int)round(12 * $scale);
      $legendGroupGap = (int)round(6 * $scale);
      $legendMaxWidth = $radarSize - (int)round(24 * $scale);
      $legendWrap = max(8, (int)floor($legendMaxWidth / max(1, ($legendFontSize * 0.6))));
      $legendLinesA = uc_wrap_text($playerALabel, $legendWrap);
      $legendLinesB = uc_wrap_text($playerBLabel, $legendWrap);

      imagefilledellipse($image, $legendX, $legendY, $legendDot, $legendDot, $accent);
      $legendTextY = $legendY - $legendTextOffset;
      foreach ($legendLinesA as $line) {
        uc_gd_text($image, $legendX + (int)round(10 * $scale), $legendTextY, $line, $muted, $legendFontSize, "left");
        $legendTextY += $legendLineHeight;
      }
      $legendY = $legendTextY + $legendGroupGap;

      imagefilledellipse($image, $legendX, $legendY, $legendDot, $legendDot, $accentDark);
      $legendTextY = $legendY - $legendTextOffset;
      foreach ($legendLinesB as $line) {
        uc_gd_text($image, $legendX + (int)round(10 * $scale), $legendTextY, $line, $muted, $legendFontSize, "left");
        $legendTextY += $legendLineHeight;
      }
      $legendY = $legendTextY + $legendGroupGap;

      imagefilledellipse($image, $legendX, $legendY, $legendDot, $legendDot, $muted);
      uc_gd_text($image, $legendX + (int)round(10 * $scale), $legendY - $legendTextOffset, t("common.team", "Team"), $muted, $legendFontSize, "left");

      $summaryX = $radarX + $radarSize + $summaryGap;
      $summaryY = $y + $cardPadding;
      foreach ($summaryLinesA as $line) {
        uc_gd_text($image, $summaryX, $summaryY, $line, $ink, $summaryNameSize, "left");
        $summaryY += $summaryNameLineHeight;
      }
      $summaryY += $summaryNameToMetaGap;
      uc_gd_text($image, $summaryX, $summaryY, t("common.place", "Platz") . " " . $overallRankA . " · " . $overallPointsPrefix . uc_format_points($overallPointsA) . " " . t("common.points_abbr", "P"), $accentDark, $summaryMetaSize, "left");
      $summaryY += $summaryMetaSize + (int)round(14 * $scale);
      foreach ($summaryLinesB as $line) {
        uc_gd_text($image, $summaryX, $summaryY, $line, $ink, $summaryNameSize, "left");
        $summaryY += $summaryNameLineHeight;
      }
      $summaryY += $summaryNameToMetaGap;
      uc_gd_text($image, $summaryX, $summaryY, t("common.place", "Platz") . " " . $overallRankB . " · " . $overallPointsPrefix . uc_format_points($overallPointsB) . " " . t("common.points_abbr", "P"), $accentDark, $summaryMetaSize, "left");

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
  $title = $combine["combine_name"] ?? t("combine.title", "Combine");
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
    $filterParts[] = t("combine.filter.gender", "Geschlecht") . ": " . ($genderOptions[$filterGender] ?? $filterGender);
  }
  if ($filterPosition !== "") {
    $filterParts[] = t("combine.filter.position", "Position") . ": " . ($filterPosition === "handler" ? t("team.players.position_handler", "Handler") : t("team.players.position_cutter", "Cutter"));
  }
  $filterLabel = "";
  if (!empty($filterParts)) {
    $filterLabel = t("combine.filter.label", "Filter") . ": " . implode(" · ", $filterParts);
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
        $nameLines = uc_wrap_text($playerName, $overallNameWrap);
        if (empty($nameLines)) {
          $nameLines = [$playerName];
        }
        $rankLabel = $overallRanks[$playerId] ?? "-";
        $scoreLabel = ($overallMode === "avg" ? t("common.avg_prefix", "Ø ") : "") . uc_format_points($score) . " " . t("common.points_abbr", "P");
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
          $lineY = $rowY;
          foreach ($nameLines as $lineIndex => $line) {
            uc_gd_text($image, $textX, $lineY, $line, $ink, (int)round(12 * $scale), "left");
            $lineY += $lineHeight;
          }
        } else {
          $firstLine = array_shift($nameLines);
          $labelLine = $rankLabel . ". " . ($firstLine ?? $playerName);
          uc_gd_text($image, $textX, $rowY, $labelLine, $ink, (int)round(12 * $scale), "left");
          $lineY = $rowY + $lineHeight;
          foreach ($nameLines as $line) {
            uc_gd_text($image, $textX + (int)round(12 * $scale), $lineY, $line, $ink, (int)round(12 * $scale), "left");
            $lineY += $lineHeight;
          }
        }
        uc_gd_text($image, $x + $cardWidth - $cardPadding, $rowY, $scoreLabel, $ink, (int)round(12 * $scale), "right");
        $rowY += max(1, count($nameLines) + (in_array((int)$rankLabel, [1, 2, 3], true) ? 0 : 1)) * $lineHeight;
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
          $nameLines = $row["name_lines"] ?? uc_wrap_text($playerName, $disciplineNameWrap);
          if (empty($nameLines)) {
            $nameLines = [$playerName];
          }
          $display = uc_display_value($row["value"], "-");
          if ($display !== "-" && $disc["unit_abbr"] !== "") {
            $display .= " " . $disc["unit_abbr"];
          }
          $pointsLabel = uc_format_points($row["points"]) . " " . t("common.points_abbr", "P");
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
            $lineY = $rowY;
            foreach ($nameLines as $line) {
              uc_gd_text($image, $textX, $lineY, $line, $ink, (int)round(11 * $scale), "left");
              $lineY += $lineHeight;
            }
          } else {
            $firstLine = array_shift($nameLines);
            $labelLine = $rankLabel . ". " . ($firstLine ?? $playerName);
            uc_gd_text($image, $textX, $rowY, $labelLine, $ink, (int)round(11 * $scale), "left");
            $lineY = $rowY + $lineHeight;
            foreach ($nameLines as $line) {
              uc_gd_text($image, $textX + (int)round(10 * $scale), $lineY, $line, $ink, (int)round(11 * $scale), "left");
              $lineY += $lineHeight;
            }
          }
          uc_gd_text($image, $colX + $colWidth - $cardPadding, $rowY, $display . " · " . $pointsLabel, $ink, (int)round(11 * $scale), "right");
          $rowY += max(1, count($nameLines) + (in_array((int)$rankLabel, [1, 2, 3], true) ? 0 : 1)) * $lineHeight;
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
