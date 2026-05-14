<?php

function uc_ranking_absolute(array $context): array {
  $players = $context["players"];
  $disciplines = $context["disciplines"];
  $resultsByDiscipline = $context["results_by_discipline"];
  $categoryWeightMap = uc_ranking_category_weight_map($context["category_weights"]);
  $disciplinesByCategory = uc_ranking_disciplines_by_category($disciplines);
  $disciplineRankings = [];

  foreach ($disciplines as $discipline) {
    $disciplineRankings[] = uc_ranking_absolute_discipline($discipline, $players, $resultsByDiscipline);
  }

  return [
    "mode" => "absolute",
    "overall" => [
      "mode" => "absolute",
      "entries" => uc_ranking_absolute_overall($players, $disciplinesByCategory, $resultsByDiscipline, $categoryWeightMap),
    ],
    "disciplines" => $disciplineRankings,
  ];
}

function uc_ranking_absolute_discipline(array $discipline, array $players, array $resultsByDiscipline): array {
  $discId = (int)$discipline["id"];
  $category = trim((string)($discipline["category"] ?? ""));
  if ($category === "") {
    $category = "Uncategorized";
  }
  $direction = $discipline["rating_direction"] ?? "more";
  if ($direction !== "less" && $direction !== "more") {
    $direction = "more";
  }
  $expectedMin = uc_ranking_float($discipline["expected_min"] ?? null);
  $expectedMax = uc_ranking_float($discipline["expected_max"] ?? null);
  $hasAbsoluteScale = $expectedMin !== null && $expectedMax !== null;
  $bonusAbsolute = uc_ranking_float($discipline["bonus_absolute"] ?? null);
  if ($bonusAbsolute === null || $bonusAbsolute <= 0) {
    $bonusAbsolute = 0.0;
  }
  $disciplineWeight = uc_ranking_weight($discipline["weight"] ?? 1);

  $scoreValues = [];
  $entries = [];
  foreach ($players as $player) {
    $playerId = (int)$player["id"];
    $numericValue = uc_ranking_float($resultsByDiscipline[$discId][$playerId] ?? null);
    $points = $hasAbsoluteScale
      ? uc_ranking_absolute_points($numericValue, $expectedMin, $expectedMax, $direction)
      : null;
    if ($points === null) {
      $points = 0.0;
    }
    if ($bonusAbsolute > 0 && uc_ranking_absolute_bonus_applies($numericValue, $expectedMax, $direction)) {
      $points += $bonusAbsolute;
    }
    if ($numericValue !== null && $hasAbsoluteScale) {
      $scoreValues[$playerId] = $points;
    }
    $entries[] = [
      "player_id" => $playerId,
      "rank" => null,
      "points" => round($points, 4),
      "weighted_points" => round($points * $disciplineWeight, 4),
      "result_value" => $resultsByDiscipline[$discId][$playerId] ?? null,
      "numeric_value" => $numericValue,
    ];
  }

  $ranks = uc_ranking_dense_ranks($scoreValues, "more");
  foreach ($entries as &$entry) {
    $entry["rank"] = $ranks[$entry["player_id"]] ?? null;
  }
  unset($entry);
  uc_ranking_sort_entries($entries);

  return [
    "discipline_id" => $discId,
    "discipline_name" => $discipline["discipline_name"] ?? "",
    "category" => $category,
    "rating_direction" => $direction,
    "weight" => $disciplineWeight,
    "expected_min" => $expectedMin,
    "expected_max" => $expectedMax,
    "has_absolute_scale" => $hasAbsoluteScale,
    "entries" => $entries,
  ];
}

function uc_ranking_absolute_overall(
  array $players,
  array $disciplinesByCategory,
  array $resultsByDiscipline,
  array $categoryWeightMap
): array {
  $overallScores = [];
  foreach ($players as $player) {
    $overallScores[(int)$player["id"]] = 0.0;
  }

  foreach ($disciplinesByCategory as $category => $categoryDisciplines) {
    $categoryWeight = $categoryWeightMap[$category] ?? 1.0;
    $categoryTotals = [];
    $categoryWeightSum = 0.0;
    foreach ($players as $player) {
      $categoryTotals[(int)$player["id"]] = 0.0;
    }

    foreach ($categoryDisciplines as $discipline) {
      $expectedMin = uc_ranking_float($discipline["expected_min"] ?? null);
      $expectedMax = uc_ranking_float($discipline["expected_max"] ?? null);
      if ($expectedMin === null || $expectedMax === null) {
        continue;
      }
      $discId = (int)$discipline["id"];
      $direction = $discipline["rating_direction"] ?? "more";
      if ($direction !== "less" && $direction !== "more") {
        $direction = "more";
      }
      $disciplineWeight = uc_ranking_weight($discipline["weight"] ?? 1);
      $bonusAbsolute = uc_ranking_float($discipline["bonus_absolute"] ?? null);
      if ($bonusAbsolute === null || $bonusAbsolute <= 0) {
        $bonusAbsolute = 0.0;
      }
      $categoryWeightSum += $disciplineWeight;

      foreach ($players as $player) {
        $playerId = (int)$player["id"];
        $numericValue = uc_ranking_float($resultsByDiscipline[$discId][$playerId] ?? null);
        $points = uc_ranking_absolute_points($numericValue, $expectedMin, $expectedMax, $direction);
        if ($points === null) {
          $points = 0.0;
        }
        if ($bonusAbsolute > 0 && uc_ranking_absolute_bonus_applies($numericValue, $expectedMax, $direction)) {
          $points += $bonusAbsolute;
        }
        $categoryTotals[$playerId] += $points * $disciplineWeight;
      }
    }

    if ($categoryWeightSum <= 0) {
      continue;
    }
    foreach ($players as $player) {
      $playerId = (int)$player["id"];
      $overallScores[$playerId] += ($categoryTotals[$playerId] / $categoryWeightSum) * $categoryWeight;
    }
  }

  $overallRanks = uc_ranking_dense_ranks($overallScores, "more");
  $entries = [];
  foreach ($players as $player) {
    $playerId = (int)$player["id"];
    $entries[] = [
      "player_id" => $playerId,
      "rank" => $overallRanks[$playerId] ?? null,
      "points" => round($overallScores[$playerId] ?? 0, 4),
    ];
  }
  uc_ranking_sort_entries($entries);
  return $entries;
}
