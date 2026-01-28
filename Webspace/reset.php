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
<?php
$pageTitle = t("reset.title", "Ultimate Combine – Neues Passwort");
$pageLang = $lang;
require __DIR__ . "/partials/head.php";
require __DIR__ . "/partials/header-simple.php";
?>

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

  <?php require __DIR__ . "/partials/footer.php"; ?>
  <?php require __DIR__ . "/partials/foot.php"; ?>
