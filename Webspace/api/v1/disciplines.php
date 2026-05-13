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
$disciplineId = uc_api_int_param("id");

$sql = "SELECT id,
               team_id,
               discipline_name,
               description,
               unit,
               category,
               rating_direction,
               expected_min,
               expected_max,
               bonus_relative,
               bonus_absolute,
               created_at
        FROM disciplines
        WHERE (team_id = :team_id OR team_id IS NULL)";
$params = [":team_id" => $teamId];

if ($disciplineId !== null) {
  $sql .= " AND id = :id";
  $params[":id"] = $disciplineId;
}

$sql .= " ORDER BY category ASC, discipline_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$disciplines = [];
foreach ($stmt->fetchAll() as $row) {
  $row = uc_api_normalize_row($row);
  $row["scope"] = $row["team_id"] === null ? "global" : "team";
  $disciplines[] = $row;
}

if ($disciplineId !== null && empty($disciplines)) {
  uc_api_error("not_found", "Discipline not found.", 404);
}

uc_api_send_json([
  "data" => $disciplineId !== null ? $disciplines[0] : $disciplines,
  "meta" => [
    "team_id" => $teamId,
    "count" => count($disciplines),
  ],
]);
