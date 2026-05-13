<?php

function uc_api_result_float($value): ?float {
  $value = trim((string)$value);
  if ($value === "") {
    return null;
  }
  $value = str_replace(",", ".", $value);
  return is_numeric($value) ? (float)$value : null;
}

function uc_api_result_weight($value): float {
  $weight = uc_api_result_float($value);
  if ($weight === null || $weight <= 0) {
    return 1.0;
  }
  return $weight;
}

function uc_api_relative_points(?float $value, ?float $bestValue, ?float $worstValue): float {
  if ($value === null || $bestValue === null || $worstValue === null) {
    return 0.0;
  }
  if ($bestValue == $worstValue) {
    return 2.0;
  }
  return 1 + (($value - $worstValue) / ($bestValue - $worstValue));
}

function uc_api_absolute_points($value, $min, $max, string $direction): ?float {
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

function uc_api_absolute_bonus_applies(?float $numericValue, ?float $bestExpected, string $direction): bool {
  if ($numericValue === null || $bestExpected === null) {
    return false;
  }
  if ($direction === "less") {
    return $numericValue <= $bestExpected;
  }
  return $numericValue >= $bestExpected;
}

function uc_api_dense_ranks(array $rankValues, string $direction = "more"): array {
  if ($direction === "less") {
    asort($rankValues, SORT_NUMERIC);
  } else {
    arsort($rankValues, SORT_NUMERIC);
  }

  $ranks = [];
  $pos = 0;
  $rank = 0;
  $prev = null;
  foreach ($rankValues as $playerId => $value) {
    $pos++;
    if ($prev === null || $value != $prev) {
      $rank = $pos;
      $prev = $value;
    }
    $ranks[(int)$playerId] = $rank;
  }
  return $ranks;
}

function uc_api_sort_rank_entries(array &$entries): void {
  usort($entries, function ($a, $b) {
    if ($a["rank"] === null && $b["rank"] === null) {
      return $a["player_id"] <=> $b["player_id"];
    }
    if ($a["rank"] === null) {
      return 1;
    }
    if ($b["rank"] === null) {
      return -1;
    }
    if ($a["rank"] === $b["rank"]) {
      return $a["player_id"] <=> $b["player_id"];
    }
    return $a["rank"] <=> $b["rank"];
  });
}

function uc_api_results_context(PDO $pdo, int $teamId, int $combineId): array {
  $stmt = $pdo->prepare(
    "SELECT id, combine_name, event_date, combine_location, combine_notes, created_at
     FROM combines
     WHERE id = :id AND team_id = :team_id"
  );
  $stmt->execute([
    ":id" => $combineId,
    ":team_id" => $teamId,
  ]);
  $combine = $stmt->fetch();

  if (!$combine) {
    uc_api_error("not_found", "Combine not found.", 404);
  }

  $stmt = $pdo->prepare(
    "SELECT players.id,
            players.first_name,
            players.last_name,
            players.jersey_number,
            players.gender,
            players.position_handler,
            players.position_cutter
     FROM combine_players
     INNER JOIN players ON players.id = combine_players.player_id
     WHERE combine_players.combine_id = :combine_id
       AND players.team_id = :team_id
     ORDER BY players.first_name ASC, players.last_name ASC"
  );
  $stmt->execute([
    ":combine_id" => $combineId,
    ":team_id" => $teamId,
  ]);
  $players = array_map("uc_api_normalize_row", $stmt->fetchAll());

  $stmt = $pdo->prepare(
    "SELECT disciplines.id,
            disciplines.team_id,
            disciplines.discipline_name,
            disciplines.description,
            disciplines.unit,
            disciplines.category,
            disciplines.rating_direction,
            disciplines.expected_min,
            disciplines.expected_max,
            disciplines.bonus_relative,
            disciplines.bonus_absolute,
            combine_disciplines.weight
     FROM combine_disciplines
     INNER JOIN disciplines ON disciplines.id = combine_disciplines.discipline_id
     WHERE combine_disciplines.combine_id = :combine_id
       AND (disciplines.team_id = :team_id OR disciplines.team_id IS NULL)
     ORDER BY disciplines.category ASC, disciplines.discipline_name ASC"
  );
  $stmt->execute([
    ":combine_id" => $combineId,
    ":team_id" => $teamId,
  ]);
  $disciplines = [];
  foreach ($stmt->fetchAll() as $row) {
    $row = uc_api_normalize_row($row);
    $row["scope"] = $row["team_id"] === null ? "global" : "team";
    $disciplines[] = $row;
  }

  $stmt = $pdo->prepare(
    "SELECT category, weight
     FROM combine_category_weights
     WHERE combine_id = :combine_id
     ORDER BY category ASC"
  );
  $stmt->execute([":combine_id" => $combineId]);
  $categoryWeights = array_map("uc_api_normalize_row", $stmt->fetchAll());

  $stmt = $pdo->prepare(
    "SELECT combine_results.discipline_id,
            combine_results.player_id,
            combine_results.result_value,
            combine_results.updated_at
     FROM combine_results
     INNER JOIN players ON players.id = combine_results.player_id
     INNER JOIN disciplines ON disciplines.id = combine_results.discipline_id
     WHERE combine_results.combine_id = :combine_id
       AND players.team_id = :team_id
       AND (disciplines.team_id = :team_id OR disciplines.team_id IS NULL)
     ORDER BY combine_results.discipline_id ASC, combine_results.player_id ASC"
  );
  $stmt->execute([
    ":combine_id" => $combineId,
    ":team_id" => $teamId,
  ]);
  $results = array_map("uc_api_normalize_row", $stmt->fetchAll());

  $resultsByDiscipline = [];
  foreach ($results as $result) {
    $discId = (int)$result["discipline_id"];
    $playerId = (int)$result["player_id"];
    $resultsByDiscipline[$discId][$playerId] = $result["result_value"];
  }

  return [
    "combine" => uc_api_normalize_row($combine),
    "players" => $players,
    "disciplines" => $disciplines,
    "category_weights" => $categoryWeights,
    "results" => $results,
    "results_by_discipline" => $resultsByDiscipline,
  ];
}

function uc_api_results_meta(array $context, int $teamId): array {
  return [
    "team_id" => $teamId,
    "player_count" => count($context["players"]),
    "discipline_count" => count($context["disciplines"]),
    "result_count" => count($context["results"]),
  ];
}

function uc_api_category_weight_map(array $categoryWeights): array {
  $map = [];
  foreach ($categoryWeights as $row) {
    $category = trim((string)($row["category"] ?? ""));
    if ($category !== "") {
      $map[$category] = uc_api_result_weight($row["weight"] ?? 1);
    }
  }
  return $map;
}

function uc_api_disciplines_by_category(array $disciplines): array {
  $byCategory = [];
  foreach ($disciplines as $discipline) {
    $category = trim((string)($discipline["category"] ?? ""));
    if ($category === "") {
      $category = "Uncategorized";
    }
    $byCategory[$category][] = $discipline;
  }
  return $byCategory;
}

function uc_api_relative_rankings(array $context): array {
  $players = $context["players"];
  $disciplines = $context["disciplines"];
  $resultsByDiscipline = $context["results_by_discipline"];
  $categoryWeightMap = uc_api_category_weight_map($context["category_weights"]);
  $disciplinesByCategory = uc_api_disciplines_by_category($disciplines);
  $disciplineRankings = [];

  foreach ($disciplines as $discipline) {
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
      $numeric = uc_api_result_float($resultsByDiscipline[$discId][$playerId] ?? null);
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

    $ranks = uc_api_dense_ranks($rankValues, $direction);
    $bonusRelative = uc_api_result_float($discipline["bonus_relative"] ?? null);
    if ($bonusRelative === null || $bonusRelative <= 0) {
      $bonusRelative = 0.0;
    }
    $disciplineWeight = uc_api_result_weight($discipline["weight"] ?? 1);
    $entries = [];

    foreach ($players as $player) {
      $playerId = (int)$player["id"];
      $numericValue = $rankValues[$playerId] ?? null;
      $points = uc_api_relative_points($numericValue, $bestValue, $worstValue);
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

    uc_api_sort_rank_entries($entries);
    $disciplineRankings[] = [
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
      $disciplineWeight = uc_api_result_weight($discipline["weight"] ?? 1);
      $bonusRelative = uc_api_result_float($discipline["bonus_relative"] ?? null);
      if ($bonusRelative === null || $bonusRelative <= 0) {
        $bonusRelative = 0.0;
      }

      $rankValues = [];
      foreach ($players as $player) {
        $playerId = (int)$player["id"];
        $numeric = uc_api_result_float($resultsByDiscipline[$discId][$playerId] ?? null);
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
        $points = uc_api_relative_points($numericValue, $bestValue, $worstValue);
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

  $overallRanks = uc_api_dense_ranks($overallScores, "more");
  $overallEntries = [];
  foreach ($players as $player) {
    $playerId = (int)$player["id"];
    $overallEntries[] = [
      "player_id" => $playerId,
      "rank" => $overallRanks[$playerId] ?? null,
      "points" => round($overallScores[$playerId] ?? 0, 4),
    ];
  }
  uc_api_sort_rank_entries($overallEntries);

  return [
    "mode" => "relative",
    "overall" => [
      "mode" => "sum",
      "entries" => $overallEntries,
    ],
    "disciplines" => $disciplineRankings,
  ];
}

function uc_api_absolute_rankings(array $context): array {
  $players = $context["players"];
  $disciplines = $context["disciplines"];
  $resultsByDiscipline = $context["results_by_discipline"];
  $categoryWeightMap = uc_api_category_weight_map($context["category_weights"]);
  $disciplinesByCategory = uc_api_disciplines_by_category($disciplines);
  $disciplineRankings = [];

  foreach ($disciplines as $discipline) {
    $discId = (int)$discipline["id"];
    $category = trim((string)($discipline["category"] ?? ""));
    if ($category === "") {
      $category = "Uncategorized";
    }
    $direction = $discipline["rating_direction"] ?? "more";
    if ($direction !== "less" && $direction !== "more") {
      $direction = "more";
    }
    $expectedMin = uc_api_result_float($discipline["expected_min"] ?? null);
    $expectedMax = uc_api_result_float($discipline["expected_max"] ?? null);
    $hasAbsoluteScale = $expectedMin !== null && $expectedMax !== null;
    $bonusAbsolute = uc_api_result_float($discipline["bonus_absolute"] ?? null);
    if ($bonusAbsolute === null || $bonusAbsolute <= 0) {
      $bonusAbsolute = 0.0;
    }
    $disciplineWeight = uc_api_result_weight($discipline["weight"] ?? 1);

    $scoreValues = [];
    $entries = [];
    foreach ($players as $player) {
      $playerId = (int)$player["id"];
      $numericValue = uc_api_result_float($resultsByDiscipline[$discId][$playerId] ?? null);
      $points = $hasAbsoluteScale
        ? uc_api_absolute_points($numericValue, $expectedMin, $expectedMax, $direction)
        : null;
      if ($points === null) {
        $points = 0.0;
      }
      if ($bonusAbsolute > 0 && uc_api_absolute_bonus_applies($numericValue, $expectedMax, $direction)) {
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

    $ranks = uc_api_dense_ranks($scoreValues, "more");
    foreach ($entries as &$entry) {
      $entry["rank"] = $ranks[$entry["player_id"]] ?? null;
    }
    unset($entry);
    uc_api_sort_rank_entries($entries);

    $disciplineRankings[] = [
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
      $expectedMin = uc_api_result_float($discipline["expected_min"] ?? null);
      $expectedMax = uc_api_result_float($discipline["expected_max"] ?? null);
      if ($expectedMin === null || $expectedMax === null) {
        continue;
      }
      $discId = (int)$discipline["id"];
      $direction = $discipline["rating_direction"] ?? "more";
      if ($direction !== "less" && $direction !== "more") {
        $direction = "more";
      }
      $disciplineWeight = uc_api_result_weight($discipline["weight"] ?? 1);
      $bonusAbsolute = uc_api_result_float($discipline["bonus_absolute"] ?? null);
      if ($bonusAbsolute === null || $bonusAbsolute <= 0) {
        $bonusAbsolute = 0.0;
      }
      $categoryWeightSum += $disciplineWeight;

      foreach ($players as $player) {
        $playerId = (int)$player["id"];
        $numericValue = uc_api_result_float($resultsByDiscipline[$discId][$playerId] ?? null);
        $points = uc_api_absolute_points($numericValue, $expectedMin, $expectedMax, $direction);
        if ($points === null) {
          $points = 0.0;
        }
        if ($bonusAbsolute > 0 && uc_api_absolute_bonus_applies($numericValue, $expectedMax, $direction)) {
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

  $overallRanks = uc_api_dense_ranks($overallScores, "more");
  $overallEntries = [];
  foreach ($players as $player) {
    $playerId = (int)$player["id"];
    $overallEntries[] = [
      "player_id" => $playerId,
      "rank" => $overallRanks[$playerId] ?? null,
      "points" => round($overallScores[$playerId] ?? 0, 4),
    ];
  }
  uc_api_sort_rank_entries($overallEntries);

  return [
    "mode" => "absolute",
    "overall" => [
      "mode" => "absolute",
      "entries" => $overallEntries,
    ],
    "disciplines" => $disciplineRankings,
  ];
}
