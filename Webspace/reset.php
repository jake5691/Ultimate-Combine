<?php
require_once __DIR__ . "/bootstrap.php";

$token = trim($_GET["token"] ?? "");
$tokenHash = $token !== "" ? hash("sha256", $token) : "";
$error = null;
$success = null;
$teamId = null;

if ($tokenHash === "") {
  $error = "Token fehlt oder ist ungültig.";
} elseif (!$pdo) {
  $error = $dbError ?? "Datenbank ist nicht erreichbar.";
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
    $error = "Token ist ungültig.";
  } elseif (!empty($resetRow["used_at"])) {
    $error = "Token wurde bereits verwendet.";
  } elseif (strtotime($resetRow["expires_at"]) < time()) {
    $error = "Token ist abgelaufen.";
  } else {
    $teamId = (int)$resetRow["team_id"];
  }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $teamId) {
  $newKey = trim($_POST["team_key_new"] ?? "");
  $newKeyRepeat = trim($_POST["team_key_repeat"] ?? "");
  if ($newKey === "" || $newKeyRepeat === "") {
    $error = "Bitte beide Felder ausfüllen.";
  } elseif ($newKey !== $newKeyRepeat) {
    $error = "Die Passwörter stimmen nicht überein.";
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
    $success = "Passwort wurde aktualisiert. Du kannst dich jetzt einloggen.";
    $teamId = null;
  }
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ultimate Combine – Neues Passwort</title>
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
      <h1>Neues Passwort setzen</h1>
      <?php if ($success): ?>
        <p class="help"><?php echo htmlspecialchars($success, ENT_QUOTES, "UTF-8"); ?></p>
        <a class="pill-button" href="index.php">Zum Login</a>
      <?php else: ?>
        <?php if ($error): ?>
          <p class="help"><?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?></p>
        <?php endif; ?>
        <?php if ($teamId): ?>
          <form class="form" method="post" action="">
            <label class="field">
              <span>Neues Passwort</span>
              <input type="password" name="team_key_new" required>
            </label>
            <label class="field">
              <span>Passwort wiederholen</span>
              <input type="password" name="team_key_repeat" required>
            </label>
            <div class="form-actions">
              <button class="primary-button" type="submit">Passwort speichern</button>
              <a class="pill-button is-muted" href="index.php">Abbrechen</a>
            </div>
          </form>
        <?php else: ?>
          <a class="pill-button" href="reset-request.php">Neuen Link anfordern</a>
        <?php endif; ?>
      <?php endif; ?>
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
