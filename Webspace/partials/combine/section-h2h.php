      <section class="info">
        <h2><?php echo htmlspecialchars(t("combine.section.h2h", "Head 2 Head"), ENT_QUOTES, "UTF-8"); ?></h2>
        <?php
          $playerMap = [];
          foreach ($assignedPlayers as $player) {
            $playerMap[(int)$player["id"]] = $player;
          }
          $h2hPlayerA = $h2hPlayerAId ? ($playerMap[$h2hPlayerAId] ?? null) : null;
          $h2hPlayerB = $h2hPlayerBId ? ($playerMap[$h2hPlayerBId] ?? null) : null;
          $h2hReady = $h2hPlayerA && $h2hPlayerB && $h2hPlayerAId !== $h2hPlayerBId;
          $h2hBaseParams = [
            "id" => (int)$combineId,
            "mode" => "h2h",
          ];
          if ($h2hPlayerAId) {
            $h2hBaseParams["player_a"] = $h2hPlayerAId;
          }
          if ($h2hPlayerBId) {
            $h2hBaseParams["player_b"] = $h2hPlayerBId;
          }
          $h2hBaseUrl = "combine.php?" . http_build_query($h2hBaseParams);
          $h2hSumUrl = $h2hBaseUrl . "&overall=sum";
          $h2hAvgUrl = $h2hBaseUrl . "&overall=avg";
          $h2hAbsUrl = $h2hBaseUrl . "&overall=abs";
        ?>
        <div class="info-card">
          <div class="card-header">
            <div class="card-actions">
              <button class="pill-button<?php echo $overallMode === "sum" ? " is-active" : ""; ?>" type="button" onclick="window.location.href='<?php echo htmlspecialchars($h2hSumUrl, ENT_QUOTES, "UTF-8"); ?>'"><?php echo htmlspecialchars(t("combine.mode.relative", "Relativ"), ENT_QUOTES, "UTF-8"); ?></button>
              <button class="pill-button<?php echo $overallMode === "avg" ? " is-active" : ""; ?>" type="button" onclick="window.location.href='<?php echo htmlspecialchars($h2hAvgUrl, ENT_QUOTES, "UTF-8"); ?>'"><?php echo htmlspecialchars(t("combine.mode.relative_avg", "Ø Relativ"), ENT_QUOTES, "UTF-8"); ?></button>
              <button class="pill-button<?php echo $overallMode === "abs" ? " is-active" : ""; ?>" type="button" onclick="window.location.href='<?php echo htmlspecialchars($h2hAbsUrl, ENT_QUOTES, "UTF-8"); ?>'"><?php echo htmlspecialchars(t("combine.mode.absolute", "Absolut"), ENT_QUOTES, "UTF-8"); ?></button>
              <?php if ($h2hReady): ?>
                <?php
                  $h2hShareUrl = $h2hBaseUrl . "&overall=" . urlencode($overallMode) . "&share=img";
                ?>
                <button class="pill-button is-share" type="button" onclick="window.location.href='<?php echo htmlspecialchars($h2hShareUrl, ENT_QUOTES, "UTF-8"); ?>'"><?php echo htmlspecialchars(t("common.share", "Teilen"), ENT_QUOTES, "UTF-8"); ?></button>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($overallMode === "sum"): ?>
            <p class="help"><?php echo htmlspecialchars(t("combine.h2h.help.relative", "Relativ: Punkte werden relativ zu allen Teilnehmern berechnet."), ENT_QUOTES, "UTF-8"); ?></p>
          <?php elseif ($overallMode === "avg"): ?>
            <p class="help"><?php echo htmlspecialchars(t("combine.h2h.help.relative_avg", "Ø Relativ: Punkte werden relativ zu allen Teilnehmern berechnet."), ENT_QUOTES, "UTF-8"); ?></p>
          <?php else: ?>
            <p class="help"><?php echo htmlspecialchars(t("combine.h2h.help.absolute", "Absolut: Punkte anhand Erwartungs-Min/Max. Es werden nur Disziplinen mit Erwartungswerten angezeigt."), ENT_QUOTES, "UTF-8"); ?></p>
          <?php endif; ?>
          <form class="form" method="get" action="combine.php">
            <input type="hidden" name="id" value="<?php echo (int)$combineId; ?>">
            <input type="hidden" name="mode" value="h2h">
            <input type="hidden" name="overall" value="<?php echo htmlspecialchars($overallMode, ENT_QUOTES, "UTF-8"); ?>">
            <label class="field">
              <select name="player_a" required>
                <option value=""><?php echo htmlspecialchars(t("common.choose", "Bitte wählen"), ENT_QUOTES, "UTF-8"); ?></option>
                <?php foreach ($assignedPlayers as $player): ?>
                  <?php $isDisabled = (int)$player["id"] === (int)$h2hPlayerBId; ?>
                  <option value="<?php echo (int)$player["id"]; ?>"<?php echo (int)$player["id"] === (int)$h2hPlayerAId ? " selected" : ""; ?><?php echo $isDisabled ? " disabled" : ""; ?>>
                    <?php echo htmlspecialchars($player["first_name"] . " " . $player["last_name"], ENT_QUOTES, "UTF-8"); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <label class="field">
              <select name="player_b" required>
                <option value=""><?php echo htmlspecialchars(t("common.choose", "Bitte wählen"), ENT_QUOTES, "UTF-8"); ?></option>
                <?php foreach ($assignedPlayers as $player): ?>
                  <?php $isDisabled = (int)$player["id"] === (int)$h2hPlayerAId; ?>
                  <option value="<?php echo (int)$player["id"]; ?>"<?php echo (int)$player["id"] === (int)$h2hPlayerBId ? " selected" : ""; ?><?php echo $isDisabled ? " disabled" : ""; ?>>
                    <?php echo htmlspecialchars($player["first_name"] . " " . $player["last_name"], ENT_QUOTES, "UTF-8"); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <div class="form-actions">
              <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("combine.h2h.compare", "Vergleichen"), ENT_QUOTES, "UTF-8"); ?></button>
            </div>
          </form>
          <?php if ($h2hPlayerAId && $h2hPlayerBId && $h2hPlayerAId === $h2hPlayerBId): ?>
            <p class="help"><?php echo htmlspecialchars(t("combine.h2h.error.same_player", "Bitte zwei unterschiedliche Spieler auswählen."), ENT_QUOTES, "UTF-8"); ?></p>
          <?php endif; ?>
        </div>

        <?php if ($h2hReady): ?>
          <?php
          require __DIR__ . "/../../lib/h2h-data.php";
          ?>
          <div class="info-card">
            <div class="card-header">
              <h3><?php echo htmlspecialchars(t("combine.overall.short", "Overall"), ENT_QUOTES, "UTF-8"); ?></h3>
            </div>
            <ul class="list">
              <li class="list-item">
                <div class="result-name">
                  <strong><?php echo htmlspecialchars($playerALabel, ENT_QUOTES, "UTF-8"); ?></strong>
                </div>
                <span class="badge">
                  <?php echo htmlspecialchars(t("common.place", "Platz") . " " . $overallRankA . " · " . $overallPointsPrefix . uc_format_points($overallPointsA) . " " . t("common.points_abbr", "P"), ENT_QUOTES, "UTF-8"); ?>
                </span>
              </li>
              <li class="list-item">
                <div class="result-name">
                  <strong><?php echo htmlspecialchars($playerBLabel, ENT_QUOTES, "UTF-8"); ?></strong>
                </div>
                <span class="badge">
                  <?php echo htmlspecialchars(t("common.place", "Platz") . " " . $overallRankB . " · " . $overallPointsPrefix . uc_format_points($overallPointsB) . " " . t("common.points_abbr", "P"), ENT_QUOTES, "UTF-8"); ?>
                </span>
              </li>
            </ul>
          </div>
          <?php if (empty($assignedDisciplines)): ?>
            <p class="help"><?php echo htmlspecialchars(t("combine.disciplines.empty_assigned", "Keine Disziplinen zugeordnet."), ENT_QUOTES, "UTF-8"); ?></p>
          <?php else: ?>
            <div class="info-card">
              <div class="h2h-legend">
                <span class="legend-item legend-player"><?php echo htmlspecialchars($playerALabel, ENT_QUOTES, "UTF-8"); ?></span>
                <span class="legend-item legend-team"><?php echo htmlspecialchars($playerBLabel, ENT_QUOTES, "UTF-8"); ?></span>
              </div>
            </div>
            <?php foreach ($assignedDisciplinesByCategory as $category => $categoryDisciplines): ?>
              <?php
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
              ?>
              <div class="category-block">
                <h3 class="category-title"><?php echo htmlspecialchars($category, ENT_QUOTES, "UTF-8"); ?></h3>
                <ul class="list h2h-list">
                  <?php foreach ($displayDisciplines as $discipline): ?>
                    <?php
                      $discId = (int)$discipline["id"];
                      $direction = $discipline["rating_direction"] ?? "more";
                      if ($direction !== "less" && $direction !== "more") {
                        $direction = "more";
                      }
                      $unit = uc_format_unit($discipline["unit"] ?? "", $unitAbbrMap);
                      $unitLabel = uc_format_unit_label($discipline["unit"] ?? "", $unitAbbrMap);
                      $expectedMinValue = uc_value_to_float($discipline["expected_min"] ?? null);
                      $expectedMaxValue = uc_value_to_float($discipline["expected_max"] ?? null);
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
                        $values = array_values($rankValues);
                        if ($direction === "less") {
                          $bestValue = min($values);
                          $worstValue = max($values);
                        } else {
                          $bestValue = max($values);
                          $worstValue = min($values);
                        }
                      }
                      $playerAValue = $resultsByDiscipline[$discId][$h2hPlayerAId] ?? null;
                      $playerBValue = $resultsByDiscipline[$discId][$h2hPlayerBId] ?? null;
                      $numericA = uc_value_to_float($playerAValue);
                      $numericB = uc_value_to_float($playerBValue);
                      if ($overallMode === "abs") {
                        $pointsA = uc_absolute_points($numericA, $expectedMinValue, $expectedMaxValue, $direction);
                        $pointsB = uc_absolute_points($numericB, $expectedMinValue, $expectedMaxValue, $direction);
                        if ($pointsA === null) { $pointsA = 0; }
                        if ($pointsB === null) { $pointsB = 0; }
                      } else {
                        if ($numericA === null || $bestValue === null || $worstValue === null) {
                          $pointsA = 0;
                        } elseif ($bestValue == $worstValue) {
                          $pointsA = 2;
                        } else {
                          $ratioA = ($numericA - $worstValue) / ($bestValue - $worstValue);
                          $pointsA = 1 + $ratioA;
                        }
                        if ($numericB === null || $bestValue === null || $worstValue === null) {
                          $pointsB = 0;
                        } elseif ($bestValue == $worstValue) {
                          $pointsB = 2;
                        } else {
                          $ratioB = ($numericB - $worstValue) / ($bestValue - $worstValue);
                          $pointsB = 1 + $ratioB;
                        }
                      }
                      $displayA = uc_display_value($playerAValue, "-");
                      $displayB = uc_display_value($playerBValue, "-");
                      if ($displayA !== "-" && $unit !== "") { $displayA .= " " . $unit; }
                      if ($displayB !== "-" && $unit !== "") { $displayB .= " " . $unit; }
                      $scaleScore = function ($value) {
                        $value = max(0, min(2, (float)$value));
                        if ($value <= 1) {
                          return ($value / 1) * 30;
                        }
                        return 30 + (($value - 1) / 1) * 70;
                      };
                      $percentA = $scaleScore($pointsA);
                      $percentB = $scaleScore($pointsB);
                    ?>
                    <li class="list-item">
                      <div class="h2h-discipline">
                        <div class="result-name">
                          <strong><?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?></strong>
                        </div>
                        <?php if (!empty($unitLabel)): ?>
                          <div class="detail h2h-unit"><?php echo htmlspecialchars(t("common.unit", "Einheit"), ENT_QUOTES, "UTF-8"); ?>: <?php echo htmlspecialchars($unitLabel, ENT_QUOTES, "UTF-8"); ?></div>
                        <?php endif; ?>
                        <?php if ($overallMode === "abs"): ?>
                          <div class="detail h2h-unit"><?php echo htmlspecialchars(t("combine.label.worst", "Schlechtester"), ENT_QUOTES, "UTF-8"); ?>: <?php echo htmlspecialchars($minLabel, ENT_QUOTES, "UTF-8"); ?> · <?php echo htmlspecialchars(t("combine.label.best", "Bester"), ENT_QUOTES, "UTF-8"); ?>: <?php echo htmlspecialchars($maxLabel, ENT_QUOTES, "UTF-8"); ?></div>
                        <?php endif; ?>
                      </div>
                      <div class="h2h-bars">
                        <div class="h2h-bar is-a">
                          <div class="h2h-fill" style="width: <?php echo htmlspecialchars(number_format($percentA, 2, ".", ""), ENT_QUOTES, "UTF-8"); ?>%;"></div>
                          <span class="h2h-value"><?php echo htmlspecialchars($displayA, ENT_QUOTES, "UTF-8"); ?></span>
                        </div>
                        <div class="h2h-bar is-b">
                          <div class="h2h-fill" style="width: <?php echo htmlspecialchars(number_format($percentB, 2, ".", ""), ENT_QUOTES, "UTF-8"); ?>%;"></div>
                          <span class="h2h-value"><?php echo htmlspecialchars($displayB, ENT_QUOTES, "UTF-8"); ?></span>
                        </div>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endforeach; ?>
            <?php if (!empty($h2hRadarData)): ?>
              <div class="radar-grid">
                <div class="radar-chart is-stacked">
                  <canvas id="radar-chart-h2h" width="360" height="360"></canvas>
                  <div class="radar-legend">
                    <span class="legend-item legend-player"><?php echo htmlspecialchars($playerALabel, ENT_QUOTES, "UTF-8"); ?></span>
                    <span class="legend-item legend-team"><?php echo htmlspecialchars($playerBLabel, ENT_QUOTES, "UTF-8"); ?></span>
                    <span class="legend-item legend-average"><?php echo htmlspecialchars(t("common.team", "Team"), ENT_QUOTES, "UTF-8"); ?></span>
                  </div>
                </div>
              </div>
              <script id="radar-data-h2h" type="application/json"><?php echo json_encode($h2hRadarData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
            <?php endif; ?>
          <?php endif; ?>
        <?php endif; ?>
      </section>
