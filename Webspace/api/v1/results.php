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
$combineId = uc_api_int_param("combine_id", true);

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

uc_api_send_json([
  "data" => [
    "combine" => uc_api_normalize_row($combine),
    "players" => $players,
    "disciplines" => $disciplines,
    "category_weights" => $categoryWeights,
    "results" => $results,
  ],
  "meta" => [
    "team_id" => $teamId,
    "player_count" => count($players),
    "discipline_count" => count($disciplines),
    "result_count" => count($results),
  ],
]);
