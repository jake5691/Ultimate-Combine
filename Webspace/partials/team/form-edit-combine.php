<form class="form" method="post" action="">
  <input type="hidden" name="action" value="update_combine">
  <input type="hidden" name="id" value="<?php echo (int)$editRecord["id"]; ?>">
  <label class="field">
    <span><?php echo htmlspecialchars(t("common.name", "Name"), ENT_QUOTES, "UTF-8"); ?></span>
    <input type="text" name="combine_name" value="<?php echo htmlspecialchars($editRecord["combine_name"], ENT_QUOTES, "UTF-8"); ?>" required>
  </label>
  <label class="field">
    <span><?php echo htmlspecialchars(t("common.date", "Datum"), ENT_QUOTES, "UTF-8"); ?></span>
    <input type="date" name="event_date" value="<?php echo htmlspecialchars($editRecord["event_date"], ENT_QUOTES, "UTF-8"); ?>" required>
  </label>
  <label class="field">
    <span><?php echo htmlspecialchars(t("common.location", "Ort"), ENT_QUOTES, "UTF-8"); ?></span>
    <input type="text" name="combine_location" value="<?php echo htmlspecialchars($editRecord["combine_location"] ?? "", ENT_QUOTES, "UTF-8"); ?>">
  </label>
  <label class="field">
    <span><?php echo htmlspecialchars(t("common.notes", "Notizen"), ENT_QUOTES, "UTF-8"); ?></span>
    <textarea name="combine_notes" rows="3"><?php echo htmlspecialchars($editRecord["combine_notes"] ?? "", ENT_QUOTES, "UTF-8"); ?></textarea>
  </label>
  <div class="form-actions">
    <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("common.save", "Speichern"), ENT_QUOTES, "UTF-8"); ?></button>
    <a class="pill-button is-muted" href="team.php"><?php echo htmlspecialchars(t("common.cancel", "Abbrechen"), ENT_QUOTES, "UTF-8"); ?></a>
  </div>
  <?php if ($combineFeedback && $editType === "combine"): ?>
    <p class="help"><?php echo htmlspecialchars($combineFeedback, ENT_QUOTES, "UTF-8"); ?></p>
  <?php endif; ?>
</form>
