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
    $feedbackError = "Bitte alle Felder ausfüllen.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $feedbackError = "Bitte eine gültige E-Mail-Adresse angeben.";
  } elseif (!$pdo) {
    $feedbackError = $dbError ?? "Feedback konnte nicht gespeichert werden.";
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
      $feedbackMessage = "Danke für dein Feedback! Ich melden mich bei Bedarf.";
      $name = "";
      $email = "";
      $subject = "";
      $message = "";
    } catch (Throwable $e) {
      $feedbackError = "Feedback konnte nicht gespeichert werden.";
    }
  }
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ultimate Combine – Feedback</title>
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
    <button class="pill-button" type="button" onclick="history.back()">Zurück</button>
    <div class="brand">
      <img class="brand-logo" src="assets/FrisbeeCatch.png" alt="Ultimate Combine">
      <span class="brand-text">Ultimate Combine</span>
      <span class="brand-sep">•</span>
      <span class="brand-team">Feedback</span>
    </div>
    <div class="topbar-actions">
      <button class="pill-button is-muted theme-toggle" type="button" data-theme-toggle aria-pressed="false">System</button>
    </div>
  </header>

  <main class="team">
    <section class="auth-card">
      <h1>Feedback</h1>
      <p class="lead">Teile mir gern deine Wünsche, Bugs oder Ideen mit.</p>
      <?php if ($feedbackMessage): ?>
        <p class="help"><?php echo htmlspecialchars($feedbackMessage, ENT_QUOTES, "UTF-8"); ?></p>
      <?php endif; ?>
      <?php if ($feedbackError): ?>
        <p class="help"><?php echo htmlspecialchars($feedbackError, ENT_QUOTES, "UTF-8"); ?></p>
      <?php endif; ?>
      <form class="form" method="post" action="">
        <label class="field">
          <span>Name</span>
          <input type="text" name="name" value="<?php echo htmlspecialchars($name, ENT_QUOTES, "UTF-8"); ?>" required>
        </label>
        <label class="field">
          <span>E-Mail</span>
          <input type="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, "UTF-8"); ?>" required>
        </label>
        <label class="field">
          <span>Betreff</span>
          <input type="text" name="subject" value="<?php echo htmlspecialchars($subject, ENT_QUOTES, "UTF-8"); ?>" required>
        </label>
        <label class="field">
          <span>Feedback</span>
          <textarea name="message" rows="5" required><?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?></textarea>
        </label>
        <button class="primary-button" type="submit">Feedback senden</button>
      </form>
    </section>
  </main>

  <footer class="site-footer">
    <a class="footer-link" href="impressum.php">Impressum</a>
    <a class="footer-link" href="feedback.php">Feedback</a>
    <script type="text/javascript" src="https://cdnjs.buymeacoffee.com/1.0.0/button.prod.min.js" data-name="bmc-button" data-slug="jakob.christen" data-color="#ff7b4b" data-emoji="☕" data-font="Inter" data-text="Buy me a coffee" data-outline-color="#000000" data-font-color="#000000" data-coffee-color="#FFDD00"></script>
  </footer>
  <script src="theme.js"></script>
</body>
</html>
