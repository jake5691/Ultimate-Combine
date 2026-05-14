<?php
define("UC_API_REQUEST", true);
require_once __DIR__ . "/../../bootstrap.php";
require_once __DIR__ . "/../../lib/api-response.php";
require_once __DIR__ . "/../../lib/api-auth.php";
require_once __DIR__ . "/../../lib/api-results.php";
require_once __DIR__ . "/../../lib/radar-service.php";

uc_api_require_method("GET");

if (!$pdo) {
  uc_api_error("service_unavailable", $dbError ?? "Database is unavailable.", 503);
}

$auth = uc_api_require_auth($pdo);
$teamId = $auth["team_id"];
$combineId = uc_api_int_param("combine_id", true);
$playerId = uc_api_int_param("player_id");
$comparePlayerId = uc_api_int_param("compare_player_id");
$overallMode = $_GET["overall"] ?? "sum";
if (!in_array($overallMode, ["sum", "avg", "abs"], true)) {
  uc_api_error("invalid_request", "Invalid overall mode.", 400);
}

$context = uc_api_results_context($pdo, $teamId, $combineId);

if ($comparePlayerId !== null) {
  if ($playerId === null) {
    uc_api_error("invalid_request", "compare_player_id requires player_id.", 400);
  }
  if ($playerId === $comparePlayerId) {
    uc_api_error("invalid_request", "player_id and compare_player_id must be different.", 400);
  }

  $playerIds = array_map(function ($player) {
    return (int)$player["id"];
  }, $context["players"]);
  if (!in_array($playerId, $playerIds, true) || !in_array($comparePlayerId, $playerIds, true)) {
    uc_api_error("not_found", "Player not found in combine.", 404);
  }

  uc_api_send_json([
    "data" => [
      "combine" => $context["combine"],
      "mode" => $overallMode,
      "player_id" => $playerId,
      "compare_player_id" => $comparePlayerId,
      "radar" => uc_radar_for_h2h($context, $playerId, $comparePlayerId, $overallMode),
    ],
    "meta" => uc_api_results_meta($context, $teamId),
  ]);
}

$items = uc_radar_for_players($context, $playerId, $overallMode);

if ($playerId !== null && empty($items)) {
  uc_api_error("not_found", "Player not found in combine.", 404);
}

uc_api_send_json([
  "data" => [
    "combine" => $context["combine"],
    "mode" => $overallMode,
    "items" => $items,
  ],
  "meta" => uc_api_results_meta($context, $teamId),
]);
