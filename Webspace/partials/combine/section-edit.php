        <section class="auth-card" id="edit">
          <h2><?php echo htmlspecialchars(t("combine.edit.title", "Combine bearbeiten"), ENT_QUOTES, "UTF-8"); ?></h2>
          <form class="form" method="post" action="">
            <input type="hidden" name="action" value="update_combine">
          <label class="field">
            <span><?php echo htmlspecialchars(t("common.name", "Name"), ENT_QUOTES, "UTF-8"); ?></span>
            <input type="text" name="combine_name" value="<?php echo htmlspecialchars($formCombineName, ENT_QUOTES, "UTF-8"); ?>" required>
          </label>
          <label class="field">
            <span><?php echo htmlspecialchars(t("common.date", "Datum"), ENT_QUOTES, "UTF-8"); ?></span>
            <input type="date" name="event_date" value="<?php echo htmlspecialchars($formEventDate, ENT_QUOTES, "UTF-8"); ?>" required>
          </label>
          <label class="field">
            <span><?php echo htmlspecialchars(t("common.location", "Ort"), ENT_QUOTES, "UTF-8"); ?></span>
            <input type="text" name="combine_location" value="<?php echo htmlspecialchars($formCombineLocation, ENT_QUOTES, "UTF-8"); ?>">
          </label>
          <label class="field">
            <span><?php echo htmlspecialchars(t("common.notes", "Notizen"), ENT_QUOTES, "UTF-8"); ?></span>
            <textarea name="combine_notes" rows="3"><?php echo htmlspecialchars($formCombineNotes, ENT_QUOTES, "UTF-8"); ?></textarea>
          </label>

          <div class="field">
            <span><?php echo htmlspecialchars(t("common.players", "Spieler"), ENT_QUOTES, "UTF-8"); ?></span>
            <?php if (empty($players)): ?>
              <p class="help"><?php echo htmlspecialchars(t("combine.players.empty", "Noch keine Spieler angelegt."), ENT_QUOTES, "UTF-8"); ?></p>
            <?php else: ?>
              <div class="check-grid">
                <?php foreach ($players as $player): ?>
                  <label class="check-item">
                    <input type="checkbox" name="players[]" value="<?php echo (int)$player["id"]; ?>"<?php echo in_array((int)$player["id"], $formPlayerIds, true) ? " checked" : ""; ?>>
                    <span>
                      <?php echo htmlspecialchars($player["first_name"], ENT_QUOTES, "UTF-8"); ?>
                      <?php echo " " . htmlspecialchars($player["last_name"], ENT_QUOTES, "UTF-8"); ?>
                      <?php if ($player["jersey_number"] !== null): ?>
                        <span class="meta">#<?php echo (int)$player["jersey_number"]; ?></span>
                      <?php endif; ?>
                    </span>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="field">
            <span><?php echo htmlspecialchars(t("common.disciplines", "Disziplinen"), ENT_QUOTES, "UTF-8"); ?></span>
            <?php if (empty($disciplines)): ?>
              <p class="help"><?php echo htmlspecialchars(t("combine.disciplines.empty", "Noch keine Disziplinen angelegt."), ENT_QUOTES, "UTF-8"); ?></p>
            <?php else: ?>
              <?php
                $globalDisciplines = [];
                $teamDisciplines = [];
                foreach ($disciplines as $discipline) {
                  if (empty($discipline["team_id"])) {
                    $globalDisciplines[] = $discipline;
                  } else {
                    $teamDisciplines[] = $discipline;
                  }
                }
              ?>
              <?php if (!empty($teamDisciplines)): ?>
                <p class="help"><?php echo htmlspecialchars(t("combine.disciplines.team", "Team-Disziplinen"), ENT_QUOTES, "UTF-8"); ?></p>
                <div class="check-grid">
                  <?php foreach ($teamDisciplines as $discipline): ?>
                    <label class="check-item">
                      <input type="checkbox" name="disciplines[]" value="<?php echo (int)$discipline["id"]; ?>"<?php echo in_array((int)$discipline["id"], $formDisciplineIds, true) ? " checked" : ""; ?>>
                      <span>
                        <?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?>
                        <span class="meta">
                          <?php echo htmlspecialchars($discipline["category"], ENT_QUOTES, "UTF-8"); ?>
                          &middot;
                          <?php
                            $unitName = trim((string)($discipline["unit"] ?? ""));
                            $unitAbbr = uc_format_unit($unitName, $unitAbbrMap);
                            $unitLabel = $unitName;
                            if ($unitAbbr !== "" && $unitAbbr !== $unitName) {
                              $unitLabel .= " (" . $unitAbbr . ")";
                            }
                          ?>
                          <?php echo htmlspecialchars($unitLabel, ENT_QUOTES, "UTF-8"); ?>
                        </span>
                      </span>
                    </label>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
              <?php if (!empty($globalDisciplines)): ?>
                <p class="help"><?php echo htmlspecialchars(t("combine.disciplines.global", "Globale Disziplinen"), ENT_QUOTES, "UTF-8"); ?></p>
                <div class="check-grid">
                  <?php foreach ($globalDisciplines as $discipline): ?>
                    <label class="check-item">
                      <input type="checkbox" name="disciplines[]" value="<?php echo (int)$discipline["id"]; ?>"<?php echo in_array((int)$discipline["id"], $formDisciplineIds, true) ? " checked" : ""; ?>>
                      <span>
                        <?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?>
                        <span class="meta">
                          <?php echo htmlspecialchars($discipline["category"], ENT_QUOTES, "UTF-8"); ?>
                          &middot;
                          <?php
                            $unitName = trim((string)($discipline["unit"] ?? ""));
                            $unitAbbr = uc_format_unit($unitName, $unitAbbrMap);
                            $unitLabel = $unitName;
                            if ($unitAbbr !== "" && $unitAbbr !== $unitName) {
                              $unitLabel .= " (" . $unitAbbr . ")";
                            }
                          ?>
                          <?php echo htmlspecialchars($unitLabel, ENT_QUOTES, "UTF-8"); ?>
                        </span>
                      </span>
                    </label>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          </div>

          <?php
            $selectedDisciplinesByCategory = [];
            foreach ($disciplines as $discipline) {
              $discId = (int)$discipline["id"];
              if (!in_array($discId, $formDisciplineIds, true)) {
                continue;
              }
              $category = trim((string)($discipline["category"] ?? ""));
              if ($category === "") {
                $category = t("common.uncategorized", "Ohne Kategorie");
              }
              $selectedDisciplinesByCategory[$category][] = $discipline;
            }
          ?>

          <?php if (!empty($selectedDisciplinesByCategory)): ?>
            <div class="field">
              <div class="section-header">
                <span><?php echo htmlspecialchars(t("combine.weights.title", "Gewichtungen"), ENT_QUOTES, "UTF-8"); ?></span>
                <button class="info-icon js-info" type="button" aria-label="<?php echo htmlspecialchars(t("common.explanation_prefix", "Erklärung:"), ENT_QUOTES, "UTF-8"); ?> <?php echo $formatLabel($infoTexts["weights"] ?? t("combine.info.weights", "Gewichtungen legen fest, wie stark Kategorien und Disziplinen in die Gesamtwertung einfließen.\nKategorien Gewichtung beeinflussen den Einfluss auf den Gesamtscore, Disziplinen Gewichtung die Zusammensetzung des Scores dieser Kategorie.")); ?>" aria-expanded="false" data-tooltip="<?php echo $formatTooltip($infoTexts["weights"] ?? t("combine.info.weights", "Gewichtungen legen fest, wie stark Kategorien und Disziplinen in die Gesamtwertung einfließen.\nKategorien Gewichtung beeinflussen den Einfluss auf den Gesamtscore, Disziplinen Gewichtung die Zusammensetzung des Scores dieser Kategorie.")); ?>">i</button>
              </div>
              <div class="category-block">
                <?php foreach ($selectedDisciplinesByCategory as $category => $categoryDisciplines): ?>
                  <?php $categoryWeight = $formCategoryWeights[$category] ?? 1; ?>
                  <div class="category-block">
                    <h4 class="category-title"><?php echo htmlspecialchars($category, ENT_QUOTES, "UTF-8"); ?></h4>
                    <label class="field">
                      <input type="number" name="category_weight[]" step="1" min="1" value="<?php echo htmlspecialchars($categoryWeight, ENT_QUOTES, "UTF-8"); ?>">
                      <input type="hidden" name="category_name[]" value="<?php echo htmlspecialchars($category, ENT_QUOTES, "UTF-8"); ?>">
                    </label>
                    <?php if (count($categoryDisciplines) > 1): ?>
                      <div class="check-grid">
                        <?php foreach ($categoryDisciplines as $discipline): ?>
                          <?php
                            $discId = (int)$discipline["id"];
                            $weightValue = $formDisciplineWeights[$discId] ?? 1;
                          ?>
                          <label class="check-item">
                            <span>
                              <?php echo htmlspecialchars($discipline["discipline_name"], ENT_QUOTES, "UTF-8"); ?>
                            </span>
                            <input type="number" name="discipline_weight[<?php echo $discId; ?>]" step="1" min="1" value="<?php echo htmlspecialchars($weightValue, ENT_QUOTES, "UTF-8"); ?>">
                          </label>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <div class="form-actions">
            <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("common.save", "Speichern"), ENT_QUOTES, "UTF-8"); ?></button>
            <button class="pill-button is-muted" type="button" onclick="window.location.href='combine.php?id=<?php echo (int)$combineId; ?>'"><?php echo htmlspecialchars(t("common.cancel", "Abbrechen"), ENT_QUOTES, "UTF-8"); ?></button>
          </div>
          <?php if ($combineFeedback): ?>
            <p class="help"><?php echo htmlspecialchars($combineFeedback, ENT_QUOTES, "UTF-8"); ?></p>
          <?php endif; ?>
        </form>
      </section>
