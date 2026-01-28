<?php
require_once __DIR__ . "/bootstrap.php";

$feedback = null;
$error = null;
$teamName = trim($_POST["team_name"] ?? "");
$contactEmail = trim($_POST["contact_email"] ?? "");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if ($teamName === "" || $contactEmail === "") {
    $error = t("reset_request.error.required", "Bitte Teamname und Kontakt-E-Mail angeben.");
  } elseif (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
    $error = t("common.error.email_invalid", "Bitte eine gültige E-Mail-Adresse angeben.");
  } elseif (!$pdo) {
    $error = $dbError ?? t("error.db_unreachable", "Datenbank ist nicht erreichbar.");
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
      $greeting = sprintf(t("reset_request.mail.greeting", "Hi %s-Kontakt,"), $teamName);
      $mailBody = $greeting
        . "\n\n"
        . t("reset_request.mail.intro", "hier ist dein Link zum Zurücksetzen des Team-Passworts:")
        . "\n"
        . $resetLink
        . "\n\n"
        . t("reset_request.mail.expiry", "Der Link ist 60 Minuten gültig.")
        . "\n\n"
        . t("reset_request.mail.ignore", "Falls du das nicht angefordert hast, ignoriere diese Mail.");
      $mailError = null;
      uc_smtp_send($env, $contactEmail, t("reset_request.mail.subject", "Passwort zurücksetzen"), $mailBody, $mailError);
    }

    $feedback = t("reset_request.feedback.sent", "Wenn die Angaben stimmen, wurde eine Reset-Mail versendet.");
    $teamName = "";
    $contactEmail = "";
  }
}
?>
<?php
$pageTitle = t("reset_request.title", "Ultimate Combine – Passwort zurücksetzen");
$pageLang = $lang;
require __DIR__ . "/partials/head.php";
require __DIR__ . "/partials/header-simple.php";
?>

  <main class="auth is-wide">
    <section class="auth-card">
      <h1><?php echo htmlspecialchars(t("reset_request.heading", "Passwort zurücksetzen"), ENT_QUOTES, "UTF-8"); ?></h1>
      <p class="lead"><?php echo htmlspecialchars(t("reset_request.lead", "Gib Teamname und Kontakt-E-Mail an. Nur wenn beides passt, bekommst du eine Reset-Mail."), ENT_QUOTES, "UTF-8"); ?></p>
      <?php if ($feedback): ?>
        <p class="help"><?php echo htmlspecialchars($feedback, ENT_QUOTES, "UTF-8"); ?></p>
      <?php endif; ?>
      <?php if ($error): ?>
        <p class="help"><?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?></p>
      <?php endif; ?>
      <form class="form" method="post" action="">
        <label class="field">
          <span><?php echo htmlspecialchars(t("index.field.team", "Teamname"), ENT_QUOTES, "UTF-8"); ?></span>
          <input type="text" name="team_name" value="<?php echo htmlspecialchars($teamName, ENT_QUOTES, "UTF-8"); ?>" required>
        </label>
        <label class="field">
          <span><?php echo htmlspecialchars(t("reset_request.field.contact_email", "Kontakt-E-Mail"), ENT_QUOTES, "UTF-8"); ?></span>
          <input type="email" name="contact_email" value="<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, "UTF-8"); ?>" required>
        </label>
        <div class="form-actions">
          <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("reset_request.submit", "Reset-Link anfordern"), ENT_QUOTES, "UTF-8"); ?></button>
          <a class="pill-button is-muted" href="index.php"><?php echo htmlspecialchars(t("common.back", "Zurück"), ENT_QUOTES, "UTF-8"); ?></a>
        </div>
      </form>
    </section>
  </main>

  <?php require __DIR__ . "/partials/footer.php"; ?>
  <?php require __DIR__ . "/partials/foot.php"; ?>
