<?php
define("UC_API_REQUEST", true);
require_once __DIR__ . "/../../bootstrap.php";
require_once __DIR__ . "/../../lib/api-response.php";

uc_api_require_method("GET");

uc_api_send_json([
  "data" => [
    "name" => "Ultimate Combine API",
    "version" => "v1",
    "status" => $pdo ? "ok" : "degraded",
  ],
]);
