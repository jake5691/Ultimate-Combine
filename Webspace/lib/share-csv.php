<?php
  if ($shareFormat === "csv") {
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"" . $shareFileBase . ".csv\"");
    echo implode(",", array_map("uc_csv_escape", $headers)) . "\r\n";
    foreach ($filteredPlayers as $player) {
      $playerId = (int)$player["id"];
      $positions = [];
      if (!empty($player["position_handler"])) {
        $positions[] = t("team.players.position_handler", "Handler");
      }
      if (!empty($player["position_cutter"])) {
        $positions[] = t("team.players.position_cutter", "Cutter");
      }
      $positionsLabel = empty($positions) ? "-" : implode(" / ", $positions);
      $row = [
        trim(($player["first_name"] ?? "") . " " . ($player["last_name"] ?? "")),
        $player["jersey_number"] !== null ? (string)$player["jersey_number"] : "-",
        $player["gender"] ?? "-",
        $positionsLabel,
      ];
      foreach ($disciplinesForExport as $discipline) {
        $discId = (int)$discipline["id"];
        $value = $resultsByDiscipline[$discId][$playerId] ?? null;
        $row[] = uc_display_value($value, "-");
      }
      echo implode(",", array_map("uc_csv_escape", $row)) . "\r\n";
    }
    exit;
  }
