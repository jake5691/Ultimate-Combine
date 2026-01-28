<form class="form" method="post" action="">
  <input type="hidden" name="action" value="update_player">
  <input type="hidden" name="id" value="<?php echo (int)$editRecord["id"]; ?>">
  <label class="field">
    <span><?php echo htmlspecialchars(t("team.players.first_name", "Vorname"), ENT_QUOTES, "UTF-8"); ?></span>
    <input type="text" name="first_name" value="<?php echo htmlspecialchars($editRecord["first_name"], ENT_QUOTES, "UTF-8"); ?>" required>
  </label>
  <label class="field">
    <span><?php echo htmlspecialchars(t("team.players.last_name", "Nachname"), ENT_QUOTES, "UTF-8"); ?></span>
    <input type="text" name="last_name" value="<?php echo htmlspecialchars($editRecord["last_name"], ENT_QUOTES, "UTF-8"); ?>" required>
  </label>
  <label class="field">
    <span><?php echo htmlspecialchars(t("team.players.jersey_number", "Trikotnummer"), ENT_QUOTES, "UTF-8"); ?></span>
    <input type="number" name="jersey_number" min="0" value="<?php echo $editRecord["jersey_number"] !== null ? (int)$editRecord["jersey_number"] : ""; ?>">
  </label>
  <label class="field">
    <span><?php echo htmlspecialchars(t("team.players.gender", "Geschlecht"), ENT_QUOTES, "UTF-8"); ?></span>
    <select name="gender" required>
      <option value=""><?php echo htmlspecialchars(t("common.choose", "Bitte wählen"), ENT_QUOTES, "UTF-8"); ?></option>
      <?php foreach ($validGenders as $key => $label): ?>
        <option value="<?php echo htmlspecialchars($key, ENT_QUOTES, "UTF-8"); ?>"<?php echo $editRecord["gender"] === $key ? " selected" : ""; ?>>
          <?php echo htmlspecialchars($label, ENT_QUOTES, "UTF-8"); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>
  <div class="field">
    <span><?php echo htmlspecialchars(t("team.players.position", "Position"), ENT_QUOTES, "UTF-8"); ?></span>
    <div class="check-grid">
      <label class="check-item">
        <input type="checkbox" name="positions[]" value="handler"<?php echo !empty($editRecord["position_handler"]) ? " checked" : ""; ?>>
        <span><?php echo htmlspecialchars(t("team.players.position_handler", "Handler"), ENT_QUOTES, "UTF-8"); ?></span>
      </label>
      <label class="check-item">
        <input type="checkbox" name="positions[]" value="cutter"<?php echo !empty($editRecord["position_cutter"]) ? " checked" : ""; ?>>
        <span><?php echo htmlspecialchars(t("team.players.position_cutter", "Cutter"), ENT_QUOTES, "UTF-8"); ?></span>
      </label>
    </div>
  </div>
  <div class="form-actions">
    <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("common.save", "Speichern"), ENT_QUOTES, "UTF-8"); ?></button>
    <a class="pill-button is-muted" href="team.php"><?php echo htmlspecialchars(t("common.cancel", "Abbrechen"), ENT_QUOTES, "UTF-8"); ?></a>
    <button class="pill-button is-danger" type="submit" form="delete-player-form"><?php echo htmlspecialchars(t("team.players.delete", "Spieler löschen"), ENT_QUOTES, "UTF-8"); ?></button>
  </div>
  <?php if ($playerFeedback && $editType === "player"): ?>
    <p class="help"><?php echo htmlspecialchars($playerFeedback, ENT_QUOTES, "UTF-8"); ?></p>
  <?php endif; ?>
</form>
<form id="delete-player-form" method="post" action="" onsubmit="return confirm('<?php echo htmlspecialchars(t("team.confirm.delete_player", "Spieler wirklich löschen? Alle zugehörigen Ergebnisse werden entfernt."), ENT_QUOTES, "UTF-8"); ?>') && confirm('<?php echo htmlspecialchars(t("team.confirm.delete_player_final", "Letzte Warnung: Dieser Vorgang kann nicht rückgängig gemacht werden. Wirklich löschen?"), ENT_QUOTES, "UTF-8"); ?>');">
  <input type="hidden" name="action" value="delete_player">
  <input type="hidden" name="id" value="<?php echo (int)$editRecord["id"]; ?>">
</form>
