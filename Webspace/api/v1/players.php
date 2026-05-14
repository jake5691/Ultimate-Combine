<?php
define("UC_API_REQUEST", true);
require_once __DIR__ . "/../../bootstrap.php";
require_once __DIR__ . "/../../lib/api-response.php";
require_once __DIR__ . "/../../lib/api-auth.php";

uc_api_require_method("GET");

if (!$pdo) {
  uc_api_error("service_unavailable", $dbError ?? "Database is unavailable.", 503);
}

$auth = uc_api_require_auth($pdo);
$teamId = $auth["team_id"];
$playerId = uc_api_int_param("id");

$sql = "SELECT id, first_name, last_name, jersey_number, gender, position_handler, position_cutter, created_at
        FROM players
        WHERE team_id = :team_id";
$params = [":team_id" => $teamId];

if ($playerId !== null) {
  $sql .= " AND id = :id";
  $params[":id"] = $playerId;
}

$sql .= " ORDER BY first_name ASC, last_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$players = array_map("uc_api_normalize_row", $stmt->fetchAll());

if ($playerId !== null && empty($players)) {
  uc_api_error("not_found", "Player not found.", 404);
}

uc_api_send_json([
  "data" => $playerId !== null ? $players[0] : $players,
  "meta" => [
    "team_id" => $teamId,
    "count" => count($players),
  ],
]);
