<div class="auth-card is-hidden" id="create-combine">
  <h2><?php echo htmlspecialchars(t("team.combines.create", "Combine anlegen"), ENT_QUOTES, "UTF-8"); ?></h2>
  <form class="form" method="post" action="">
    <input type="hidden" name="action" value="create_combine">
    <label class="field">
      <span><?php echo htmlspecialchars(t("common.name", "Name"), ENT_QUOTES, "UTF-8"); ?></span>
      <input type="text" name="combine_name" required>
    </label>
    <label class="field">
      <span><?php echo htmlspecialchars(t("common.date", "Datum"), ENT_QUOTES, "UTF-8"); ?></span>
      <input type="date" name="event_date" required>
    </label>
    <label class="field">
      <span><?php echo htmlspecialchars(t("common.location", "Ort"), ENT_QUOTES, "UTF-8"); ?></span>
      <input type="text" name="combine_location" placeholder="<?php echo htmlspecialchars(t("team.combines.location_placeholder", "z. B. Sportplatz Nord"), ENT_QUOTES, "UTF-8"); ?>">
    </label>
    <label class="field">
      <span><?php echo htmlspecialchars(t("common.notes", "Notizen"), ENT_QUOTES, "UTF-8"); ?></span>
      <textarea name="combine_notes" rows="3" placeholder="<?php echo htmlspecialchars(t("common.optional", "Optional"), ENT_QUOTES, "UTF-8"); ?>"></textarea>
    </label>
    <div class="form-actions">
      <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("team.combines.save", "Combine speichern"), ENT_QUOTES, "UTF-8"); ?></button>
      <button class="pill-button is-muted js-close" type="button" data-target="create-combine"><?php echo htmlspecialchars(t("common.cancel", "Abbrechen"), ENT_QUOTES, "UTF-8"); ?></button>
    </div>
    <?php if ($combineFeedback && $action === "create_combine"): ?>
      <p class="help"><?php echo htmlspecialchars($combineFeedback, ENT_QUOTES, "UTF-8"); ?></p>
    <?php endif; ?>
  </form>
</div>
