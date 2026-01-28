<?php
          $selectedPlayerId = filter_var($_GET["player_id"] ?? null, FILTER_VALIDATE_INT);
          $selectedPlayer = null;
          if ($selectedPlayerId) {
            foreach ($filteredPlayers as $player) {
              if ((int)$player["id"] === (int)$selectedPlayerId) {
                $selectedPlayer = $player;
                break;
              }
            }
            if (!$selectedPlayer) {
              $selectedPlayerId = null;
            }
          }
          $overallScoresSum = [];
          $overallScoresAvg = [];
          $overallScoresAbs = [];
          $overallCategoryCounts = [];
          $categoryAverages = [];
          $categoryAveragesAbs = [];
          $categoryAveragesAvg = [];
          $categoryTeamAverages = [];
          $categoryTeamWeightedAverages = [];
          $categoryTeamAveragesAbs = [];
          $categoryTeamWeightedAveragesAbs = [];
          $categoryTeamAveragesAvg = [];
          $categoryWeights = [];
          foreach ($filteredPlayers as $player) {
            $playerId = (int)$player["id"];
            $overallScoresSum[$playerId] = 0;
            $overallScoresAvg[$playerId] = 0;
            $overallScoresAbs[$playerId] = 0;
            $overallCategoryCounts[$playerId] = 0;
          }
          foreach ($assignedDisciplinesByCategory as $category => $categoryDisciplines) {
            $categoryWeight = $combineCategoryWeights[$category] ?? 1.0;
            if ($categoryWeight <= 0) {
              $categoryWeight = 1.0;
            }
            $categoryWeights[$category] = $categoryWeight;
            $disciplineCount = 0;
            $categoryTotals = [];
            $categoryTotalsAbs = [];
            $categoryTotalsAvg = [];
            $categoryWeightSumAll = 0.0;
            $categoryWeightSumAllAbs = 0.0;
            $categoryWeightSumsAvg = [];
            foreach ($filteredPlayers as $player) {
              $playerId = (int)$player["id"];
              $categoryTotals[$playerId] = 0;
              $categoryTotalsAbs[$playerId] = 0;
              $categoryTotalsAvg[$playerId] = 0;
              $categoryWeightSumsAvg[$playerId] = 0.0;
            }
            foreach ($categoryDisciplines as $discipline) {
              $discId = (int)$discipline["id"];
              $disciplineWeight = $combineDisciplineWeights[$discId] ?? 1.0;
              if ($disciplineWeight <= 0) {
                $disciplineWeight = 1.0;
              }
              $direction = $discipline["rating_direction"] ?? "more";
              if ($direction !== "less" && $direction !== "more") {
                $direction = "more";
              }
              $expectedMinValue = uc_value_to_float($discipline["expected_min"] ?? null);
              $expectedMaxValue = uc_value_to_float($discipline["expected_max"] ?? null);
              $bonusRel = uc_bonus_value($discipline["bonus_relative"] ?? null);
              $bonusAbs = uc_bonus_value($discipline["bonus_absolute"] ?? null);
              $hasAbsolute = $expectedMinValue !== null && $expectedMaxValue !== null;
              $rankValues = [];
              foreach ($filteredPlayers as $player) {
                $playerId = (int)$player["id"];
                $value = $resultsByDiscipline[$discId][$playerId] ?? null;
                $numeric = uc_value_to_float($value);
                if ($numeric === null) {
                  continue;
                }
                $rankValues[$playerId] = $numeric;
              }
              $bestValue = null;
              $worstValue = null;
              if (!empty($rankValues)) {
                $disciplineCount++;
                $categoryWeightSumAll += $disciplineWeight;
                $values = array_values($rankValues);
                if ($direction === "less") {
                  $bestValue = min($values);
                  $worstValue = max($values);
                } else {
                  $bestValue = max($values);
                  $worstValue = min($values);
                }
              }
              if ($hasAbsolute) {
                $categoryWeightSumAllAbs += $disciplineWeight;
              }
              foreach ($filteredPlayers as $player) {
                $playerId = (int)$player["id"];
                $numericValue = $rankValues[$playerId] ?? null;
                $pointsBase = 0;
                if ($numericValue === null || $bestValue === null || $worstValue === null) {
                  $pointsBase = 0;
                } elseif ($bestValue == $worstValue) {
                  $pointsBase = 2;
                } else {
                  $ratio = ($numericValue - $worstValue) / ($bestValue - $worstValue);
                  $pointsBase = 1 + $ratio;
                }
                $pointsSum = $pointsBase;
                if ($overallMode === "sum" && $bonusRel > 0 && $numericValue !== null && $bestValue !== null && $numericValue == $bestValue) {
                  $pointsSum += $bonusRel;
                }
                $categoryTotals[$playerId] += $pointsSum * $disciplineWeight;
                if ($hasAbsolute) {
                  $absolutePoints = uc_absolute_points($numericValue, $expectedMinValue, $expectedMaxValue, $direction);
                  if ($absolutePoints === null) {
                    $absolutePoints = 0;
                  }
                  if ($bonusAbs > 0 && uc_absolute_bonus_applies($numericValue, $expectedMaxValue, $direction)) {
                    $absolutePoints += $bonusAbs;
                  }
                  $categoryTotalsAbs[$playerId] += $absolutePoints * $disciplineWeight;
                }

                if ($numericValue !== null && $bestValue !== null && $worstValue !== null) {
                  $categoryTotalsAvg[$playerId] += $pointsBase * $disciplineWeight;
                  $categoryWeightSumsAvg[$playerId] += $disciplineWeight;
                }
              }
            }
            if ($disciplineCount === 0 || $categoryWeightSumAll <= 0) {
              continue;
            }
            $teamSum = 0;
            $teamCount = 0;
            $teamSumAbs = 0;
            $teamCountAbs = 0;
            $teamSumAvg = 0;
            $teamCountAvg = 0;
            $hasAbsoluteCategory = $categoryWeightSumAllAbs > 0;
            foreach ($filteredPlayers as $player) {
              $playerId = (int)$player["id"];
              $categoryAverage = $categoryTotals[$playerId] / $categoryWeightSumAll;
              $overallScoresSum[$playerId] += $categoryAverage * $categoryWeight;
              if ($hasAbsoluteCategory) {
                $categoryAverageAbs = $categoryTotalsAbs[$playerId] / $categoryWeightSumAllAbs;
                $overallScoresAbs[$playerId] += $categoryAverageAbs * $categoryWeight;
                $categoryAveragesAbs[$category][$playerId] = $categoryAverageAbs;
                $teamSumAbs += $categoryAverageAbs;
                $teamCountAbs++;
              }
              $avgWeightSum = $categoryWeightSumsAvg[$playerId] ?? 0.0;
              if ($avgWeightSum > 0) {
                $categoryAverageAvg = $categoryTotalsAvg[$playerId] / $avgWeightSum;
                $overallScoresAvg[$playerId] += $categoryAverageAvg;
                $overallCategoryCounts[$playerId] += 1;
                $categoryAveragesAvg[$category][$playerId] = $categoryAverageAvg;
                $teamSumAvg += $categoryAverageAvg;
                $teamCountAvg++;
              }
              $categoryAverages[$category][$playerId] = $categoryAverage;
              $teamSum += $categoryAverage;
              $teamCount++;
            }
            if ($teamCount > 0) {
              $categoryTeamAverages[$category] = $teamSum / $teamCount;
              $categoryTeamWeightedAverages[$category] = ($teamSum / $teamCount) * $categoryWeight;
            }
            if ($teamCountAbs > 0) {
              $categoryTeamAveragesAbs[$category] = $teamSumAbs / $teamCountAbs;
              $categoryTeamWeightedAveragesAbs[$category] = ($teamSumAbs / $teamCountAbs) * $categoryWeight;
            }
            if ($teamCountAvg > 0) {
              $categoryTeamAveragesAvg[$category] = $teamSumAvg / $teamCountAvg;
            }
          }
          foreach ($overallScoresAvg as $playerId => $score) {
            $count = $overallCategoryCounts[$playerId] ?? 0;
            if ($count > 0) {
              $overallScoresAvg[$playerId] = $score / $count;
            }
          }
          if ($overallMode === "avg") {
            $overallScores = $overallScoresAvg;
          } elseif ($overallMode === "abs") {
            $overallScores = $overallScoresAbs;
          } else {
            $overallScores = $overallScoresSum;
          }
          $overallRankValues = $overallScores;
          arsort($overallRankValues, SORT_NUMERIC);
          $overallRanks = [];
          $pos = 0;
          $rank = 0;
          $prev = null;
          foreach ($overallRankValues as $playerId => $val) {
            $pos++;
            if ($prev === null || $val != $prev) {
              $rank = $pos;
              $prev = $val;
            }
            $overallRanks[$playerId] = $rank;
          }
          $overallBaseParams = [
            "id" => (int)$combineId,
            "mode" => "results",
          ];
          if ($filterGender !== "") {
            $overallBaseParams["gender"] = $filterGender;
          }
          if ($filterPosition !== "") {
            $overallBaseParams["position"] = $filterPosition;
          }
          $overallBaseUrl = "combine.php?" . http_build_query($overallBaseParams);
          $overallSumUrl = $overallBaseUrl . "&overall=sum";
          $overallAvgUrl = $overallBaseUrl . "&overall=avg";
          $overallAbsUrl = $overallBaseUrl . "&overall=abs";
