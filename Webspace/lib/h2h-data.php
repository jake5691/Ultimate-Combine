<?php
            require_once __DIR__ . "/ranking-service.php";
            $h2hOverallView = uc_ranking_overall_view(
              $assignedPlayers,
              $assignedDisciplinesByCategory,
              $combineCategoryWeights,
              $combineDisciplineWeights,
              $resultsByDiscipline,
              $overallMode
            );
            $overallScores = $h2hOverallView["overall_scores"];
            $overallRanks = $h2hOverallView["overall_ranks"];
            $playerALabel = $h2hPlayerA["first_name"] . " " . $h2hPlayerA["last_name"];
            $playerBLabel = $h2hPlayerB["first_name"] . " " . $h2hPlayerB["last_name"];
            $overallPointsPrefix = $overallMode === "avg" ? t("common.avg_prefix", "Ø ") : "";
            $overallPointsA = $overallScores[$h2hPlayerAId] ?? 0;
            $overallPointsB = $overallScores[$h2hPlayerBId] ?? 0;
            $overallRankA = $overallRanks[$h2hPlayerAId] ?? "-";
            $overallRankB = $overallRanks[$h2hPlayerBId] ?? "-";
            $h2hRadarData = [];
            foreach ($assignedDisciplinesByCategory as $category => $categoryDisciplines) {
              $displayDisciplines = $categoryDisciplines;
              if ($overallMode === "abs") {
                $displayDisciplines = array_values(array_filter($categoryDisciplines, function ($discipline) {
                  $minValue = uc_value_to_float($discipline["expected_min"] ?? null);
                  $maxValue = uc_value_to_float($discipline["expected_max"] ?? null);
                  return $minValue !== null && $maxValue !== null;
                }));
              }
              if (empty($displayDisciplines)) {
                continue;
              }
              $categoryWeight = $combineCategoryWeights[$category] ?? 1.0;
              if ($categoryWeight <= 0) {
                $categoryWeight = 1.0;
              }
              $weightSum = 0.0;
              $weightSumAbs = 0.0;
              $sumA = 0.0;
              $sumB = 0.0;
              $sumAbsA = 0.0;
              $sumAbsB = 0.0;
              $sumAvgA = 0.0;
              $sumAvgB = 0.0;
              $sumAvgWeightA = 0.0;
              $sumAvgWeightB = 0.0;
              $categoryTotalsTeam = [];
              $categoryTotalsAbsTeam = [];
              $categoryTotalsAvgTeam = [];
              $categoryWeightSumsAvgTeam = [];
              foreach ($assignedPlayers as $player) {
                $playerId = (int)$player["id"];
                $categoryTotalsTeam[$playerId] = 0.0;
                $categoryTotalsAbsTeam[$playerId] = 0.0;
                $categoryTotalsAvgTeam[$playerId] = 0.0;
                $categoryWeightSumsAvgTeam[$playerId] = 0.0;
              }
              foreach ($displayDisciplines as $discipline) {
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
                foreach ($assignedPlayers as $player) {
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
                  $weightSum += $disciplineWeight;
                  if ($hasAbsolute) {
                    $weightSumAbs += $disciplineWeight;
                  }
                  $values = array_values($rankValues);
                  if ($direction === "less") {
                    $bestValue = min($values);
                    $worstValue = max($values);
                  } else {
                    $bestValue = max($values);
                    $worstValue = min($values);
                  }
                }
                $numericA = isset($rankValues[$h2hPlayerAId]) ? $rankValues[$h2hPlayerAId] : null;
                $numericB = isset($rankValues[$h2hPlayerBId]) ? $rankValues[$h2hPlayerBId] : null;
                $pointsBaseA = 0;
                $pointsBaseB = 0;
                if ($numericA === null || $bestValue === null || $worstValue === null) {
                  $pointsBaseA = 0;
                } elseif ($bestValue == $worstValue) {
                  $pointsBaseA = 2;
                } else {
                  $ratioA = ($numericA - $worstValue) / ($bestValue - $worstValue);
                  $pointsBaseA = 1 + $ratioA;
                }
                if ($numericB === null || $bestValue === null || $worstValue === null) {
                  $pointsBaseB = 0;
                } elseif ($bestValue == $worstValue) {
                  $pointsBaseB = 2;
                } else {
                  $ratioB = ($numericB - $worstValue) / ($bestValue - $worstValue);
                  $pointsBaseB = 1 + $ratioB;
                }
                $pointsSumA = $pointsBaseA;
                $pointsSumB = $pointsBaseB;
                if ($overallMode === "sum" && $bonusRel > 0 && $bestValue !== null) {
                  if ($numericA !== null && $numericA == $bestValue) {
                    $pointsSumA += $bonusRel;
                  }
                  if ($numericB !== null && $numericB == $bestValue) {
                    $pointsSumB += $bonusRel;
                  }
                }
                $sumA += $pointsSumA * $disciplineWeight;
                $sumB += $pointsSumB * $disciplineWeight;
                if ($hasAbsolute) {
                  $pointsAbsA = uc_absolute_points($numericA, $expectedMinValue, $expectedMaxValue, $direction);
                  $pointsAbsB = uc_absolute_points($numericB, $expectedMinValue, $expectedMaxValue, $direction);
                  if ($pointsAbsA === null) { $pointsAbsA = 0; }
                  if ($pointsAbsB === null) { $pointsAbsB = 0; }
                  if ($bonusAbs > 0 && uc_absolute_bonus_applies($numericA, $expectedMaxValue, $direction)) {
                    $pointsAbsA += $bonusAbs;
                  }
                  if ($bonusAbs > 0 && uc_absolute_bonus_applies($numericB, $expectedMaxValue, $direction)) {
                    $pointsAbsB += $bonusAbs;
                  }
                  $sumAbsA += $pointsAbsA * $disciplineWeight;
                  $sumAbsB += $pointsAbsB * $disciplineWeight;
                }
                if ($numericA !== null && $bestValue !== null && $worstValue !== null) {
                  $sumAvgA += $pointsBaseA * $disciplineWeight;
                  $sumAvgWeightA += $disciplineWeight;
                }
                if ($numericB !== null && $bestValue !== null && $worstValue !== null) {
                  $sumAvgB += $pointsBaseB * $disciplineWeight;
                  $sumAvgWeightB += $disciplineWeight;
                }
                foreach ($assignedPlayers as $player) {
                  $playerId = (int)$player["id"];
                  $numeric = $rankValues[$playerId] ?? null;
                  $pointsBase = 0;
                  if ($numeric === null || $bestValue === null || $worstValue === null) {
                    $pointsBase = 0;
                  } elseif ($bestValue == $worstValue) {
                    $pointsBase = 2;
                  } else {
                    $ratio = ($numeric - $worstValue) / ($bestValue - $worstValue);
                    $pointsBase = 1 + $ratio;
                  }
                  $pointsSum = $pointsBase;
                  if ($overallMode === "sum" && $bonusRel > 0 && $bestValue !== null && $numeric !== null && $numeric == $bestValue) {
                    $pointsSum += $bonusRel;
                  }
                  $categoryTotalsTeam[$playerId] += $pointsSum * $disciplineWeight;
                  if ($hasAbsolute) {
                    $pointsAbs = uc_absolute_points($numeric, $expectedMinValue, $expectedMaxValue, $direction);
                    if ($pointsAbs === null) {
                      $pointsAbs = 0;
                    }
                    if ($bonusAbs > 0 && uc_absolute_bonus_applies($numeric, $expectedMaxValue, $direction)) {
                      $pointsAbs += $bonusAbs;
                    }
                    $categoryTotalsAbsTeam[$playerId] += $pointsAbs * $disciplineWeight;
                  }
                  if ($numeric !== null && $bestValue !== null && $worstValue !== null) {
                    $categoryTotalsAvgTeam[$playerId] += $pointsBase * $disciplineWeight;
                    $categoryWeightSumsAvgTeam[$playerId] += $disciplineWeight;
                  }
                }
              }
              $radarA = 0.0;
              $radarB = 0.0;
              $radarTeam = 0.0;
              $hasRadar = false;
              if ($overallMode === "avg") {
                if ($sumAvgWeightA > 0 || $sumAvgWeightB > 0) {
                  $radarA = $sumAvgWeightA > 0 ? $sumAvgA / $sumAvgWeightA : 0;
                  $radarB = $sumAvgWeightB > 0 ? $sumAvgB / $sumAvgWeightB : 0;
                  $hasRadar = true;
                }
                $teamSum = 0.0;
                $teamCount = 0;
                foreach ($assignedPlayers as $player) {
                  $playerId = (int)$player["id"];
                  $teamWeightSum = $categoryWeightSumsAvgTeam[$playerId] ?? 0.0;
                  if ($teamWeightSum > 0) {
                    $teamSum += $categoryTotalsAvgTeam[$playerId] / $teamWeightSum;
                    $teamCount++;
                  }
                }
                if ($teamCount > 0) {
                  $radarTeam = $teamSum / $teamCount;
                  $hasRadar = true;
                }
              } elseif ($overallMode === "abs") {
                if ($weightSumAbs > 0) {
                  $radarA = $sumAbsA / $weightSumAbs;
                  $radarB = $sumAbsB / $weightSumAbs;
                  $hasRadar = true;
                }
                if ($weightSumAbs > 0) {
                  $teamSum = 0.0;
                  $teamCount = 0;
                  foreach ($assignedPlayers as $player) {
                    $playerId = (int)$player["id"];
                    $teamSum += $categoryTotalsAbsTeam[$playerId] / $weightSumAbs;
                    $teamCount++;
                  }
                  if ($teamCount > 0) {
                    $radarTeam = $teamSum / $teamCount;
                    $hasRadar = true;
                  }
                }
              } else {
                if ($weightSum > 0) {
                  $radarA = $sumA / $weightSum;
                  $radarB = $sumB / $weightSum;
                  $hasRadar = true;
                }
                if ($weightSum > 0) {
                  $teamSum = 0.0;
                  $teamCount = 0;
                  foreach ($assignedPlayers as $player) {
                    $playerId = (int)$player["id"];
                    $teamSum += $categoryTotalsTeam[$playerId] / $weightSum;
                    $teamCount++;
                  }
                  if ($teamCount > 0) {
                    $radarTeam = $teamSum / $teamCount;
                    $hasRadar = true;
                  }
                }
              }
              if ($hasRadar) {
                if ($overallMode !== "avg") {
                  $radarA *= $categoryWeight;
                  $radarB *= $categoryWeight;
                  $radarTeam *= $categoryWeight;
                }
                $h2hRadarData[] = [
                  "label" => $category,
                  "player" => $radarA,
                  "playerB" => $radarB,
                  "team" => $radarTeam,
                ];
              }
            }
