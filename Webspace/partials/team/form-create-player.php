<div class="auth-card is-hidden" id="create-player">
  <h2><?php echo htmlspecialchars(t("team.players.create", "Spieler anlegen"), ENT_QUOTES, "UTF-8"); ?></h2>
  <form class="form" method="post" action="">
    <input type="hidden" name="action" value="create_player">
    <label class="field">
      <span><?php echo htmlspecialchars(t("team.players.first_name", "Vorname"), ENT_QUOTES, "UTF-8"); ?></span>
      <input type="text" name="first_name" required>
    </label>
    <label class="field">
      <span><?php echo htmlspecialchars(t("team.players.last_name", "Nachname"), ENT_QUOTES, "UTF-8"); ?></span>
      <input type="text" name="last_name" required>
    </label>
    <label class="field">
      <span><?php echo htmlspecialchars(t("team.players.jersey_number", "Trikotnummer"), ENT_QUOTES, "UTF-8"); ?></span>
      <input type="number" name="jersey_number" min="0">
    </label>
    <label class="field">
      <span><?php echo htmlspecialchars(t("team.players.gender", "Geschlecht"), ENT_QUOTES, "UTF-8"); ?></span>
      <select name="gender" required>
        <option value=""><?php echo htmlspecialchars(t("common.choose", "Bitte wählen"), ENT_QUOTES, "UTF-8"); ?></option>
        <option value="m"><?php echo htmlspecialchars(t("team.players.gender_m", "Männlich"), ENT_QUOTES, "UTF-8"); ?></option>
        <option value="w"><?php echo htmlspecialchars(t("team.players.gender_w", "Weiblich"), ENT_QUOTES, "UTF-8"); ?></option>
        <option value="d"><?php echo htmlspecialchars(t("team.players.gender_d", "Divers"), ENT_QUOTES, "UTF-8"); ?></option>
      </select>
    </label>
    <div class="field">
      <span><?php echo htmlspecialchars(t("team.players.position", "Position"), ENT_QUOTES, "UTF-8"); ?></span>
      <div class="check-grid">
        <label class="check-item">
          <input type="checkbox" name="positions[]" value="handler">
          <span><?php echo htmlspecialchars(t("team.players.position_handler", "Handler"), ENT_QUOTES, "UTF-8"); ?></span>
        </label>
        <label class="check-item">
          <input type="checkbox" name="positions[]" value="cutter">
          <span><?php echo htmlspecialchars(t("team.players.position_cutter", "Cutter"), ENT_QUOTES, "UTF-8"); ?></span>
        </label>
      </div>
    </div>
    <div class="form-actions">
      <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("team.players.save", "Spieler speichern"), ENT_QUOTES, "UTF-8"); ?></button>
      <button class="pill-button is-muted js-close" type="button" data-target="create-player"><?php echo htmlspecialchars(t("common.cancel", "Abbrechen"), ENT_QUOTES, "UTF-8"); ?></button>
    </div>
    <?php if ($playerFeedback && $action === "create_player"): ?>
      <p class="help"><?php echo htmlspecialchars($playerFeedback, ENT_QUOTES, "UTF-8"); ?></p>
    <?php endif; ?>
  </form>
</div>
