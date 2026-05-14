<?php
require_once __DIR__ . "/ranking-service.php";
require_once __DIR__ . "/radar-service.php";

/** @var array<int, array<string, mixed>> $assignedPlayers */
/** @var array<string, array<int, array<string, mixed>>> $assignedDisciplinesByCategory */
/** @var array<string, float|int|string> $combineCategoryWeights */
/** @var array<int, float|int|string> $combineDisciplineWeights */
/** @var array<int, array<int, mixed>> $resultsByDiscipline */
/** @var string $overallMode */
/** @var array<string, mixed> $h2hPlayerA */
/** @var array<string, mixed> $h2hPlayerB */
/** @var int $h2hPlayerAId */
/** @var int $h2hPlayerBId */

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
