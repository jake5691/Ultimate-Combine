<?php
$topbarClass = $topbarClass ?? "topbar";
$brandText = $brandText ?? "Ultimate Combine";
$brandSuffix = $brandSuffix ?? "";
$showBack = $showBack ?? false;
$backLabel = $backLabel ?? t("common.back", "Zurück");
$backOnclick = $backOnclick ?? "";
$showLogout = $showLogout ?? false;
$themeLabels = $themeLabels ?? true;
$themeToggleText = $themeToggleText ?? t("common.theme_auto", "Auto");
?>
<header class="<?php echo htmlspecialchars($topbarClass, ENT_QUOTES, "UTF-8"); ?>">
  <?php if ($showBack): ?>
    <button class="pill-button" type="button" onclick="<?php echo htmlspecialchars($backOnclick, ENT_QUOTES, "UTF-8"); ?>"><?php echo htmlspecialchars($backLabel, ENT_QUOTES, "UTF-8"); ?></button>
  <?php endif; ?>
  <div class="brand">
    <img class="brand-logo" src="assets/FrisbeeCatch.png" alt="Ultimate Combine">
    <span class="brand-text"><?php echo htmlspecialchars($brandText, ENT_QUOTES, "UTF-8"); ?></span>
    <?php if ($brandSuffix !== ""): ?>
      <span class="brand-sep">•</span>
      <span class="brand-team"><?php echo htmlspecialchars($brandSuffix, ENT_QUOTES, "UTF-8"); ?></span>
    <?php endif; ?>
  </div>
  <?php require __DIR__ . "/menu.php"; ?>
</header>
