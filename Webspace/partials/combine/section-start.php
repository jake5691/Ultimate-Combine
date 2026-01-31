      <section class="auth-card">
        <h2><?php echo htmlspecialchars(t("combine.section.entry", "Eintragen"), ENT_QUOTES, "UTF-8"); ?></h2>
        <?php if ($startError): ?>
          <p class="help"><?php echo htmlspecialchars($startError, ENT_QUOTES, "UTF-8"); ?></p>
        <?php endif; ?>
        <?php if (!empty($assignedDisciplines) && !empty($assignedPlayers)): ?>
          <form class="form" method="get" action="combine.php">
            <input type="hidden" name="id" value="<?php echo (int)$combineId; ?>">
            <input type="hidden" name="mode" value="start">
            <label class="field">
              <span><?php echo htmlspecialchars(t("common.discipline", "Disziplin"), ENT_QUOTES, "UTF-8"); ?></span>
              <select
                name="discipline_id"
                required
                data-discipline-select
                data-combine-id="<?php echo (int)$combineId; ?>"
                data-confirm-unsaved="<?php echo htmlspecialchars(t("combine.confirm.unsaved_change", "Ungesicherte Änderungen gehen verloren. Trotzdem wechseln?"), ENT_QUOTES, "UTF-8"); ?>"
              >
                <?php foreach ($assignedDisciplines as $discipline): ?>
                  <option value="<?php echo (int)$discipline["id"]; ?>"<?php echo (int)$discipline["id"] === (int)$activeDisciplineId ? " selected" : ""; ?>>
                    <?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </label>
            <?php if (!empty($activeDisciplineDescription)): ?>
              <p class="help"><?php echo htmlspecialchars($activeDisciplineDescription, ENT_QUOTES, "UTF-8"); ?></p>
            <?php endif; ?>
          </form>
        <?php endif; ?>
      </section>

      <?php if ($needsConfirmation): ?>
        <section class="auth-card">
          <h3><?php echo htmlspecialchars(t("combine.confirm.title", "Bestätigung nötig"), ENT_QUOTES, "UTF-8"); ?></h3>
          <p class="help"><?php echo htmlspecialchars(t("combine.confirm.notice", "Es gab zwischenzeitliche Änderungen. Bitte bestätige das Überschreiben."), ENT_QUOTES, "UTF-8"); ?></p>
          <div class="conflict-list">
            <?php foreach ($conflicts as $playerId => $conflict): ?>
              <?php
                $playerName = t("combine.player_placeholder", "Spieler #") . (int)$playerId;
                foreach ($assignedPlayers as $player) {
                  if ((int)$player["id"] === (int)$playerId) {
                    $playerName = $player["first_name"] . " " . $player["last_name"];
                    break;
                  }
                }
                $currentValue = uc_display_value($conflict["current"], "-");
                $newValue = uc_display_value($conflict["new"], "-");
              ?>
              <div class="conflict-row">
                <span><?php echo htmlspecialchars($playerName, ENT_QUOTES, "UTF-8"); ?></span>
                <span><?php echo htmlspecialchars(t("combine.confirm.current", "Aktuell"), ENT_QUOTES, "UTF-8"); ?>: <?php echo htmlspecialchars($currentValue, ENT_QUOTES, "UTF-8"); ?></span>
                <span><?php echo htmlspecialchars(t("combine.confirm.new", "Neu"), ENT_QUOTES, "UTF-8"); ?>: <?php echo htmlspecialchars($newValue, ENT_QUOTES, "UTF-8"); ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <form method="post" action="" class="form">
            <input type="hidden" name="action" value="confirm_save_results">
            <input type="hidden" name="discipline_id" value="<?php echo (int)$activeDisciplineId; ?>">
            <?php foreach ($orderedPlayers as $player): ?>
              <?php $playerId = (int)$player["id"]; ?>
              <input type="hidden" name="result[<?php echo $playerId; ?>]" value="<?php echo htmlspecialchars($resultValues[$playerId] ?? "", ENT_QUOTES, "UTF-8"); ?>">
            <?php endforeach; ?>
            <div class="form-actions">
              <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("combine.confirm.save", "Bestätigen und speichern"), ENT_QUOTES, "UTF-8"); ?></button>
              <a class="pill-button is-muted" href="combine.php?id=<?php echo (int)$combineId; ?>&mode=start&discipline_id=<?php echo (int)$activeDisciplineId; ?>"><?php echo htmlspecialchars(t("common.cancel", "Abbrechen"), ENT_QUOTES, "UTF-8"); ?></a>
            </div>
          </form>
        </section>
      <?php endif; ?>

      <?php if (!$startError && !empty($assignedDisciplines) && !empty($assignedPlayers) && $activeDisciplineId): ?>
        <section class="auth-card">
          <div class="card-header">
            <h3><?php echo htmlspecialchars(t("combine.section.capture_results", "Ergebnisse erfassen"), ENT_QUOTES, "UTF-8"); ?></h3>
            <div class="card-actions">
              <form class="csv-upload" method="post" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_results_csv">
                <input type="hidden" name="discipline_id" value="<?php echo (int)$activeDisciplineId; ?>">
                <input class="csv-upload-input" type="file" name="results_csv" accept=".csv,text/csv" required>
                <button class="pill-button csv-upload-trigger" type="button"><?php echo htmlspecialchars(t("combine.results.csv_upload", "importieren"), ENT_QUOTES, "UTF-8"); ?></button>
              </form>
              <button class="info-icon js-info" type="button" aria-label="<?php echo htmlspecialchars(t("common.explanation_prefix", "Erklärung:"), ENT_QUOTES, "UTF-8"); ?> <?php echo htmlspecialchars(t("combine.results.csv_upload_info", "CSV mit Header: Athlet, Finale Zeit. Werte werden für die gewählte Disziplin übernommen."), ENT_QUOTES, "UTF-8"); ?>" aria-expanded="false" data-tooltip="<?php echo htmlspecialchars(t("combine.results.csv_upload_info", "CSV mit Header: Athlet, Finale Zeit. Werte werden für die gewählte Disziplin übernommen."), ENT_QUOTES, "UTF-8"); ?>">i</button>
            </div>
          </div>
          <?php if (!empty($activeDisciplineUnit)): ?>
            <p class="help"><?php echo htmlspecialchars($activeDisciplineUnit, ENT_QUOTES, "UTF-8"); ?></p>
          <?php endif; ?>
          <?php if (!empty($csvNotice)): ?>
            <p class="help"><?php echo htmlspecialchars($csvNotice, ENT_QUOTES, "UTF-8"); ?></p>
          <?php endif; ?>
          <form class="form" method="post" action="">
            <input type="hidden" name="action" value="save_results">
            <input type="hidden" name="discipline_id" value="<?php echo (int)$activeDisciplineId; ?>">
            <div class="result-grid">
              <?php foreach ($orderedPlayers as $player): ?>
                <?php $playerId = (int)$player["id"]; ?>
                <label class="result-item">
                  <span>
                    <?php echo htmlspecialchars($player["first_name"], ENT_QUOTES, "UTF-8"); ?>
                    <?php echo " " . htmlspecialchars($player["last_name"], ENT_QUOTES, "UTF-8"); ?>
                  </span>
                  <span class="result-value">
                    <?php if (!empty($csvImported)): ?>
                      <?php
                        $playerOptions = $csvOptionsByPlayer[$playerId] ?? [];
                        $currentValue = $resultValues[$playerId] ?? null;
                        $currentDbValue = $resultOriginalValues[$playerId] ?? null;
                        $currentLabel = $currentDbValue !== null ? uc_display_value($currentDbValue) : "";
                      ?>
                      <select class="result-input" name="result[<?php echo $playerId; ?>]">
                        <option value=""><?php echo htmlspecialchars(t("combine.results.csv_select", "Bitte wählen"), ENT_QUOTES, "UTF-8"); ?></option>
                        <?php if ($currentDbValue !== null): ?>
                          <option value="<?php echo htmlspecialchars((string)$currentDbValue, ENT_QUOTES, "UTF-8"); ?>"<?php echo $currentValue !== null && (string)$currentValue === (string)$currentDbValue ? " selected" : ""; ?>>
                            <?php echo htmlspecialchars(t("combine.results.current_value", "Aktuell") . ": " . $currentLabel, ENT_QUOTES, "UTF-8"); ?>
                          </option>
                        <?php endif; ?>
                        <?php foreach ($playerOptions as $option): ?>
                          <?php
                            $optionValue = (string)($option["value"] ?? "");
                            $optionLabel = trim((string)($option["label"] ?? ""));
                            $optionRaw = trim((string)($option["raw"] ?? ""));
                            $optionText = $optionLabel !== "" ? $optionLabel . ": " . $optionRaw : $optionRaw;
                          ?>
                          <option value="<?php echo htmlspecialchars($optionValue, ENT_QUOTES, "UTF-8"); ?>"<?php echo $currentValue !== null && (string)$currentValue === $optionValue ? " selected" : ""; ?>>
                            <?php echo htmlspecialchars($optionText, ENT_QUOTES, "UTF-8"); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    <?php else: ?>
                      <input class="result-input" type="text" name="result[<?php echo $playerId; ?>]" value="<?php echo htmlspecialchars(uc_display_value($resultValues[$playerId] ?? ""), ENT_QUOTES, "UTF-8"); ?>">
                    <?php endif; ?>
                    <?php if (!empty($activeDisciplineUnitAbbr)): ?>
                      <span class="unit-tag"><?php echo htmlspecialchars($activeDisciplineUnitAbbr, ENT_QUOTES, "UTF-8"); ?></span>
                    <?php endif; ?>
                  </span>
                  <input type="hidden" name="original[<?php echo $playerId; ?>]" value="<?php echo htmlspecialchars($resultOriginalValues[$playerId] ?? ($resultValues[$playerId] ?? ""), ENT_QUOTES, "UTF-8"); ?>">
                </label>
              <?php endforeach; ?>
            </div>
            <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("common.save", "Speichern"), ENT_QUOTES, "UTF-8"); ?></button>
            <?php if ($saveNotice): ?>
              <p class="help"><?php echo htmlspecialchars($saveNotice, ENT_QUOTES, "UTF-8"); ?></p>
            <?php endif; ?>
          </form>
        </section>
      <?php endif; ?>
    
