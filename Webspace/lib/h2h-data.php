<?php
require_once __DIR__ . "/ranking-service.php";
require_once __DIR__ . "/radar-service.php";

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
$playerALabel = $h2hPlayerA["first_name"] . " " . $h2hPlayerA["last_name"];
$playerBLabel = $h2hPlayerB["first_name"] . " " . $h2hPlayerB["last_name"];
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
