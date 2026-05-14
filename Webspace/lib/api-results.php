<?php
require_once __DIR__ . "/combine-results-service.php";
require_once __DIR__ . "/ranking-service.php";

function uc_api_results_context(PDO $pdo, int $teamId, int $combineId): array {
  $context = uc_results_context($pdo, $teamId, $combineId);
  if (!$context) {
    uc_api_error("not_found", "Combine not found.", 404);
  }
  return $context;
}

function uc_api_results_meta(array $context, int $teamId): array {
  return uc_results_meta($context, $teamId);
}

function uc_api_relative_rankings(array $context): array {
  return uc_ranking_relative($context);
}

function uc_api_absolute_rankings(array $context): array {
  return uc_ranking_absolute($context);
}
