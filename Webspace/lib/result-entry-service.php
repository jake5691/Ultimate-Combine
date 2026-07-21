<?php

function uc_result_entry_normalize_value($value): ?string {
  $value = trim((string)$value);
  if ($value === "") {
    return null;
  }
  return str_replace(",", ".", $value);
}

function uc_result_entry_player_ids(array $players): array {
  $playerIds = [];
  foreach ($players as $player) {
    $playerId = (int)($player["id"] ?? 0);
    if ($playerId > 0) {
      $playerIds[$playerId] = $playerId;
    }
  }
  return array_values($playerIds);
}

function uc_result_entry_prepare_values(array $playerIds, array $submitted): array {
  $values = [];
  foreach ($playerIds as $playerId) {
    $playerId = (int)$playerId;
    $values[$playerId] = uc_result_entry_normalize_value($submitted[$playerId] ?? null);
  }
  return $values;
}

function uc_result_entry_load_current_values(
  PDO $pdo,
  int $combineId,
  int $disciplineId,
  array $playerIds
): array {
  if (empty($playerIds)) {
    return [];
  }

  $placeholders = [];
  $params = [
    ":combine_id" => $combineId,
    ":discipline_id" => $disciplineId,
  ];
  foreach (array_values($playerIds) as $index => $playerId) {
    $placeholder = ":player_id_" . $index;
    $placeholders[] = $placeholder;
    $params[$placeholder] = (int)$playerId;
  }

  $stmt = $pdo->prepare(
    "SELECT player_id, result_value
     FROM combine_results
     WHERE combine_id = :combine_id
       AND discipline_id = :discipline_id
       AND player_id IN (" . implode(", ", $placeholders) . ")"
  );
  $stmt->execute($params);

  $currentValues = [];
  foreach ($stmt->fetchAll() as $row) {
    $currentValues[(int)$row["player_id"]] = uc_result_entry_normalize_value($row["result_value"]);
  }
  return $currentValues;
}

function uc_result_entry_find_conflicts(
  array $playerIds,
  array $newValues,
  array $originalValues,
  array $currentValues
): array {
  $conflicts = [];
  foreach ($playerIds as $playerId) {
    $playerId = (int)$playerId;
    $current = $currentValues[$playerId] ?? null;
    $original = $originalValues[$playerId] ?? null;
    if ($current !== $original) {
      $conflicts[$playerId] = [
        "current" => $current,
        "new" => $newValues[$playerId] ?? null,
        "original" => $original,
      ];
    }
  }
  return $conflicts;
}

function uc_result_entry_completed_disciplines(
  PDO $pdo,
  int $combineId,
  array $disciplineIds,
  array $playerIds
): array {
  $disciplineIds = array_values(array_unique(array_filter(array_map("intval", $disciplineIds))));
  $playerIds = array_values(array_unique(array_filter(array_map("intval", $playerIds))));
  if (empty($disciplineIds) || empty($playerIds)) {
    return [];
  }

  $stmt = $pdo->prepare(
    "SELECT discipline_id, player_id, result_value
     FROM combine_results
     WHERE combine_id = :combine_id
       AND result_value IS NOT NULL"
  );
  $stmt->execute([":combine_id" => $combineId]);

  $disciplineMap = array_fill_keys($disciplineIds, true);
  $playerMap = array_fill_keys($playerIds, true);
  $resultsByDiscipline = [];
  foreach ($stmt->fetchAll() as $row) {
    $disciplineId = (int)$row["discipline_id"];
    $playerId = (int)$row["player_id"];
    if (
      isset($disciplineMap[$disciplineId])
      && isset($playerMap[$playerId])
      && uc_result_entry_normalize_value($row["result_value"]) !== null
    ) {
      $resultsByDiscipline[$disciplineId][$playerId] = true;
    }
  }

  $completed = [];
  foreach ($disciplineIds as $disciplineId) {
    if (count($resultsByDiscipline[$disciplineId] ?? []) === count($playerIds)) {
      $completed[$disciplineId] = true;
    }
  }
  return $completed;
}

function uc_result_entry_store_values(
  PDO $pdo,
  int $combineId,
  int $disciplineId,
  array $playerIds,
  array $values
): void {
  $pdo->beginTransaction();
  try {
    $deleteStmt = $pdo->prepare(
      "DELETE FROM combine_results
       WHERE combine_id = :combine_id AND discipline_id = :discipline_id AND player_id = :player_id"
    );
    $upsertStmt = $pdo->prepare(
      "INSERT INTO combine_results (combine_id, discipline_id, player_id, result_value)
       VALUES (:combine_id, :discipline_id, :player_id, :result_value)
       ON DUPLICATE KEY UPDATE result_value = VALUES(result_value), updated_at = CURRENT_TIMESTAMP"
    );

    foreach ($playerIds as $playerId) {
      $playerId = (int)$playerId;
      $value = $values[$playerId] ?? null;
      if ($value === null) {
        $deleteStmt->execute([
          ":combine_id" => $combineId,
          ":discipline_id" => $disciplineId,
          ":player_id" => $playerId,
        ]);
      } else {
        $upsertStmt->execute([
          ":combine_id" => $combineId,
          ":discipline_id" => $disciplineId,
          ":player_id" => $playerId,
          ":result_value" => $value,
        ]);
      }
    }

    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    throw $e;
  }
}
