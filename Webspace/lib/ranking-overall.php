<?php

function uc_ranking_overall_view(
  array $players,
  array $disciplinesByCategory,
  array $categoryWeightsMap,
  array $disciplineWeightsMap,
  array $resultsByDiscipline,
  string $mode = "sum"
): array {
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

  foreach ($players as $player) {
    $playerId = (int)$player["id"];
    $overallScoresSum[$playerId] = 0.0;
    $overallScoresAvg[$playerId] = 0.0;
    $overallScoresAbs[$playerId] = 0.0;
    $overallCategoryCounts[$playerId] = 0;
  }

  foreach ($disciplinesByCategory as $category => $categoryDisciplines) {
    $categoryWeight = uc_ranking_weight($categoryWeightsMap[$category] ?? 1.0);
    $categoryWeights[$category] = $categoryWeight;
    $categoryTotals = [];
    $categoryTotalsAbs = [];
    $categoryTotalsAvg = [];
    $categoryWeightSumsAvg = [];
    $categoryWeightSumAll = 0.0;
    $categoryWeightSumAllAbs = 0.0;
    $disciplineCount = 0;

    foreach ($players as $player) {
      $playerId = (int)$player["id"];
      $categoryTotals[$playerId] = 0.0;
      $categoryTotalsAbs[$playerId] = 0.0;
      $categoryTotalsAvg[$playerId] = 0.0;
      $categoryWeightSumsAvg[$playerId] = 0.0;
    }

    foreach ($categoryDisciplines as $discipline) {
      uc_ranking_apply_discipline_to_category(
        $discipline,
        $players,
        $disciplineWeightsMap,
        $resultsByDiscipline,
        $categoryTotals,
        $categoryTotalsAbs,
        $categoryTotalsAvg,
        $categoryWeightSumsAvg,
        $categoryWeightSumAll,
        $categoryWeightSumAllAbs,
        $disciplineCount
      );
    }

    if ($disciplineCount === 0 || $categoryWeightSumAll <= 0) {
      continue;
    }

    uc_ranking_finish_category(
      $players,
      $category,
      $categoryWeight,
      $categoryTotals,
      $categoryTotalsAbs,
      $categoryTotalsAvg,
      $categoryWeightSumsAvg,
      $categoryWeightSumAll,
      $categoryWeightSumAllAbs,
      $overallScoresSum,
      $overallScoresAvg,
      $overallScoresAbs,
      $overallCategoryCounts,
      $categoryAverages,
      $categoryAveragesAbs,
      $categoryAveragesAvg,
      $categoryTeamAverages,
      $categoryTeamWeightedAverages,
      $categoryTeamAveragesAbs,
      $categoryTeamWeightedAveragesAbs,
      $categoryTeamAveragesAvg
    );
  }

  foreach ($overallScoresAvg as $playerId => $score) {
    $count = $overallCategoryCounts[$playerId] ?? 0;
    if ($count > 0) {
      $overallScoresAvg[$playerId] = $score / $count;
    }
  }

  if ($mode === "avg") {
    $overallScores = $overallScoresAvg;
  } elseif ($mode === "abs") {
    $overallScores = $overallScoresAbs;
  } else {
    $overallScores = $overallScoresSum;
  }

  return [
    "overall_scores" => $overallScores,
    "overall_scores_sum" => $overallScoresSum,
    "overall_scores_avg" => $overallScoresAvg,
    "overall_scores_abs" => $overallScoresAbs,
    "overall_ranks" => uc_ranking_dense_ranks($overallScores, "more"),
    "category_averages" => $categoryAverages,
    "category_averages_abs" => $categoryAveragesAbs,
    "category_averages_avg" => $categoryAveragesAvg,
    "category_team_averages" => $categoryTeamAverages,
    "category_team_weighted_averages" => $categoryTeamWeightedAverages,
    "category_team_averages_abs" => $categoryTeamAveragesAbs,
    "category_team_weighted_averages_abs" => $categoryTeamWeightedAveragesAbs,
    "category_team_averages_avg" => $categoryTeamAveragesAvg,
    "category_weights" => $categoryWeights,
  ];
}

function uc_ranking_apply_discipline_to_category(
  array $discipline,
  array $players,
  array $disciplineWeightsMap,
  array $resultsByDiscipline,
  array &$categoryTotals,
  array &$categoryTotalsAbs,
  array &$categoryTotalsAvg,
  array &$categoryWeightSumsAvg,
  float &$categoryWeightSumAll,
  float &$categoryWeightSumAllAbs,
  int &$disciplineCount
): void {
  $discId = (int)$discipline["id"];
  $disciplineWeight = uc_ranking_weight($disciplineWeightsMap[$discId] ?? 1.0);
  $direction = $discipline["rating_direction"] ?? "more";
  if ($direction !== "less" && $direction !== "more") {
    $direction = "more";
  }
  $expectedMinValue = uc_ranking_float($discipline["expected_min"] ?? null);
  $expectedMaxValue = uc_ranking_float($discipline["expected_max"] ?? null);
  $bonusRel = uc_ranking_weight_or_zero($discipline["bonus_relative"] ?? null);
  $bonusAbs = uc_ranking_weight_or_zero($discipline["bonus_absolute"] ?? null);
  $hasAbsolute = $expectedMinValue !== null && $expectedMaxValue !== null;
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
    $disciplineCount++;
    $categoryWeightSumAll += $disciplineWeight;
    $values = array_values($rankValues);
    $bestValue = $direction === "less" ? min($values) : max($values);
    $worstValue = $direction === "less" ? max($values) : min($values);
  }
  if ($hasAbsolute) {
    $categoryWeightSumAllAbs += $disciplineWeight;
  }

  foreach ($players as $player) {
    $playerId = (int)$player["id"];
    $numericValue = $rankValues[$playerId] ?? null;
    $pointsBase = uc_ranking_relative_points($numericValue, $bestValue, $worstValue);
    $pointsSum = $pointsBase;
    if ($bonusRel > 0 && $numericValue !== null && $bestValue !== null && $numericValue == $bestValue) {
      $pointsSum += $bonusRel;
    }
    $categoryTotals[$playerId] += $pointsSum * $disciplineWeight;

    if ($hasAbsolute) {
      $absolutePoints = uc_ranking_absolute_points($numericValue, $expectedMinValue, $expectedMaxValue, $direction);
      if ($absolutePoints === null) {
        $absolutePoints = 0.0;
      }
      if ($bonusAbs > 0 && uc_ranking_absolute_bonus_applies($numericValue, $expectedMaxValue, $direction)) {
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

function uc_ranking_finish_category(
  array $players,
  string $category,
  float $categoryWeight,
  array $categoryTotals,
  array $categoryTotalsAbs,
  array $categoryTotalsAvg,
  array $categoryWeightSumsAvg,
  float $categoryWeightSumAll,
  float $categoryWeightSumAllAbs,
  array &$overallScoresSum,
  array &$overallScoresAvg,
  array &$overallScoresAbs,
  array &$overallCategoryCounts,
  array &$categoryAverages,
  array &$categoryAveragesAbs,
  array &$categoryAveragesAvg,
  array &$categoryTeamAverages,
  array &$categoryTeamWeightedAverages,
  array &$categoryTeamAveragesAbs,
  array &$categoryTeamWeightedAveragesAbs,
  array &$categoryTeamAveragesAvg
): void {
  $teamSum = 0.0;
  $teamCount = 0;
  $teamSumAbs = 0.0;
  $teamCountAbs = 0;
  $teamSumAvg = 0.0;
  $teamCountAvg = 0;
  $hasAbsoluteCategory = $categoryWeightSumAllAbs > 0;

  foreach ($players as $player) {
    $playerId = (int)$player["id"];
    $categoryAverage = $categoryTotals[$playerId] / $categoryWeightSumAll;
    $overallScoresSum[$playerId] += $categoryAverage * $categoryWeight;
    $categoryAverages[$category][$playerId] = $categoryAverage;
    $teamSum += $categoryAverage;
    $teamCount++;

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
      $overallCategoryCounts[$playerId]++;
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

function uc_ranking_weight_or_zero($value): float {
  $weight = uc_ranking_float($value);
  if ($weight === null || $weight <= 0) {
    return 0.0;
  }
  return $weight;
}
