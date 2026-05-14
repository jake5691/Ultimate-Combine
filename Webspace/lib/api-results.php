<?php
require_once __DIR__ . "/ranking-service.php";

function uc_api_results_context(PDO $pdo, int $teamId, int $combineId): array {
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

  $players = uc_api_results_players($pdo, $teamId, $combineId);
  $disciplines = uc_api_results_disciplines($pdo, $teamId, $combineId);
  $categoryWeights = uc_api_results_category_weights($pdo, $combineId);
  $results = uc_api_results_values($pdo, $teamId, $combineId);

  $resultsByDiscipline = [];
  foreach ($results as $result) {
    $discId = (int)$result["discipline_id"];
    $playerId = (int)$result["player_id"];
    $resultsByDiscipline[$discId][$playerId] = $result["result_value"];
  }

  return [
    "combine" => uc_api_normalize_row($combine),
    "players" => $players,
    "disciplines" => $disciplines,
    "category_weights" => $categoryWeights,
    "results" => $results,
    "results_by_discipline" => $resultsByDiscipline,
  ];
}

function uc_api_results_players(PDO $pdo, int $teamId, int $combineId): array {
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
  return array_map("uc_api_normalize_row", $stmt->fetchAll());
}

function uc_api_results_disciplines(PDO $pdo, int $teamId, int $combineId): array {
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
  return $disciplines;
}

function uc_api_results_category_weights(PDO $pdo, int $combineId): array {
  $stmt = $pdo->prepare(
    "SELECT category, weight
     FROM combine_category_weights
     WHERE combine_id = :combine_id
     ORDER BY category ASC"
  );
  $stmt->execute([":combine_id" => $combineId]);
  return array_map("uc_api_normalize_row", $stmt->fetchAll());
}

function uc_api_results_values(PDO $pdo, int $teamId, int $combineId): array {
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
  return array_map("uc_api_normalize_row", $stmt->fetchAll());
}

function uc_api_results_meta(array $context, int $teamId): array {
  return [
    "team_id" => $teamId,
    "player_count" => count($context["players"]),
    "discipline_count" => count($context["disciplines"]),
    "result_count" => count($context["results"]),
  ];
}

function uc_api_relative_rankings(array $context): array {
  return uc_ranking_relative($context);
}

function uc_api_absolute_rankings(array $context): array {
  return uc_ranking_absolute($context);
}
