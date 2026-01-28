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
<?php
$pageTitle = t("feedback.title", "Ultimate Combine – Feedback");
$pageLang = $lang;
require __DIR__ . "/partials/head.php";
$brandText = t("site.title", "Ultimate Combine");
$brandSuffix = t("feedback.brand", "Feedback");
$showBack = true;
$backOnclick = "history.back()";
require __DIR__ . "/partials/header-brand.php";
?>

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

  <?php require __DIR__ . "/partials/footer.php"; ?>
  <?php require __DIR__ . "/partials/foot.php"; ?>
