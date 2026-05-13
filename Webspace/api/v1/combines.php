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
$combineId = uc_api_int_param("id");

$sql = "SELECT id, combine_name, event_date, combine_location, combine_notes, created_at
        FROM combines
        WHERE team_id = :team_id";
$params = [":team_id" => $teamId];

if ($combineId !== null) {
  $sql .= " AND id = :id";
  $params[":id"] = $combineId;
}

$sql .= " ORDER BY event_date DESC, created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$combines = array_map("uc_api_normalize_row", $stmt->fetchAll());

if ($combineId !== null && empty($combines)) {
  uc_api_error("not_found", "Combine not found.", 404);
}

if ($combineId !== null) {
  $combine = $combines[0];

  $stmt = $pdo->prepare(
    "SELECT player_id
     FROM combine_players
     WHERE combine_id = :combine_id
     ORDER BY player_id ASC"
  );
  $stmt->execute([":combine_id" => $combineId]);
  $combine["player_ids"] = array_map("intval", $stmt->fetchAll(PDO::FETCH_COLUMN));

  $stmt = $pdo->prepare(
    "SELECT discipline_id, weight
     FROM combine_disciplines
     WHERE combine_id = :combine_id
     ORDER BY discipline_id ASC"
  );
  $stmt->execute([":combine_id" => $combineId]);
  $combine["disciplines"] = array_map("uc_api_normalize_row", $stmt->fetchAll());

  $stmt = $pdo->prepare(
    "SELECT category, weight
     FROM combine_category_weights
     WHERE combine_id = :combine_id
     ORDER BY category ASC"
  );
  $stmt->execute([":combine_id" => $combineId]);
  $combine["category_weights"] = array_map("uc_api_normalize_row", $stmt->fetchAll());

  uc_api_send_json([
    "data" => $combine,
    "meta" => [
      "team_id" => $teamId,
      "count" => 1,
    ],
  ]);
}

uc_api_send_json([
  "data" => $combines,
  "meta" => [
    "team_id" => $teamId,
    "count" => count($combines),
  ],
]);
