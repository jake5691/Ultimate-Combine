<?php
require_once __DIR__ . "/ranking-service.php";

function uc_radar_for_player(array $overallView, array $categoryWeightsMap, int $playerId, string $mode = "sum"): array {
  $radarPlayerAverages = $overallView["category_averages"];
  $radarTeamAverages = $overallView["category_team_weighted_averages"];
  $applyWeight = true;

  if ($mode === "abs") {
    $radarPlayerAverages = $overallView["category_averages_abs"];
    $radarTeamAverages = $overallView["category_team_weighted_averages_abs"];
  } elseif ($mode === "avg") {
    $radarPlayerAverages = $overallView["category_averages_avg"];
    $radarTeamAverages = $overallView["category_team_averages_avg"];
    $applyWeight = false;
  }

  $radarData = [];
  foreach ($radarPlayerAverages as $category => $playerAverages) {
    $categoryWeight = uc_ranking_weight($categoryWeightsMap[$category] ?? 1);
    $playerAverage = $playerAverages[$playerId] ?? 0;
    if ($applyWeight) {
      $playerAverage *= $categoryWeight;
    }
    $radarData[] = [
      "label" => $category,
      "player" => $playerAverage,
      "team" => $radarTeamAverages[$category] ?? 0,
    ];
  }
  return $radarData;
}

function uc_radar_for_players(array $context, ?int $playerId = null, string $mode = "sum"): array {
  $overallView = uc_radar_overall_view($context, $mode);
  $categoryWeights = uc_radar_category_weight_map($context["category_weights"]);
  $players = $context["players"];
  if ($playerId !== null) {
    $players = array_values(array_filter($players, function ($player) use ($playerId) {
      return (int)$player["id"] === $playerId;
    }));
  }

  $items = [];
  foreach ($players as $player) {
    $id = (int)$player["id"];
    $items[] = [
      "player_id" => $id,
      "player_name" => trim(($player["first_name"] ?? "") . " " . ($player["last_name"] ?? "")),
      "mode" => $mode,
      "radar" => uc_radar_for_player($overallView, $categoryWeights, $id, $mode),
    ];
  }
  return $items;
}

function uc_radar_for_h2h(array $context, int $playerAId, int $playerBId, string $mode = "sum"): array {
  $overallView = uc_radar_overall_view($context, $mode);
  $categoryWeights = uc_radar_category_weight_map($context["category_weights"]);
  return uc_radar_for_h2h_from_view($overallView, $categoryWeights, $playerAId, $playerBId, $mode);
}

function uc_radar_for_h2h_from_view(array $overallView, array $categoryWeightsMap, int $playerAId, int $playerBId, string $mode = "sum"): array {
  $playerAData = uc_radar_for_player($overallView, $categoryWeightsMap, $playerAId, $mode);
  $playerBData = uc_radar_for_player($overallView, $categoryWeightsMap, $playerBId, $mode);
  $playerBByLabel = [];
  foreach ($playerBData as $item) {
    $playerBByLabel[$item["label"]] = $item;
  }

  $radarData = [];
  foreach ($playerAData as $itemA) {
    $label = $itemA["label"];
    if (!isset($playerBByLabel[$label])) {
      continue;
    }
    $itemB = $playerBByLabel[$label];
    $radarData[] = [
      "label" => $label,
      "player" => $itemA["player"],
      "playerB" => $itemB["player"],
      "team" => $itemA["team"],
    ];
  }
  return $radarData;
}

function uc_radar_overall_view(array $context, string $mode = "sum"): array {
  return uc_ranking_overall_view(
    $context["players"],
    uc_ranking_disciplines_by_category($context["disciplines"]),
    uc_radar_category_weight_map($context["category_weights"]),
    uc_radar_discipline_weight_map($context["disciplines"]),
    $context["results_by_discipline"],
    $mode
  );
}

function uc_radar_category_weight_map(array $categoryWeights): array {
  $map = [];
  foreach ($categoryWeights as $row) {
    $category = trim((string)($row["category"] ?? ""));
    if ($category !== "") {
      $map[$category] = uc_ranking_weight($row["weight"] ?? 1);
    }
  }
  return $map;
}

function uc_radar_discipline_weight_map(array $disciplines): array {
  $map = [];
  foreach ($disciplines as $discipline) {
    $map[(int)$discipline["id"]] = uc_ranking_weight($discipline["weight"] ?? 1);
  }
  return $map;
}
