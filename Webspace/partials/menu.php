<?php
$showLogout = $showLogout ?? false;
$themeLabels = $themeLabels ?? true;
$themeToggleText = $themeToggleText ?? t("common.theme_auto", "Auto");
?>
<div class="topbar-actions">
  <details class="header-menu">
    <summary class="pill-button is-muted" aria-label="<?php echo htmlspecialchars(t("common.menu", "Menü"), ENT_QUOTES, "UTF-8"); ?>">☰</summary>
    <div class="menu-panel">
      <div class="menu-item">
        <span class="menu-label"><?php echo htmlspecialchars(t("common.theme", "Design"), ENT_QUOTES, "UTF-8"); ?></span>
        <button
          class="pill-button is-muted theme-toggle"
          type="button"
          data-theme-toggle
          <?php if ($themeLabels): ?>
            data-theme-label-system="<?php echo htmlspecialchars(t("common.theme_auto", "Auto"), ENT_QUOTES, "UTF-8"); ?>"
            data-theme-label-dark="<?php echo htmlspecialchars(t("common.theme_dark", "Dunkel"), ENT_QUOTES, "UTF-8"); ?>"
            data-theme-label-light="<?php echo htmlspecialchars(t("common.theme_light", "Hell"), ENT_QUOTES, "UTF-8"); ?>"
          <?php endif; ?>
          aria-pressed="false"
        ><?php echo htmlspecialchars($themeToggleText, ENT_QUOTES, "UTF-8"); ?></button>
      </div>
      <div class="menu-item">
        <span class="menu-label"><?php echo htmlspecialchars(t("common.language", "Sprache"), ENT_QUOTES, "UTF-8"); ?></span>
        <div class="menu-links">
          <a class="pill-button is-muted<?php echo $lang === "de" ? " is-active" : ""; ?>" href="<?php echo htmlspecialchars(uc_lang_url("de"), ENT_QUOTES, "UTF-8"); ?>">DE</a>
          <a class="pill-button is-muted<?php echo $lang === "en" ? " is-active" : ""; ?>" href="<?php echo htmlspecialchars(uc_lang_url("en"), ENT_QUOTES, "UTF-8"); ?>">EN</a>
        </div>
      </div>
      <?php if ($showLogout): ?>
        <div class="menu-item">
          <form method="post" action="">
            <input type="hidden" name="action" value="logout">
            <button class="pill-button is-logout" type="submit"><?php echo htmlspecialchars(t("common.logout", "Abmelden"), ENT_QUOTES, "UTF-8"); ?></button>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </details>
</div>
