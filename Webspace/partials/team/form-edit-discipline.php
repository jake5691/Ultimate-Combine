<form class="form" method="post" action="">
  <input type="hidden" name="action" value="<?php echo $cloneSourceId ? "create_discipline" : "update_discipline"; ?>">
  <?php if (!$cloneSourceId): ?>
    <input type="hidden" name="id" value="<?php echo (int)$editRecord["id"]; ?>">
  <?php endif; ?>
  <label class="field">
    <span><?php echo htmlspecialchars(t("common.name", "Name"), ENT_QUOTES, "UTF-8"); ?></span>
    <input type="text" name="discipline_name" value="<?php echo htmlspecialchars($editRecord["discipline_name"], ENT_QUOTES, "UTF-8"); ?>" required>
  </label>
  <label class="field">
    <span><?php echo htmlspecialchars(t("common.description", "Beschreibung"), ENT_QUOTES, "UTF-8"); ?></span>
    <textarea name="description" rows="3" required><?php echo htmlspecialchars($editRecord["description"], ENT_QUOTES, "UTF-8"); ?></textarea>
  </label>
  <label class="field">
    <span><?php echo htmlspecialchars(t("common.unit", "Einheit"), ENT_QUOTES, "UTF-8"); ?></span>
    <input type="text" name="unit" list="unit-options" value="<?php echo htmlspecialchars($editRecord["unit"], ENT_QUOTES, "UTF-8"); ?>" data-unit-name required>
  </label>
  <label class="field">
    <span><?php echo htmlspecialchars(t("common.unit_abbr", "Einheit (Abkürzung)"), ENT_QUOTES, "UTF-8"); ?></span>
    <input type="text" name="unit_abbreviation" value="<?php echo htmlspecialchars($unitNameToAbbr[$editRecord["unit"] ?? ""] ?? "", ENT_QUOTES, "UTF-8"); ?>" data-unit-abbr required>
  </label>
  <label class="field">
    <span><?php echo htmlspecialchars(t("common.category", "Kategorie"), ENT_QUOTES, "UTF-8"); ?></span>
    <input type="text" name="category" list="discipline-categories" value="<?php echo htmlspecialchars($editRecord["category"], ENT_QUOTES, "UTF-8"); ?>" required>
  </label>
  <label class="field">
    <span><?php echo htmlspecialchars(t("common.rating", "Bewertung"), ENT_QUOTES, "UTF-8"); ?></span>
    <select name="rating_direction" required>
      <option value=""><?php echo htmlspecialchars(t("common.choose", "Bitte wählen"), ENT_QUOTES, "UTF-8"); ?></option>
      <?php foreach ($validDirections as $key => $label): ?>
        <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, "UTF-8"); ?>"<?php echo $editRecord["rating_direction"] === $key ? " selected" : ""; ?>>
          <?php echo htmlspecialchars($label, ENT_QUOTES, "UTF-8"); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>
  <label class="field">
    <span><?php echo htmlspecialchars(t("team.disciplines.expected_worst", "Erwartung Schlechtester (1 Punkt)"), ENT_QUOTES, "UTF-8"); ?></span>
    <input type="number" name="expected_min" step="any" value="<?php echo htmlspecialchars($editRecord["expected_min"] ?? "", ENT_QUOTES, "UTF-8"); ?>" placeholder="<?php echo htmlspecialchars(t("common.optional", "Optional"), ENT_QUOTES, "UTF-8"); ?>">
  </label>
  <label class="field">
    <span><?php echo htmlspecialchars(t("team.disciplines.expected_best", "Erwartung Bester (2 Punkte)"), ENT_QUOTES, "UTF-8"); ?></span>
    <input type="number" name="expected_max" step="any" value="<?php echo htmlspecialchars($editRecord["expected_max"] ?? "", ENT_QUOTES, "UTF-8"); ?>" placeholder="<?php echo htmlspecialchars(t("common.optional", "Optional"), ENT_QUOTES, "UTF-8"); ?>">
  </label>
  <label class="field">
    <span><?php echo htmlspecialchars(t("common.bonus_relative", "Bonus Platz 1 (Relativ)"), ENT_QUOTES, "UTF-8"); ?></span>
    <input type="number" name="bonus_relative" step="any" value="<?php echo htmlspecialchars($editRecord["bonus_relative"] ?? "", ENT_QUOTES, "UTF-8"); ?>" placeholder="<?php echo htmlspecialchars(t("common.optional", "Optional"), ENT_QUOTES, "UTF-8"); ?>">
  </label>
  <label class="field">
    <span><?php echo htmlspecialchars(t("common.bonus_absolute", "Bonus Bestwert (Absolut)"), ENT_QUOTES, "UTF-8"); ?></span>
    <input type="number" name="bonus_absolute" step="any" value="<?php echo htmlspecialchars($editRecord["bonus_absolute"] ?? "", ENT_QUOTES, "UTF-8"); ?>" placeholder="<?php echo htmlspecialchars(t("common.optional", "Optional"), ENT_QUOTES, "UTF-8"); ?>">
  </label>
  <div class="form-actions">
    <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("common.save", "Speichern"), ENT_QUOTES, "UTF-8"); ?></button>
    <a class="pill-button is-muted" href="team.php"><?php echo htmlspecialchars(t("common.cancel", "Abbrechen"), ENT_QUOTES, "UTF-8"); ?></a>
    <?php if (!$cloneSourceId): ?>
      <button class="pill-button is-danger" type="submit" form="delete-discipline-form"><?php echo htmlspecialchars(t("team.disciplines.delete", "Disziplin löschen"), ENT_QUOTES, "UTF-8"); ?></button>
    <?php endif; ?>
  </div>
  <?php if ($disciplineFeedback && $editType === "discipline"): ?>
    <p class="help"><?php echo htmlspecialchars($disciplineFeedback, ENT_QUOTES, "UTF-8"); ?></p>
  <?php endif; ?>
</form>
<?php if (!$cloneSourceId): ?>
  <form id="delete-discipline-form" method="post" action="" onsubmit="return confirm('<?php echo htmlspecialchars(t("team.confirm.delete_discipline", "Disziplin wirklich löschen? Alle zugehörigen Ergebnisse werden entfernt."), ENT_QUOTES, "UTF-8"); ?>') && confirm('<?php echo htmlspecialchars(t("team.confirm.delete_discipline_final", "Letzte Warnung: Dieser Vorgang kann nicht rückgängig gemacht werden. Wirklich löschen?"), ENT_QUOTES, "UTF-8"); ?>');">
    <input type="hidden" name="action" value="delete_discipline">
    <input type="hidden" name="id" value="<?php echo (int)$editRecord["id"]; ?>">
  </form>
<?php endif; ?>
