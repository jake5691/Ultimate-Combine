<?php

function uc_ranking_float($value): ?float {
  $value = trim((string)$value);
  if ($value === "") {
    return null;
  }
  $value = str_replace(",", ".", $value);
  return is_numeric($value) ? (float)$value : null;
}

function uc_ranking_weight($value): float {
  $weight = uc_ranking_float($value);
  if ($weight === null || $weight <= 0) {
    return 1.0;
  }
  return $weight;
}

function uc_ranking_relative_points(?float $value, ?float $bestValue, ?float $worstValue): float {
  if ($value === null || $bestValue === null || $worstValue === null) {
    return 0.0;
  }
  if ($bestValue == $worstValue) {
    return 2.0;
  }
  return 1 + (($value - $worstValue) / ($bestValue - $worstValue));
}

function uc_ranking_absolute_points($value, $min, $max, string $direction): ?float {
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

function uc_ranking_absolute_bonus_applies(?float $numericValue, ?float $bestExpected, string $direction): bool {
  if ($numericValue === null || $bestExpected === null) {
    return false;
  }
  if ($direction === "less") {
    return $numericValue <= $bestExpected;
  }
  return $numericValue >= $bestExpected;
}

function uc_ranking_dense_ranks(array $rankValues, string $direction = "more"): array {
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

function uc_ranking_sort_entries(array &$entries): void {
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

function uc_ranking_category_weight_map(array $categoryWeights): array {
  $map = [];
  foreach ($categoryWeights as $row) {
    $category = trim((string)($row["category"] ?? ""));
    if ($category !== "") {
      $map[$category] = uc_ranking_weight($row["weight"] ?? 1);
    }
  }
  return $map;
}

function uc_ranking_disciplines_by_category(array $disciplines): array {
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
