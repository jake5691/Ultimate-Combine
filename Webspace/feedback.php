<?php
require_once __DIR__ . "/bootstrap.php";

$feedbackMessage = null;
$feedbackError = null;
$name = trim($_POST["name"] ?? "");
$email = trim($_POST["email"] ?? "");
$subject = trim($_POST["subject"] ?? "");
$message = trim($_POST["message"] ?? "");
$teamId = $_SESSION["team_id"] ?? null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if ($name === "" || $email === "" || $subject === "" || $message === "") {
    $feedbackError = t("feedback.error.required", "Bitte alle Felder ausfüllen.");
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $feedbackError = t("common.error.email_invalid", "Bitte eine gültige E-Mail-Adresse angeben.");
  } elseif (!$pdo) {
    $feedbackError = $dbError ?? t("feedback.error.save_failed", "Feedback konnte nicht gespeichert werden.");
  } else {
    try {
      $stmt = $pdo->prepare(
        "INSERT INTO feedback (team_id, sender_name, sender_email, subject, message, status)
         VALUES (:team_id, :sender_name, :sender_email, :subject, :message, :status)"
      );
      $stmt->execute([
        ":team_id" => $teamId,
        ":sender_name" => $name,
        ":sender_email" => $email,
        ":subject" => $subject,
        ":message" => $message,
        ":status" => "Neu",
      ]);
      $feedbackMessage = t("feedback.success", "Danke für dein Feedback! Wir melden uns bei Bedarf.");
      $name = "";
      $email = "";
      $subject = "";
      $message = "";
    } catch (Throwable $e) {
      $feedbackError = t("feedback.error.save_failed", "Feedback konnte nicht gespeichert werden.");
    }
  }
}
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($lang, ENT_QUOTES, "UTF-8"); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars(t("feedback.title", "Ultimate Combine – Feedback"), ENT_QUOTES, "UTF-8"); ?></title>
  <link rel="icon" href="assets/favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="assets/apple-touch-icon.png">
  <link rel="manifest" href="assets/site.webmanifest">
  <link rel="stylesheet" href="ui.css">
</head>
<body>
  <div class="bg-grid"></div>

  <header class="topbar">
    <button class="pill-button" type="button" onclick="history.back()"><?php echo htmlspecialchars(t("common.back", "Zurück"), ENT_QUOTES, "UTF-8"); ?></button>
    <div class="brand">
      <img class="brand-logo" src="assets/FrisbeeCatch.png" alt="Ultimate Combine">
      <span class="brand-text"><?php echo htmlspecialchars(t("site.title", "Ultimate Combine"), ENT_QUOTES, "UTF-8"); ?></span>
      <span class="brand-sep">•</span>
      <span class="brand-team"><?php echo htmlspecialchars(t("feedback.brand", "Feedback"), ENT_QUOTES, "UTF-8"); ?></span>
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

  <main class="team">
    <section class="auth-card">
      <h1><?php echo htmlspecialchars(t("feedback.heading", "Feedback"), ENT_QUOTES, "UTF-8"); ?></h1>
      <p class="lead"><?php echo htmlspecialchars(t("feedback.lead", "Teile mir gern deine Wünsche, Bugs oder Ideen mit."), ENT_QUOTES, "UTF-8"); ?></p>
      <?php if ($feedbackMessage): ?>
        <p class="help"><?php echo htmlspecialchars($feedbackMessage, ENT_QUOTES, "UTF-8"); ?></p>
      <?php endif; ?>
      <?php if ($feedbackError): ?>
        <p class="help"><?php echo htmlspecialchars($feedbackError, ENT_QUOTES, "UTF-8"); ?></p>
      <?php endif; ?>
      <form class="form" method="post" action="">
        <label class="field">
          <span><?php echo htmlspecialchars(t("feedback.field.name", "Name"), ENT_QUOTES, "UTF-8"); ?></span>
          <input type="text" name="name" value="<?php echo htmlspecialchars($name, ENT_QUOTES, "UTF-8"); ?>" required>
        </label>
        <label class="field">
          <span><?php echo htmlspecialchars(t("feedback.field.email", "E-Mail"), ENT_QUOTES, "UTF-8"); ?></span>
          <input type="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, "UTF-8"); ?>" required>
        </label>
        <label class="field">
          <span><?php echo htmlspecialchars(t("feedback.field.subject", "Betreff"), ENT_QUOTES, "UTF-8"); ?></span>
          <input type="text" name="subject" value="<?php echo htmlspecialchars($subject, ENT_QUOTES, "UTF-8"); ?>" required>
        </label>
        <label class="field">
          <span><?php echo htmlspecialchars(t("feedback.field.message", "Feedback"), ENT_QUOTES, "UTF-8"); ?></span>
          <textarea name="message" rows="5" required><?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?></textarea>
        </label>
        <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("feedback.submit", "Feedback senden"), ENT_QUOTES, "UTF-8"); ?></button>
      </form>
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
