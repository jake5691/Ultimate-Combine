<?php
define("UC_API_REQUEST", true);
require_once __DIR__ . "/../../bootstrap.php";
require_once __DIR__ . "/../../lib/api-response.php";
require_once __DIR__ . "/../../lib/api-auth.php";
require_once __DIR__ . "/../../lib/api-results.php";

uc_api_require_method("GET");

if (!$pdo) {
  uc_api_error("service_unavailable", $dbError ?? "Database is unavailable.", 503);
}

$auth = uc_api_require_auth($pdo);
$teamId = $auth["team_id"];
$combineId = uc_api_int_param("combine_id", true);
$context = uc_api_results_context($pdo, $teamId, $combineId);

uc_api_send_json([
  "data" => [
    "combine" => $context["combine"],
    "ranking" => uc_api_absolute_rankings($context),
  ],
  "meta" => uc_api_results_meta($context, $teamId),
]);
