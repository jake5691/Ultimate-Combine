<?php

function uc_ranking_relative(array $context): array {
  $players = $context["players"];
  $disciplines = $context["disciplines"];
  $resultsByDiscipline = $context["results_by_discipline"];
  $categoryWeightMap = uc_ranking_category_weight_map($context["category_weights"]);
  $disciplinesByCategory = uc_ranking_disciplines_by_category($disciplines);
  $disciplineRankings = [];

  foreach ($disciplines as $discipline) {
    $disciplineRankings[] = uc_ranking_relative_discipline($discipline, $players, $resultsByDiscipline);
  }

  return [
    "mode" => "relative",
    "overall" => [
      "mode" => "sum",
      "entries" => uc_ranking_relative_overall($players, $disciplinesByCategory, $resultsByDiscipline, $categoryWeightMap),
    ],
    "disciplines" => $disciplineRankings,
  ];
}

function uc_ranking_relative_discipline(array $discipline, array $players, array $resultsByDiscipline): array {
  $discId = (int)$discipline["id"];
  $category = trim((string)($discipline["category"] ?? ""));
  if ($category === "") {
    $category = "Uncategorized";
  }
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

  $bestValue = null;
  $worstValue = null;
  if (!empty($rankValues)) {
    $values = array_values($rankValues);
    $bestValue = $direction === "less" ? min($values) : max($values);
    $worstValue = $direction === "less" ? max($values) : min($values);
  }

  $ranks = uc_ranking_dense_ranks($rankValues, $direction);
  $bonusRelative = uc_ranking_float($discipline["bonus_relative"] ?? null);
  if ($bonusRelative === null || $bonusRelative <= 0) {
    $bonusRelative = 0.0;
  }
  $disciplineWeight = uc_ranking_weight($discipline["weight"] ?? 1);
  $entries = [];

  foreach ($players as $player) {
    $playerId = (int)$player["id"];
    $numericValue = $rankValues[$playerId] ?? null;
    $points = uc_ranking_relative_points($numericValue, $bestValue, $worstValue);
    if ($bonusRelative > 0 && $numericValue !== null && $bestValue !== null && $numericValue == $bestValue) {
      $points += $bonusRelative;
    }
    $entries[] = [
      "player_id" => $playerId,
      "rank" => $ranks[$playerId] ?? null,
      "points" => round($points, 4),
      "weighted_points" => round($points * $disciplineWeight, 4),
      "result_value" => $resultsByDiscipline[$discId][$playerId] ?? null,
      "numeric_value" => $numericValue,
    ];
  }

  uc_ranking_sort_entries($entries);
  return [
    "discipline_id" => $discId,
    "discipline_name" => $discipline["discipline_name"] ?? "",
    "category" => $category,
    "rating_direction" => $direction,
    "weight" => $disciplineWeight,
    "best_value" => $bestValue,
    "worst_value" => $worstValue,
    "entries" => $entries,
  ];
}

function uc_ranking_relative_overall(
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
      $discId = (int)$discipline["id"];
      $direction = $discipline["rating_direction"] ?? "more";
      if ($direction !== "less" && $direction !== "more") {
        $direction = "more";
      }
      $disciplineWeight = uc_ranking_weight($discipline["weight"] ?? 1);
      $bonusRelative = uc_ranking_float($discipline["bonus_relative"] ?? null);
      if ($bonusRelative === null || $bonusRelative <= 0) {
        $bonusRelative = 0.0;
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

      $categoryWeightSum += $disciplineWeight;
      $values = array_values($rankValues);
      $bestValue = $direction === "less" ? min($values) : max($values);
      $worstValue = $direction === "less" ? max($values) : min($values);

      foreach ($players as $player) {
        $playerId = (int)$player["id"];
        $numericValue = $rankValues[$playerId] ?? null;
        $points = uc_ranking_relative_points($numericValue, $bestValue, $worstValue);
        if ($bonusRelative > 0 && $numericValue !== null && $bestValue !== null && $numericValue == $bestValue) {
          $points += $bonusRelative;
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
