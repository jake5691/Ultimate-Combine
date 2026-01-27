<?php
require_once __DIR__ . "/bootstrap.php";

$token = trim($_GET["token"] ?? "");
$tokenHash = $token !== "" ? hash("sha256", $token) : "";
$error = null;
$success = null;
$teamId = null;

if ($tokenHash === "") {
  $error = t("reset.error.token_missing", "Token fehlt oder ist ungültig.");
} elseif (!$pdo) {
  $error = $dbError ?? t("error.db_unreachable", "Datenbank ist nicht erreichbar.");
} else {
  $stmt = $pdo->prepare(
    "SELECT id, team_id, expires_at, used_at
     FROM password_resets
     WHERE token_hash = :token_hash
     LIMIT 1"
  );
  $stmt->execute([":token_hash" => $tokenHash]);
  $resetRow = $stmt->fetch();

  if (!$resetRow) {
    $error = t("reset.error.token_invalid", "Token ist ungültig.");
  } elseif (!empty($resetRow["used_at"])) {
    $error = t("reset.error.token_used", "Token wurde bereits verwendet.");
  } elseif (strtotime($resetRow["expires_at"]) < time()) {
    $error = t("reset.error.token_expired", "Token ist abgelaufen.");
  } else {
    $teamId = (int)$resetRow["team_id"];
  }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $teamId) {
  $newKey = trim($_POST["team_key_new"] ?? "");
  $newKeyRepeat = trim($_POST["team_key_repeat"] ?? "");
  if ($newKey === "" || $newKeyRepeat === "") {
    $error = t("reset.error.required", "Bitte beide Felder ausfüllen.");
  } elseif ($newKey !== $newKeyRepeat) {
    $error = t("reset.error.mismatch", "Die Passwörter stimmen nicht überein.");
  } else {
    $newKeyHash = password_hash($newKey, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
      "UPDATE teams
       SET team_key_hash = :team_key_hash
       WHERE id = :id"
    );
    $stmt->execute([
      ":team_key_hash" => $newKeyHash,
      ":id" => $teamId,
    ]);
    $stmt = $pdo->prepare(
      "UPDATE password_resets
       SET used_at = NOW()
       WHERE token_hash = :token_hash"
    );
    $stmt->execute([":token_hash" => $tokenHash]);
    $success = t("reset.success", "Passwort wurde aktualisiert. Du kannst dich jetzt einloggen.");
    $teamId = null;
  }
}
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($lang, ENT_QUOTES, "UTF-8"); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars(t("reset.title", "Ultimate Combine – Neues Passwort"), ENT_QUOTES, "UTF-8"); ?></title>
  <link rel="icon" href="assets/favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="assets/apple-touch-icon.png">
  <link rel="manifest" href="assets/site.webmanifest">
  <link rel="stylesheet" href="ui.css">
</head>
<body>
  <div class="bg-grid"></div>

  <header class="topbar is-simple">
    <span class="topbar-spacer"></span>
    <div class="brand">
      <img class="brand-logo" src="assets/FrisbeeCatch.png" alt="Ultimate Combine">
      <span class="brand-text"><?php echo htmlspecialchars(t("site.title", "Ultimate Combine"), ENT_QUOTES, "UTF-8"); ?></span>
    </div>
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
              data-theme-label-system="<?php echo htmlspecialchars(t("common.theme_auto", "Auto"), ENT_QUOTES, "UTF-8"); ?>"
              data-theme-label-dark="<?php echo htmlspecialchars(t("common.theme_dark", "Dunkel"), ENT_QUOTES, "UTF-8"); ?>"
              data-theme-label-light="<?php echo htmlspecialchars(t("common.theme_light", "Hell"), ENT_QUOTES, "UTF-8"); ?>"
              aria-pressed="false"
            ><?php echo htmlspecialchars(t("common.theme_auto", "Auto"), ENT_QUOTES, "UTF-8"); ?></button>
          </div>
          <div class="menu-item">
            <span class="menu-label"><?php echo htmlspecialchars(t("common.language", "Sprache"), ENT_QUOTES, "UTF-8"); ?></span>
            <div class="menu-links">
              <a class="pill-button is-muted<?php echo $lang === "de" ? " is-active" : ""; ?>" href="<?php echo htmlspecialchars(uc_lang_url("de"), ENT_QUOTES, "UTF-8"); ?>">DE</a>
              <a class="pill-button is-muted<?php echo $lang === "en" ? " is-active" : ""; ?>" href="<?php echo htmlspecialchars(uc_lang_url("en"), ENT_QUOTES, "UTF-8"); ?>">EN</a>
            </div>
          </div>
        </div>
      </details>
    </div>
  </header>

  <main class="auth is-wide">
    <section class="auth-card">
      <h1><?php echo htmlspecialchars(t("reset.heading", "Neues Passwort setzen"), ENT_QUOTES, "UTF-8"); ?></h1>
      <?php if ($success): ?>
        <p class="help"><?php echo htmlspecialchars($success, ENT_QUOTES, "UTF-8"); ?></p>
        <a class="pill-button" href="index.php"><?php echo htmlspecialchars(t("reset.back_to_login", "Zum Login"), ENT_QUOTES, "UTF-8"); ?></a>
      <?php else: ?>
        <?php if ($error): ?>
          <p class="help"><?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?></p>
        <?php endif; ?>
        <?php if ($teamId): ?>
          <form class="form" method="post" action="">
            <label class="field">
              <span><?php echo htmlspecialchars(t("reset.field.new_password", "Neues Passwort"), ENT_QUOTES, "UTF-8"); ?></span>
              <input type="password" name="team_key_new" required>
            </label>
            <label class="field">
              <span><?php echo htmlspecialchars(t("reset.field.repeat_password", "Passwort wiederholen"), ENT_QUOTES, "UTF-8"); ?></span>
              <input type="password" name="team_key_repeat" required>
            </label>
            <div class="form-actions">
              <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("reset.submit", "Passwort speichern"), ENT_QUOTES, "UTF-8"); ?></button>
              <a class="pill-button is-muted" href="index.php"><?php echo htmlspecialchars(t("common.cancel", "Abbrechen"), ENT_QUOTES, "UTF-8"); ?></a>
            </div>
          </form>
        <?php else: ?>
          <a class="pill-button" href="reset-request.php"><?php echo htmlspecialchars(t("reset.request_new", "Neuen Link anfordern"), ENT_QUOTES, "UTF-8"); ?></a>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </main>

  <footer class="site-footer">
    <a class="footer-link" href="impressum.php"><?php echo htmlspecialchars(t("footer.impressum", "Impressum"), ENT_QUOTES, "UTF-8"); ?></a>
    <a class="footer-link" href="feedback.php"><?php echo htmlspecialchars(t("footer.feedback", "Feedback"), ENT_QUOTES, "UTF-8"); ?></a>
    <script type="text/javascript" src="https://cdnjs.buymeacoffee.com/1.0.0/button.prod.min.js" data-name="bmc-button" data-slug="jakob.christen" data-color="#ff7b4b" data-emoji="☕" data-font="Inter" data-text="Buy me a coffee" data-outline-color="#000000" data-font-color="#000000" data-coffee-color="#FFDD00"></script>
  </footer>
  <script src="theme.js"></script>
</body>
</html>
