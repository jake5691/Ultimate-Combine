      <section class="info">
        <h2><?php echo htmlspecialchars(t("combine.section.results", "Ergebnisse"), ENT_QUOTES, "UTF-8"); ?></h2>
        <?php
        $filteredPlayers = uc_filter_players($assignedPlayers, $filterGender, $filterPosition);
          require __DIR__ . "/../../lib/overall-results.php";
        ?>
        <div class="section-header">
          <div class="card-actions">
            <button class="pill-button is-muted" type="button" data-target="results-filters" aria-expanded="false"><?php echo htmlspecialchars(t("combine.filter.title", "Filter"), ENT_QUOTES, "UTF-8"); ?></button>
            <button class="pill-button is-share" type="button" data-target="share-combine" aria-expanded="false"><?php echo htmlspecialchars(t("common.share", "Teilen"), ENT_QUOTES, "UTF-8"); ?></button>
          </div>
        </div>
        <?php if ($filterGender !== "" || $filterPosition !== ""): ?>
          <?php
            $activeFilters = [];
            if ($filterGender !== "") {
              $activeFilters[] = t("combine.filter.gender", "Geschlecht") . ": " . ($genderOptions[$filterGender] ?? $filterGender);
            }
            if ($filterPosition !== "") {
              $activeFilters[] = t("combine.filter.position", "Position") . ": " . ($filterPosition === "handler" ? t("team.players.position_handler", "Handler") : t("team.players.position_cutter", "Cutter"));
            }
          ?>
          <p class="help"><?php echo htmlspecialchars(t("combine.filter.active", "Filter aktiv"), ENT_QUOTES, "UTF-8"); ?>: <?php echo htmlspecialchars(implode(" · ", $activeFilters), ENT_QUOTES, "UTF-8"); ?></p>
        <?php endif; ?>
        <?php
          $shareBaseParams = [
            "id" => (int)$combineId,
            "mode" => "results",
            "overall" => $overallMode,
          ];
          if ($filterGender !== "") {
            $shareBaseParams["gender"] = $filterGender;
          }
          if ($filterPosition !== "") {
            $shareBaseParams["position"] = $filterPosition;
          }
          $shareBaseUrl = "combine.php?" . http_build_query($shareBaseParams);
        ?>
        <div class="share-panel is-hidden" id="share-combine">
          <button class="pill-button is-muted" type="button" onclick="window.location.href='<?php echo htmlspecialchars($shareBaseUrl . "&share=csv", ENT_QUOTES, "UTF-8"); ?>'"><?php echo htmlspecialchars(t("combine.share.csv", "CSV herunterladen"), ENT_QUOTES, "UTF-8"); ?></button>
          <button class="pill-button is-muted" type="button" onclick="window.location.href='<?php echo htmlspecialchars($shareBaseUrl . "&share=img", ENT_QUOTES, "UTF-8"); ?>'"><?php echo htmlspecialchars(t("combine.share.image", "Bild herunterladen"), ENT_QUOTES, "UTF-8"); ?></button>
        </div>
        <div class="info-card is-hidden" id="results-filters">
          <h3><?php echo htmlspecialchars(t("combine.filter.title", "Filter"), ENT_QUOTES, "UTF-8"); ?></h3>
          <form class="form" method="get" action="combine.php">
            <input type="hidden" name="id" value="<?php echo (int)$combineId; ?>">
            <input type="hidden" name="mode" value="results">
            <input type="hidden" name="overall" value="<?php echo htmlspecialchars($overallMode, ENT_QUOTES, "UTF-8"); ?>">
            <label class="field">
              <span><?php echo htmlspecialchars(t("combine.filter.gender", "Geschlecht"), ENT_QUOTES, "UTF-8"); ?></span>
              <select name="gender">
                <option value=""><?php echo htmlspecialchars(t("combine.filter.all", "Alle"), ENT_QUOTES, "UTF-8"); ?></option>
                <?php foreach ($genderOptions as $key => $label): ?>
                  <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, "UTF-8"); ?>"<?php echo $filterGender === $key ? " selected" : ""; ?>>
                    <?php echo htmlspecialchars($label, ENT_QUOTES, "UTF-8"); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="field">
              <span><?php echo htmlspecialchars(t("combine.filter.position", "Position"), ENT_QUOTES, "UTF-8"); ?></span>
              <select name="position">
                <option value=""><?php echo htmlspecialchars(t("combine.filter.all", "Alle"), ENT_QUOTES, "UTF-8"); ?></option>
                <option value="handler"<?php echo $filterPosition === "handler" ? " selected" : ""; ?>><?php echo htmlspecialchars(t("team.players.position_handler", "Handler"), ENT_QUOTES, "UTF-8"); ?></option>
                <option value="cutter"<?php echo $filterPosition === "cutter" ? " selected" : ""; ?>><?php echo htmlspecialchars(t("team.players.position_cutter", "Cutter"), ENT_QUOTES, "UTF-8"); ?></option>
              </select>
            </label>
            <div class="form-actions">
              <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("combine.filter.apply", "Filter anwenden"), ENT_QUOTES, "UTF-8"); ?></button>
              <?php if ($filterGender !== "" || $filterPosition !== ""): ?>
                <button class="pill-button is-muted" type="button" onclick="window.location.href='<?php echo htmlspecialchars($overallBaseUrl . "&overall=" . urlencode($overallMode), ENT_QUOTES, "UTF-8"); ?>'"><?php echo htmlspecialchars(t("combine.filter.reset", "Zurücksetzen"), ENT_QUOTES, "UTF-8"); ?></button>
              <?php endif; ?>
            </div>
          </form>
        </div>
        <div class="info-card">
          <div class="card-header">
            <h3><?php echo htmlspecialchars(t("combine.overall.title", "Overall Ranking"), ENT_QUOTES, "UTF-8"); ?></h3>
            <div class="card-actions">
              <button class="pill-button<?php echo $overallMode === "sum" ? " is-active" : ""; ?>" type="button" onclick="window.location.href='<?php echo htmlspecialchars($overallSumUrl, ENT_QUOTES, "UTF-8"); ?>'"><?php echo htmlspecialchars(t("combine.mode.relative", "Relativ"), ENT_QUOTES, "UTF-8"); ?></button>
              <button class="pill-button<?php echo $overallMode === "avg" ? " is-active" : ""; ?>" type="button" onclick="window.location.href='<?php echo htmlspecialchars($overallAvgUrl, ENT_QUOTES, "UTF-8"); ?>'"><?php echo htmlspecialchars(t("combine.mode.relative_avg", "Ø Relativ"), ENT_QUOTES, "UTF-8"); ?></button>
              <button class="pill-button<?php echo $overallMode === "abs" ? " is-active" : ""; ?>" type="button" onclick="window.location.href='<?php echo htmlspecialchars($overallAbsUrl, ENT_QUOTES, "UTF-8"); ?>'"><?php echo htmlspecialchars(t("combine.mode.absolute", "Absolut"), ENT_QUOTES, "UTF-8"); ?></button>
            </div>
          </div>
          <?php if ($overallMode === "sum"): ?>
            <p class="help"><?php echo htmlspecialchars(t("combine.mode.help.relative", "Relativ: Punkte werden relativ zu den Teilnehmern berechnet. Nicht absolvierte Disziplinen zählen als 0 in den Kategorien."), ENT_QUOTES, "UTF-8"); ?></p>
          <?php elseif ($overallMode === "avg"): ?>
            <p class="help"><?php echo htmlspecialchars(t("combine.mode.help.relative_avg", "Ø Relativ: Es zählen nur Kategorien und Disziplinen, die dieser Spieler absolviert hat. Punkte werden relativ zu den Teilnehmern berechnet."), ENT_QUOTES, "UTF-8"); ?></p>
          <?php else: ?>
            <p class="help"><?php echo htmlspecialchars(t("combine.mode.help.absolute", "Absolut: Punkte anhand Erwartungs-Min/Max. Disziplinen ohne Erwartungswerte werden nicht berücksichtigt."), ENT_QUOTES, "UTF-8"); ?></p>
          <?php endif; ?>
          <?php if (empty($filteredPlayers)): ?>
            <p class="help"><?php echo htmlspecialchars(t("combine.players.empty_filtered", "Keine Spieler für den gewählten Filter."), ENT_QUOTES, "UTF-8"); ?></p>
          <?php else: ?>
            <?php
              $overallOrderedPlayers = $filteredPlayers;
              usort($overallOrderedPlayers, function ($a, $b) use ($overallScores) {
                $scoreA = $overallScores[(int)$a["id"]] ?? 0;
                $scoreB = $overallScores[(int)$b["id"]] ?? 0;
                if ($scoreA == $scoreB) {
                  $lastCompare = strcmp((string)$a["last_name"], (string)$b["last_name"]);
                  if ($lastCompare === 0) {
                    return strcmp((string)$a["first_name"], (string)$b["first_name"]);
                  }
                  return $lastCompare;
                }
                return $scoreA < $scoreB ? 1 : -1;
              });
            ?>
            <ul class="list overall-ranking-list">
              <?php foreach ($overallOrderedPlayers as $player): ?>
                <?php $playerId = (int)$player["id"]; ?>
                <?php $overallPoints = $overallScores[$playerId] ?? 0; ?>
                <?php $rankLabel = isset($overallRanks[$playerId]) ? (string)$overallRanks[$playerId] : "-"; ?>
                <?php $overallPointsPrefix = $overallMode === "avg" ? t("common.avg_prefix", "Ø ") : ""; ?>
                <?php
                  $nameParts = [(string)($player["first_name"] ?? ""), (string)($player["last_name"] ?? "")];
                  $hasLongNamePart = false;
                  foreach ($nameParts as $part) {
                    $partLength = function_exists("mb_strlen") ? mb_strlen($part) : strlen($part);
                    if ($partLength >= 16) {
                      $hasLongNamePart = true;
                      break;
                    }
                  }
                ?>
                <?php
                  $detailUrl = "combine.php?id=" . (int)$combineId . "&mode=results";
                  if ($filterGender !== "") {
                    $detailUrl .= "&gender=" . urlencode($filterGender);
                  }
                  if ($filterPosition !== "") {
                    $detailUrl .= "&position=" . urlencode($filterPosition);
                  }
                  $detailUrl .= "&overall=" . urlencode($overallMode);
                  $detailUrl .= "&player_id=" . $playerId;
                ?>
                <li class="list-item<?php echo ($selectedPlayerId && (int)$selectedPlayerId === $playerId) ? " is-active" : ""; ?>">
                  <a class="list-link" href="<?php echo htmlspecialchars($detailUrl, ENT_QUOTES, "UTF-8"); ?>">
                    <div class="result-name">
                      <span class="rank-pill"><?php echo htmlspecialchars(t("common.place", "Platz"), ENT_QUOTES, "UTF-8"); ?> <?php echo htmlspecialchars($rankLabel, ENT_QUOTES, "UTF-8"); ?></span>
                      <strong class="player-name<?php echo $hasLongNamePart ? " is-condensed" : ""; ?>">
                        <?php echo htmlspecialchars($player["first_name"], ENT_QUOTES, "UTF-8"); ?>
                        <?php echo " " . htmlspecialchars($player["last_name"], ENT_QUOTES, "UTF-8"); ?>
                      </strong>
                    </div>
                    <span class="badge"><?php echo htmlspecialchars($overallPointsPrefix . uc_format_points($overallPoints) . " " . t("common.points_abbr", "P"), ENT_QUOTES, "UTF-8"); ?></span>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
        <?php if ($selectedPlayerId && $selectedPlayer): ?>
          <?php
            $radarData = uc_radar_for_player($overallView, $combineCategoryWeights, (int)$selectedPlayerId, $overallMode);
            $resetUrl = "combine.php?id=" . (int)$combineId . "&mode=results";
            if ($filterGender !== "") {
              $resetUrl .= "&gender=" . urlencode($filterGender);
            }
            if ($filterPosition !== "") {
              $resetUrl .= "&position=" . urlencode($filterPosition);
            }
            $resetUrl .= "&overall=" . urlencode($overallMode);
            $playerShareUrl = $resetUrl . "&player_id=" . (int)$selectedPlayerId . "&share=img";
          ?>
          <div class="info-card player-detail">
            <div class="card-header">
              <h3>
                <?php echo htmlspecialchars(t("combine.player.results", "Ergebnisse"), ENT_QUOTES, "UTF-8"); ?>: <?php echo htmlspecialchars($selectedPlayer["first_name"], ENT_QUOTES, "UTF-8"); ?>
                <?php echo " " . htmlspecialchars($selectedPlayer["last_name"], ENT_QUOTES, "UTF-8"); ?>
              </h3>
              <div class="card-actions">
                <a class="pill-button is-share" href="<?php echo htmlspecialchars($playerShareUrl, ENT_QUOTES, "UTF-8"); ?>"><?php echo htmlspecialchars(t("common.share", "Teilen"), ENT_QUOTES, "UTF-8"); ?></a>
                <a class="pill-button is-muted" href="<?php echo htmlspecialchars($resetUrl, ENT_QUOTES, "UTF-8"); ?>"><?php echo htmlspecialchars(t("common.close", "Schließen"), ENT_QUOTES, "UTF-8"); ?></a>
              </div>
            </div>
            <?php if (empty($radarData)): ?>
              <p class="help"><?php echo htmlspecialchars(t("combine.categories.empty_display", "Keine Kategorien für die Anzeige."), ENT_QUOTES, "UTF-8"); ?></p>
            <?php else: ?>
              <div class="radar-grid">
                <div class="radar-chart">
                  <canvas id="radar-chart" width="360" height="360"></canvas>
                  <div class="radar-legend is-overlay">
                    <span class="legend-item legend-player"><?php echo htmlspecialchars(t("common.player", "Spieler"), ENT_QUOTES, "UTF-8"); ?></span>
                    <span class="legend-item legend-team"><?php echo htmlspecialchars(t("common.team", "Team"), ENT_QUOTES, "UTF-8"); ?></span>
                  </div>
                </div>
                <div class="radar-details">
                  <?php
                    $showCategoryWeights = false;
                    foreach ($assignedDisciplinesByCategory as $categoryKey => $categoryDisciplines) {
                      $weight = $combineCategoryWeights[$categoryKey] ?? 1;
                      if ((float)$weight !== 1.0) {
                        $showCategoryWeights = true;
                        break;
                      }
                    }
                  ?>
                  <?php foreach ($assignedDisciplinesByCategory as $category => $categoryDisciplines): ?>
                    <?php
                      $categoryWeight = $combineCategoryWeights[$category] ?? 1;
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
                      $showDisciplineWeights = false;
                      foreach ($displayDisciplines as $discipline) {
                        $discId = (int)$discipline["id"];
                        $discWeight = $combineDisciplineWeights[$discId] ?? 1;
                        if ((float)$discWeight !== 1.0) {
                          $showDisciplineWeights = true;
                          break;
                        }
                      }
                    ?>
                    <div class="category-block">
                      <h4 class="category-title">
                        <?php echo htmlspecialchars($category, ENT_QUOTES, "UTF-8"); ?>
                        <?php if ($showCategoryWeights): ?>
                          <span class="meta">(<?php echo htmlspecialchars($categoryWeight, ENT_QUOTES, "UTF-8"); ?>x)</span>
                        <?php endif; ?>
                      </h4>
                      <?php if (count($displayDisciplines) > 1): ?>
                        <?php
                          $categoryScore = $categoryAverages[$category][$selectedPlayerId] ?? null;
                          $categoryScoreLabel = $categoryScore === null ? "-" : uc_format_points($categoryScore) . " " . t("common.points_abbr", "P");
                        ?>
                        <p class="help"><?php echo htmlspecialchars(t("combine.category.score", "Kategorie-Score"), ENT_QUOTES, "UTF-8"); ?>: <?php echo htmlspecialchars($categoryScoreLabel, ENT_QUOTES, "UTF-8"); ?></p>
                      <?php endif; ?>
                      <ul class="list">
                        <?php foreach ($displayDisciplines as $discipline): ?>
                          <?php
                            $discId = (int)$discipline["id"];
                            $direction = $discipline["rating_direction"] ?? "more";
                            if ($direction !== "less" && $direction !== "more") {
                              $direction = "more";
                            }
                            $unit = uc_format_unit($discipline["unit"] ?? "", $unitAbbrMap);
                            $disciplineWeight = $combineDisciplineWeights[$discId] ?? 1;
                            $expectedMinValue = uc_value_to_float($discipline["expected_min"] ?? null);
                            $expectedMaxValue = uc_value_to_float($discipline["expected_max"] ?? null);
                            $bonusRel = uc_bonus_value($discipline["bonus_relative"] ?? null);
                            $bonusAbs = uc_bonus_value($discipline["bonus_absolute"] ?? null);
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
                            if ($direction === "less") {
                              asort($rankValues, SORT_NUMERIC);
                            } else {
                              arsort($rankValues, SORT_NUMERIC);
                            }
                            $ranks = [];
                            $pos = 0;
                            $rank = 0;
                            $prev = null;
                            foreach ($rankValues as $playerId => $val) {
                              $pos++;
                              if ($prev === null || $val != $prev) {
                                $rank = $pos;
                                $prev = $val;
                              }
                              $ranks[$playerId] = $rank;
                            }
                            $bestValue = null;
                            $worstValue = null;
                            if (!empty($rankValues)) {
                              $values = array_values($rankValues);
                              if ($direction === "less") {
                                $bestValue = min($values);
                                $worstValue = max($values);
                              } else {
                                $bestValue = max($values);
                                $worstValue = min($values);
                              }
                            }
                            $playerValue = $resultsByDiscipline[$discId][$selectedPlayerId] ?? null;
                            $display = uc_display_value($playerValue, "-");
                            if ($display !== "-" && $unit !== "") { $display .= " " . $unit; }
                            $numericValue = $rankValues[$selectedPlayerId] ?? null;
                            $bestExpected = $expectedMaxValue;
                            $worstExpected = $expectedMinValue;
                            if ($expectedMinValue !== null && $expectedMaxValue !== null) {
                              if ($direction === "less") {
                                $bestExpected = min($expectedMinValue, $expectedMaxValue);
                                $worstExpected = max($expectedMinValue, $expectedMaxValue);
                              } else {
                                $bestExpected = max($expectedMinValue, $expectedMaxValue);
                                $worstExpected = min($expectedMinValue, $expectedMaxValue);
                              }
                            }
                            $minLabel = $worstExpected === null ? "-" : uc_display_value($worstExpected, "-");
                            $maxLabel = $bestExpected === null ? "-" : uc_display_value($bestExpected, "-");
                            if ($overallMode === "abs" && $unit !== "") {
                              if ($minLabel !== "-") { $minLabel .= " " . $unit; }
                              if ($maxLabel !== "-") { $maxLabel .= " " . $unit; }
                            }
                            if ($overallMode === "abs") {
                              $points = uc_absolute_points($numericValue, $expectedMinValue, $expectedMaxValue, $direction);
                              if ($points === null) {
                                $points = 0;
                              }
                              if ($bonusAbs > 0 && uc_absolute_bonus_applies($numericValue, $expectedMaxValue, $direction)) {
                                $points += $bonusAbs;
                              }
                            } elseif ($numericValue === null || $bestValue === null || $worstValue === null) {
                              $points = 0;
                            } elseif ($bestValue == $worstValue) {
                              $points = 2;
                            } else {
                              $ratio = ($numericValue - $worstValue) / ($bestValue - $worstValue);
                              $points = 1 + $ratio;
                            }
                            if ($overallMode === "sum" && $bonusRel > 0 && $numericValue !== null && $bestValue !== null && $numericValue == $bestValue) {
                              $points += $bonusRel;
                            }
                            $pointsLabel = uc_format_points($points) . " " . t("common.points_abbr", "P");
                            $rankLabel = isset($ranks[$selectedPlayerId]) ? (string)$ranks[$selectedPlayerId] : "-";
                          ?>
                          <li class="list-item">
                            <div>
                              <strong><?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?></strong>
                              <?php if ($showDisciplineWeights): ?>
                                <span class="meta">(<?php echo htmlspecialchars($disciplineWeight, ENT_QUOTES, "UTF-8"); ?>x)</span>
                              <?php endif; ?>
                              <?php if ($display !== "-"): ?>
                                <span class="meta"><?php echo htmlspecialchars($display, ENT_QUOTES, "UTF-8"); ?></span>
                              <?php endif; ?>
                              <?php if ($overallMode === "abs"): ?>
                                <span class="meta"><?php echo htmlspecialchars(t("combine.label.worst", "Schlechtester"), ENT_QUOTES, "UTF-8"); ?>: <?php echo htmlspecialchars($minLabel, ENT_QUOTES, "UTF-8"); ?> · <?php echo htmlspecialchars(t("combine.label.best", "Bester"), ENT_QUOTES, "UTF-8"); ?>: <?php echo htmlspecialchars($maxLabel, ENT_QUOTES, "UTF-8"); ?></span>
                              <?php endif; ?>
                            </div>
                            <span class="badge">
                              <?php echo htmlspecialchars(t("common.place", "Platz") . " " . $rankLabel . " · " . $pointsLabel, ENT_QUOTES, "UTF-8"); ?>
                            </span>
                          </li>
                        <?php endforeach; ?>
                      </ul>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <script id="radar-data" type="application/json"><?php echo json_encode($radarData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if (empty($assignedDisciplines)): ?>
          <p class="help"><?php echo htmlspecialchars(t("combine.disciplines.empty_assigned", "Keine Disziplinen zugeordnet."), ENT_QUOTES, "UTF-8"); ?></p>
        <?php else: ?>
          <?php
            $showCategoryWeights = false;
            foreach ($assignedDisciplinesByCategory as $categoryKey => $categoryDisciplines) {
              $weight = $combineCategoryWeights[$categoryKey] ?? 1;
              if ((float)$weight !== 1.0) {
                $showCategoryWeights = true;
                break;
              }
            }
          ?>
          <?php foreach ($assignedDisciplinesByCategory as $category => $categoryDisciplines): ?>
            <?php
              $categoryWeight = $combineCategoryWeights[$category] ?? 1;
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
              $showDisciplineWeights = false;
              foreach ($displayDisciplines as $discipline) {
                $discId = (int)$discipline["id"];
                $discWeight = $combineDisciplineWeights[$discId] ?? 1;
                if ((float)$discWeight !== 1.0) {
                  $showDisciplineWeights = true;
                  break;
                }
              }
            ?>
            <div class="category-block">
              <h3 class="category-title">
                <?php echo htmlspecialchars($category, ENT_QUOTES, "UTF-8"); ?>
                <?php if ($showCategoryWeights): ?>
                  <span class="meta">(<?php echo htmlspecialchars($categoryWeight, ENT_QUOTES, "UTF-8"); ?>x)</span>
                <?php endif; ?>
              </h3>
              <?php foreach ($displayDisciplines as $discipline): ?>
                <?php
                  $discId = (int)$discipline["id"];
                  $direction = $discipline["rating_direction"] ?? "more";
                  if ($direction !== "less" && $direction !== "more") {
                    $direction = "more";
                  }
                  $unit = uc_format_unit($discipline["unit"] ?? "", $unitAbbrMap);
                  $unitLabel = uc_format_unit_label($discipline["unit"] ?? "", $unitAbbrMap);
                  $disciplineWeight = $combineDisciplineWeights[$discId] ?? 1;
                  $expectedMinValue = uc_value_to_float($discipline["expected_min"] ?? null);
                  $expectedMaxValue = uc_value_to_float($discipline["expected_max"] ?? null);
                  $bonusRel = uc_bonus_value($discipline["bonus_relative"] ?? null);
                  $bonusAbs = uc_bonus_value($discipline["bonus_absolute"] ?? null);
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
                  $topValue = null;
                  $topPlayerIds = [];
                  $averageValue = null;
                  if (!empty($rankValues)) {
                    $values = array_values($rankValues);
                    $averageValue = array_sum($values) / count($values);
                    $topValue = $direction === "less" ? min($values) : max($values);
                    foreach ($rankValues as $playerId => $numeric) {
                      if ($numeric == $topValue) {
                        $topPlayerIds[] = $playerId;
                      }
                    }
                  }
                  if ($direction === "less") {
                    asort($rankValues, SORT_NUMERIC);
                  } else {
                    arsort($rankValues, SORT_NUMERIC);
                  }
                  $ranks = [];
                  $pos = 0;
                  $rank = 0;
                  $prev = null;
                  foreach ($rankValues as $playerId => $val) {
                    $pos++;
                    if ($prev === null || $val != $prev) {
                      $rank = $pos;
                      $prev = $val;
                    }
                    $ranks[$playerId] = $rank;
                  }
                  $bestValue = null;
                  $worstValue = null;
                  if (!empty($rankValues)) {
                    $values = array_values($rankValues);
                    if ($direction === "less") {
                      $bestValue = min($values);
                      $worstValue = max($values);
                    } else {
                      $bestValue = max($values);
                      $worstValue = min($values);
                    }
                  }
                  $bestExpected = $expectedMaxValue;
                  $worstExpected = $expectedMinValue;
                  if ($expectedMinValue !== null && $expectedMaxValue !== null) {
                    if ($direction === "less") {
                      $bestExpected = min($expectedMinValue, $expectedMaxValue);
                      $worstExpected = max($expectedMinValue, $expectedMaxValue);
                    } else {
                      $bestExpected = max($expectedMinValue, $expectedMaxValue);
                      $worstExpected = min($expectedMinValue, $expectedMaxValue);
                    }
                  }
                  $minLabel = $worstExpected === null ? "-" : uc_display_value($worstExpected, "-");
                  $maxLabel = $bestExpected === null ? "-" : uc_display_value($bestExpected, "-");
                  if ($overallMode === "abs" && $unit !== "") {
                    if ($minLabel !== "-") { $minLabel .= " " . $unit; }
                    if ($maxLabel !== "-") { $maxLabel .= " " . $unit; }
                  }
                ?>
                <div class="info-card">
                  <details>
                    <summary>
                      <strong><?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?></strong>
                      <?php if ($showDisciplineWeights): ?>
                        <span class="meta">(<?php echo htmlspecialchars($disciplineWeight, ENT_QUOTES, "UTF-8"); ?>x)</span>
                      <?php endif; ?>
                      <?php if ($overallMode === "abs"): ?>
                        <span class="meta"><?php echo htmlspecialchars(t("combine.label.worst", "Schlechtester"), ENT_QUOTES, "UTF-8"); ?>: <?php echo htmlspecialchars($minLabel, ENT_QUOTES, "UTF-8"); ?> · <?php echo htmlspecialchars(t("combine.label.best", "Bester"), ENT_QUOTES, "UTF-8"); ?>: <?php echo htmlspecialchars($maxLabel, ENT_QUOTES, "UTF-8"); ?></span>
                      <?php endif; ?>
                      <span class="meta">
                        <?php
                          $topLabel = $topValue === null ? "-" : uc_display_value($topValue, "-");
                          if ($topLabel !== "-" && $unit !== "") { $topLabel .= " " . $unit; }
                          $avgLabel = $averageValue === null
                            ? "-"
                            : uc_display_value(number_format($averageValue, 2, ".", ""), "-");
                          if ($avgLabel !== "-" && $unit !== "") { $avgLabel .= " " . $unit; }
                        ?>
                        <?php echo htmlspecialchars(t("combine.label.top", "Top"), ENT_QUOTES, "UTF-8"); ?>: <?php echo htmlspecialchars($topLabel, ENT_QUOTES, "UTF-8"); ?>
                        &middot;
                        <?php echo htmlspecialchars(t("combine.label.avg", "Ø"), ENT_QUOTES, "UTF-8"); ?>: <?php echo htmlspecialchars($avgLabel, ENT_QUOTES, "UTF-8"); ?>
                      </span>
                      <?php if (!empty($topPlayerIds)): ?>
                        <?php
                          $topNames = [];
                          foreach ($topPlayerIds as $topPlayerId) {
                            foreach ($filteredPlayers as $player) {
                              if ((int)$player["id"] === (int)$topPlayerId) {
                                $topNames[] = $player["first_name"] . " " . $player["last_name"];
                                break;
                              }
                            }
                          }
                        ?>
                        <div class="detail">
                          <?php echo htmlspecialchars(t("combine.label.top", "Top"), ENT_QUOTES, "UTF-8"); ?>: <?php echo htmlspecialchars(implode(", ", $topNames), ENT_QUOTES, "UTF-8"); ?>
                        </div>
                      <?php else: ?>
                        <div class="detail"><?php echo htmlspecialchars(t("combine.label.top", "Top"), ENT_QUOTES, "UTF-8"); ?>: -</div>
                      <?php endif; ?>
                    </summary>
                    <?php if (empty($filteredPlayers)): ?>
                      <p class="help"><?php echo htmlspecialchars(t("combine.players.empty_filtered", "Keine Spieler für den gewählten Filter."), ENT_QUOTES, "UTF-8"); ?></p>
                    <?php else: ?>
                      <?php if ($unitLabel !== ""): ?>
                        <p class="help"><?php echo htmlspecialchars(t("common.unit", "Einheit"), ENT_QUOTES, "UTF-8"); ?>: <?php echo htmlspecialchars($unitLabel, ENT_QUOTES, "UTF-8"); ?></p>
                      <?php endif; ?>
                      <?php
                        $orderedPlayers = [];
                        $rankedIds = array_keys($rankValues);
                        foreach ($rankedIds as $playerId) {
                          foreach ($filteredPlayers as $player) {
                            if ((int)$player["id"] === (int)$playerId) {
                              $orderedPlayers[] = $player;
                              break;
                            }
                          }
                        }
                        foreach ($filteredPlayers as $player) {
                          if (!in_array((int)$player["id"], $rankedIds, true)) {
                            $orderedPlayers[] = $player;
                          }
                        }
                      ?>
                      <ul class="list">
                        <?php foreach ($orderedPlayers as $player): ?>
                          <?php $playerId = (int)$player["id"]; ?>
                          <?php $value = $resultsByDiscipline[$discId][$playerId] ?? null; ?>
                          <?php $display = uc_display_value($value, "-"); ?>
                          <?php if ($display !== "-" && $unit !== "") { $display .= " " . $unit; } ?>
                          <?php
                            $numericValue = $rankValues[$playerId] ?? null;
                            if ($overallMode === "abs") {
                              $points = uc_absolute_points($numericValue, $expectedMinValue, $expectedMaxValue, $direction);
                              if ($points === null) {
                                $points = 0;
                              }
                              if ($bonusAbs > 0 && uc_absolute_bonus_applies($numericValue, $expectedMaxValue, $direction)) {
                                $points += $bonusAbs;
                              }
                            } elseif ($numericValue === null || $bestValue === null || $worstValue === null) {
                              $points = 0;
                            } elseif ($bestValue == $worstValue) {
                              $points = 2;
                            } else {
                              $ratio = ($numericValue - $worstValue) / ($bestValue - $worstValue);
                              $points = 1 + $ratio;
                            }
                            if ($overallMode === "sum" && $bonusRel > 0 && $numericValue !== null && $bestValue !== null && $numericValue == $bestValue) {
                              $points += $bonusRel;
                            }
                            $pointsLabel = uc_format_points($points) . " " . t("common.points_abbr", "P");
                          ?>
                          <?php $rankLabel = isset($ranks[$playerId]) ? (string)$ranks[$playerId] : "-"; ?>
                          <li class="list-item">
                            <div class="result-name">
                              <span class="rank-pill">
                                <?php echo htmlspecialchars(t("common.place", "Platz"), ENT_QUOTES, "UTF-8"); ?> <?php echo htmlspecialchars($rankLabel, ENT_QUOTES, "UTF-8"); ?>
                                &middot;
                                <?php echo htmlspecialchars($pointsLabel, ENT_QUOTES, "UTF-8"); ?>
                              </span>
                              <strong>
                                <?php echo htmlspecialchars($player["first_name"], ENT_QUOTES, "UTF-8"); ?>
                                <?php echo " " . htmlspecialchars($player["last_name"], ENT_QUOTES, "UTF-8"); ?>
                              </strong>
                            </div>
                            <span class="badge">
                              <?php echo htmlspecialchars($display, ENT_QUOTES, "UTF-8"); ?>
                            </span>
                          </li>
                        <?php endforeach; ?>
                      </ul>
                    <?php endif; ?>
                  </details>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </section>
    
