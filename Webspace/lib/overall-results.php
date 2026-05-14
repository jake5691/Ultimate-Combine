<?php
require_once __DIR__ . "/ranking-service.php";

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
$overallScoresSum = $overallView["overall_scores_sum"];
$overallScoresAvg = $overallView["overall_scores_avg"];
$overallScoresAbs = $overallView["overall_scores_abs"];
$overallRanks = $overallView["overall_ranks"];
$categoryAverages = $overallView["category_averages"];
$categoryAveragesAbs = $overallView["category_averages_abs"];
$categoryAveragesAvg = $overallView["category_averages_avg"];
$categoryTeamAverages = $overallView["category_team_averages"];
$categoryTeamWeightedAverages = $overallView["category_team_weighted_averages"];
$categoryTeamAveragesAbs = $overallView["category_team_averages_abs"];
$categoryTeamWeightedAveragesAbs = $overallView["category_team_weighted_averages_abs"];
$categoryTeamAveragesAvg = $overallView["category_team_averages_avg"];
$categoryWeights = $overallView["category_weights"];

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
