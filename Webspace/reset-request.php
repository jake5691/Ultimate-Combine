<?php
require_once __DIR__ . "/bootstrap.php";

$feedback = null;
$error = null;
$teamName = trim($_POST["team_name"] ?? "");
$contactEmail = trim($_POST["contact_email"] ?? "");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if ($teamName === "" || $contactEmail === "") {
    $error = "Bitte Teamname und Kontakt-E-Mail angeben.";
  } elseif (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
    $error = "Bitte eine gültige E-Mail-Adresse angeben.";
  } elseif (!$pdo) {
    $error = $dbError ?? "Datenbank ist nicht erreichbar.";
  } else {
    $stmt = $pdo->prepare(
      "SELECT id, contact
       FROM teams
       WHERE team_name = :team_name
       LIMIT 1"
    );
    $stmt->execute([":team_name" => $teamName]);
    $team = $stmt->fetch();

    if ($team && !empty($team["contact"]) && strtolower(trim((string)$team["contact"])) === strtolower($contactEmail)) {
      $token = bin2hex(random_bytes(32));
      $tokenHash = hash("sha256", $token);
      $expiresAt = date("Y-m-d H:i:s", time() + 3600);

      $stmt = $pdo->prepare(
        "INSERT INTO password_resets (team_id, token_hash, expires_at)
         VALUES (:team_id, :token_hash, :expires_at)"
      );
      $stmt->execute([
        ":team_id" => (int)$team["id"],
        ":token_hash" => $tokenHash,
        ":expires_at" => $expiresAt,
      ]);

      $baseUrl = uc_base_url($env);
      $resetLink = ($baseUrl !== "" ? $baseUrl : "") . "/reset.php?token=" . urlencode($token);
      $greeting = "Hi " . $teamName . "-Kontakt,";
      $mailBody = $greeting . "\n\nhier ist dein Link zum Zurücksetzen des Team-Passworts:\n" . $resetLink . "\n\nDer Link ist 60 Minuten gültig.\n\nFalls du das nicht angefordert hast, ignoriere diese Mail.";
      $mailError = null;
      uc_smtp_send($env, $contactEmail, "Passwort zurücksetzen", $mailBody, $mailError);
    }

    $feedback = "Wenn die Angaben stimmen, wurde eine Reset-Mail versendet.";
    $teamName = "";
    $contactEmail = "";
  }
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ultimate Combine – Passwort zurücksetzen</title>
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
      <span class="brand-text">Ultimate Combine</span>
    </div>
    <div class="topbar-actions">
      <button class="pill-button is-muted theme-toggle" type="button" data-theme-toggle aria-pressed="false">Auto</button>
    </div>
  </header>

  <main class="auth is-wide">
    <section class="auth-card">
      <h1>Passwort zurücksetzen</h1>
      <p class="lead">Gib Teamname und Kontakt-E-Mail an. Nur wenn beides passt, bekommst du eine Reset-Mail.</p>
      <?php if ($feedback): ?>
        <p class="help"><?php echo htmlspecialchars($feedback, ENT_QUOTES, "UTF-8"); ?></p>
      <?php endif; ?>
      <?php if ($error): ?>
        <p class="help"><?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?></p>
      <?php endif; ?>
      <form class="form" method="post" action="">
        <label class="field">
          <span>Teamname</span>
          <input type="text" name="team_name" value="<?php echo htmlspecialchars($teamName, ENT_QUOTES, "UTF-8"); ?>" required>
        </label>
        <label class="field">
          <span>Kontakt-E-Mail</span>
          <input type="email" name="contact_email" value="<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, "UTF-8"); ?>" required>
        </label>
        <div class="form-actions">
          <button class="primary-button" type="submit">Reset-Link anfordern</button>
          <a class="pill-button is-muted" href="index.php">Zurück</a>
        </div>
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
