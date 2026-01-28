<section class="auth-card is-hidden" id="create-discipline">
  <h2><?php echo htmlspecialchars(t("team.disciplines.create", "Disziplin anlegen"), ENT_QUOTES, "UTF-8"); ?></h2>
  <form class="form" method="post" action="">
    <input type="hidden" name="action" value="create_discipline">
    <label class="field">
      <span><?php echo htmlspecialchars(t("common.name", "Name"), ENT_QUOTES, "UTF-8"); ?></span>
      <input type="text" name="discipline_name" required>
    </label>
    <label class="field">
      <span><?php echo htmlspecialchars(t("common.description", "Beschreibung"), ENT_QUOTES, "UTF-8"); ?></span>
      <textarea name="description" rows="3" required></textarea>
    </label>
    <label class="field">
      <span><?php echo htmlspecialchars(t("common.unit", "Einheit"), ENT_QUOTES, "UTF-8"); ?></span>
      <input type="text" name="unit" list="unit-options" data-unit-name required>
    </label>
    <label class="field">
      <span><?php echo htmlspecialchars(t("common.unit_abbr", "Einheit (Abkürzung)"), ENT_QUOTES, "UTF-8"); ?></span>
      <input type="text" name="unit_abbreviation" data-unit-abbr required>
    </label>
    <label class="field">
      <span><?php echo htmlspecialchars(t("common.category", "Kategorie"), ENT_QUOTES, "UTF-8"); ?></span>
      <input type="text" name="category" list="discipline-categories" required>
    </label>
    <datalist id="discipline-categories">
      <?php foreach ($disciplineCategories as $category): ?>
        <option value="<?php echo htmlspecialchars($category, ENT_QUOTES, "UTF-8"); ?>"></option>
      <?php endforeach; ?>
    </datalist>
    <datalist id="unit-options">
      <?php foreach ($units as $unit): ?>
        <?php
          $unitName = trim((string)($unit["unit_name"] ?? ""));
          $unitAbbr = trim((string)($unit["unit_abbreviation"] ?? ""));
          $unitLabel = $unitName;
          if ($unitAbbr !== "") {
            $unitLabel .= " (" . $unitAbbr . ")";
          }
        ?>
        <option value="<?php echo htmlspecialchars($unitName, ENT_QUOTES, "UTF-8"); ?>" data-abbr="<?php echo htmlspecialchars($unitAbbr, ENT_QUOTES, "UTF-8"); ?>">
          <?php echo htmlspecialchars($unitLabel, ENT_QUOTES, "UTF-8"); ?>
        </option>
      <?php endforeach; ?>
    </datalist>
    <label class="field">
      <span><?php echo htmlspecialchars(t("common.rating", "Bewertung"), ENT_QUOTES, "UTF-8"); ?></span>
      <select name="rating_direction" required>
        <option value=""><?php echo htmlspecialchars(t("common.choose", "Bitte wählen"), ENT_QUOTES, "UTF-8"); ?></option>
        <option value="more"><?php echo htmlspecialchars(t("common.more_is_better", "Mehr ist besser"), ENT_QUOTES, "UTF-8"); ?></option>
        <option value="less"><?php echo htmlspecialchars(t("common.less_is_better", "Weniger ist besser"), ENT_QUOTES, "UTF-8"); ?></option>
      </select>
    </label>
    <label class="field">
      <span><?php echo htmlspecialchars(t("team.disciplines.expected_worst", "Erwartung Schlechtester (1 Punkt)"), ENT_QUOTES, "UTF-8"); ?></span>
      <input type="number" name="expected_min" step="any" placeholder="<?php echo htmlspecialchars(t("common.optional", "Optional"), ENT_QUOTES, "UTF-8"); ?>">
    </label>
    <label class="field">
      <span><?php echo htmlspecialchars(t("team.disciplines.expected_best", "Erwartung Bester (2 Punkte)"), ENT_QUOTES, "UTF-8"); ?></span>
      <input type="number" name="expected_max" step="any" placeholder="<?php echo htmlspecialchars(t("common.optional", "Optional"), ENT_QUOTES, "UTF-8"); ?>">
    </label>
    <label class="field">
      <span><?php echo htmlspecialchars(t("common.bonus_relative", "Bonus Platz 1 (Relativ)"), ENT_QUOTES, "UTF-8"); ?></span>
      <input type="number" name="bonus_relative" step="any" placeholder="<?php echo htmlspecialchars(t("common.optional", "Optional"), ENT_QUOTES, "UTF-8"); ?>">
    </label>
    <label class="field">
      <span><?php echo htmlspecialchars(t("common.bonus_absolute", "Bonus Bestwert (Absolut)"), ENT_QUOTES, "UTF-8"); ?></span>
      <input type="number" name="bonus_absolute" step="any" placeholder="<?php echo htmlspecialchars(t("common.optional", "Optional"), ENT_QUOTES, "UTF-8"); ?>">
    </label>
    <div class="form-actions">
      <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("team.disciplines.save", "Disziplin speichern"), ENT_QUOTES, "UTF-8"); ?></button>
      <button class="pill-button is-muted js-close" type="button" data-target="create-discipline"><?php echo htmlspecialchars(t("common.cancel", "Abbrechen"), ENT_QUOTES, "UTF-8"); ?></button>
    </div>
    <?php if ($disciplineFeedback && $action === "create_discipline"): ?>
      <p class="help"><?php echo htmlspecialchars($disciplineFeedback, ENT_QUOTES, "UTF-8"); ?></p>
    <?php endif; ?>
  </form>
</section>
