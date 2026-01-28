<?php
$brandText = $brandText ?? t("site.title", "Ultimate Combine");
$showSpacer = $showSpacer ?? true;
$showLogout = $showLogout ?? false;
$themeLabels = $themeLabels ?? true;
$themeToggleText = $themeToggleText ?? t("common.theme_auto", "Auto");
?>
<header class="topbar is-simple">
  <?php if ($showSpacer): ?>
    <span class="topbar-spacer"></span>
  <?php endif; ?>
  <div class="brand">
    <img class="brand-logo" src="assets/FrisbeeCatch.png" alt="Ultimate Combine">
    <span class="brand-text"><?php echo htmlspecialchars($brandText, ENT_QUOTES, "UTF-8"); ?></span>
  </div>
  <?php require __DIR__ . "/menu.php"; ?>
</header>
