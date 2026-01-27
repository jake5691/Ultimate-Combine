<?php
require_once __DIR__ . "/bootstrap.php";

$feedback = null;
$activeTab = "login";
$registerTeam = "";
$registerContact = "";
$registerContactInvalid = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = $_POST["action"] ?? "login";

  if ($action === "register") {
    $activeTab = "register";
    if (!$pdo) {
      $feedback = $dbError ?? t("index.error.register_db", "Registrierung nicht möglich, Datenbank ist nicht erreichbar.");
    } else {
      $team = trim($_POST["team"] ?? "");
      $key = (string)($_POST["key"] ?? "");
      $contact = trim($_POST["contact"] ?? "");
      $registerTeam = $team;
      $registerContact = $contact;

      if (strtolower($team) === "admin") {
        $feedback = t("index.error.team_reserved", "Dieser Teamname ist reserviert.");
      } elseif ($team === "" || $key === "" || $contact === "") {
        $feedback = t("index.error.register_required", "Bitte Teamname, Schlüsselwort und Kontakt angeben.");
      } elseif (!filter_var($contact, FILTER_VALIDATE_EMAIL)) {
        $feedback = t("index.error.contact_invalid", "Bitte eine gültige E-Mail-Adresse angeben.");
        $registerContactInvalid = true;
      } else {
        try {
          $stmt = $pdo->prepare(
            "INSERT INTO teams (team_name, team_key_hash, contact)
             VALUES (:team_name, :team_key_hash, :contact)"
          );
          $stmt->execute([
            ":team_name" => $team,
            ":team_key_hash" => password_hash($key, PASSWORD_DEFAULT),
            ":contact" => $contact,
          ]);

          $_SESSION["team_id"] = (int)$pdo->lastInsertId();
          $_SESSION["team_name"] = $team;
          header("Location: team.php");
          exit;
        } catch (PDOException $e) {
          if ((int)($e->errorInfo[1] ?? 0) === 1062) {
            $feedback = t("index.error.team_exists", "Teamname ist bereits vergeben.");
          } else {
            $feedback = t("index.error.register_failed", "Registrierung fehlgeschlagen.");
          }
        }
      }
    }
  }

  if ($action === "login") {
    $activeTab = "login";
    if (!$pdo) {
      $feedback = $dbError ?? t("index.error.login_db", "Login nicht möglich, Datenbank ist nicht erreichbar.");
    } else {
      $team = trim($_POST["team"] ?? "");
      $key = (string)($_POST["key"] ?? "");

      if ($team === "" || $key === "") {
        $feedback = t("index.error.login_required", "Bitte Teamname und Schlüsselwort angeben.");
      } elseif (strtolower($team) === "admin") {
        $stmt = $pdo->prepare(
          "SELECT id, username, password_hash
           FROM admins
           WHERE username = :username
           LIMIT 1"
        );
        $stmt->execute([":username" => $team]);
        $row = $stmt->fetch();
        if ($row && password_verify($key, $row["password_hash"])) {
          session_regenerate_id(true);
          $_SESSION["is_admin"] = true;
          unset($_SESSION["team_id"], $_SESSION["team_name"]);
          header("Location: admin.php");
          exit;
        }
        $feedback = t("index.error.admin_invalid", "Admin-Zugang ist falsch.");
      } else {
        $stmt = $pdo->prepare(
          "SELECT id, team_name, team_key_hash FROM teams WHERE team_name = :team_name"
        );
        $stmt->execute([":team_name" => $team]);
        $row = $stmt->fetch();

        if ($row && password_verify($key, $row["team_key_hash"])) {
          $_SESSION["is_admin"] = false;
          $_SESSION["team_id"] = (int)$row["id"];
          $_SESSION["team_name"] = $row["team_name"];
          header("Location: team.php");
          exit;
        }

        $feedback = t("index.error.login_invalid", "Teamname oder Schlüsselwort ist falsch.");
      }
    }
  }
}
?>
<!doctype html>
<html lang="<?php echo htmlspecialchars($lang, ENT_QUOTES, "UTF-8"); ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars(t("site.title", "Ultimate Combine"), ENT_QUOTES, "UTF-8"); ?></title>
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
    <section class="auth-card" id="login">
      <h1><?php echo htmlspecialchars(t("index.title", "Willkommen zur Combine Verwaltung"), ENT_QUOTES, "UTF-8"); ?></h1>
      <p class="lead"><?php echo htmlspecialchars(t("index.lead", "Melde dich an, um Combines für dein Team anzulegen, verwalten und auszuwerten."), ENT_QUOTES, "UTF-8"); ?></p>

      <div class="segmented" role="tablist" aria-label="<?php echo htmlspecialchars(t("index.tabs.aria", "Login oder Registrieren"), ENT_QUOTES, "UTF-8"); ?>">
        <button class="segmented-button<?php echo $activeTab === "login" ? " is-active" : ""; ?>" type="button" data-tab="login"><?php echo htmlspecialchars(t("index.tabs.login", "Login"), ENT_QUOTES, "UTF-8"); ?></button>
        <button class="segmented-button<?php echo $activeTab === "register" ? " is-active" : ""; ?>" type="button" data-tab="register"><?php echo htmlspecialchars(t("index.tabs.register", "Registrieren"), ENT_QUOTES, "UTF-8"); ?></button>
      </div>

      <form class="form<?php echo $activeTab !== "login" ? " is-hidden" : ""; ?>" data-panel="login" method="post" action="">
        <input type="hidden" name="action" value="login">
        <label class="field">
          <span><?php echo htmlspecialchars(t("index.field.team", "Teamname"), ENT_QUOTES, "UTF-8"); ?></span>
          <input type="text" name="team" placeholder="<?php echo htmlspecialchars(t("index.placeholder.team", "z. B. Maultaschen"), ENT_QUOTES, "UTF-8"); ?>" required>
        </label>
        <label class="field">
          <span><?php echo htmlspecialchars(t("index.field.password", "Schlüsselwort"), ENT_QUOTES, "UTF-8"); ?></span>
          <input type="password" name="key" placeholder="<?php echo htmlspecialchars(t("index.placeholder.password", "Team-Code"), ENT_QUOTES, "UTF-8"); ?>" required>
        </label>
        <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("index.login.submit", "Jetzt einloggen"), ENT_QUOTES, "UTF-8"); ?></button>
        <p class="help"><a class="text-link" href="reset-request.php"><?php echo htmlspecialchars(t("index.login.reset", "Passwort zurücksetzen"), ENT_QUOTES, "UTF-8"); ?></a> · <?php echo htmlspecialchars(t("index.login.no_access", "Noch kein Zugang?"), ENT_QUOTES, "UTF-8"); ?> <a class="text-link js-tab-link" href="#register" data-tab="register"><?php echo htmlspecialchars(t("index.tabs.register", "Registrieren"), ENT_QUOTES, "UTF-8"); ?></a></p>
        <?php if ($feedback && $activeTab === "login"): ?>
          <p class="help"><?php echo htmlspecialchars($feedback, ENT_QUOTES, "UTF-8"); ?></p>
        <?php endif; ?>
      </form>

      <form class="form<?php echo $activeTab !== "register" ? " is-hidden" : ""; ?>" id="register" data-panel="register" method="post" action="">
        <input type="hidden" name="action" value="register">
        <label class="field">
          <span><?php echo htmlspecialchars(t("index.field.team", "Teamname"), ENT_QUOTES, "UTF-8"); ?></span>
          <input type="text" name="team" placeholder="<?php echo htmlspecialchars(t("index.placeholder.team_new", "Neuer Teamname"), ENT_QUOTES, "UTF-8"); ?>" value="<?php echo htmlspecialchars($registerTeam, ENT_QUOTES, "UTF-8"); ?>" required>
        </label>
        <label class="field">
          <span><?php echo htmlspecialchars(t("index.field.password", "Schlüsselwort"), ENT_QUOTES, "UTF-8"); ?></span>
          <input type="password" name="key" placeholder="<?php echo htmlspecialchars(t("index.placeholder.password_shared", "Gemeinsamer Team-Code"), ENT_QUOTES, "UTF-8"); ?>" required>
        </label>
        <label class="field">
          <span><?php echo htmlspecialchars(t("index.field.contact", "Kontakt"), ENT_QUOTES, "UTF-8"); ?></span>
          <input class="<?php echo $registerContactInvalid ? "input-error" : ""; ?>" type="email" name="contact" placeholder="<?php echo htmlspecialchars(t("index.placeholder.email", "E-Mail-Adresse"), ENT_QUOTES, "UTF-8"); ?>" value="<?php echo htmlspecialchars($registerContact, ENT_QUOTES, "UTF-8"); ?>" required>
        </label>
        <?php if ($registerContactInvalid): ?>
          <p class="help error"><?php echo htmlspecialchars(t("index.error.contact_fix", "Bitte korrigiere die E-Mail-Adresse, damit du fortfahren kannst."), ENT_QUOTES, "UTF-8"); ?></p>
        <?php endif; ?>
        <button class="primary-button" type="submit"><?php echo htmlspecialchars(t("index.register.submit", "Team anlegen"), ENT_QUOTES, "UTF-8"); ?></button>
        <?php if ($feedback && $activeTab === "register"): ?>
          <p class="help"><?php echo htmlspecialchars($feedback, ENT_QUOTES, "UTF-8"); ?></p>
        <?php endif; ?>
      </form>
    </section>

    <section class="auth-card" id="info">
      <h2><?php echo htmlspecialchars(t("index.info.title", "Was erwartet euch?"), ENT_QUOTES, "UTF-8"); ?></h2>
      <div class="info-grid">
        <div class="info-card">
          <h3><?php echo htmlspecialchars(t("index.info.setup.title", "Eigenes Combine Setup"), ENT_QUOTES, "UTF-8"); ?></h3>
          <p><?php echo htmlspecialchars(t("index.info.setup.body", "Erstelle ein individuelles Combine, nutzt bereitgestellte Disziplinen und erstellt eure eigenen. Gruppiert Disziplinen in Kategorien und vergleicht euch. Gewichtet für euch relevantere Disziplinen und Kategorien stärker."), ENT_QUOTES, "UTF-8"); ?></p>
        </div>
        <div class="info-card">
          <h3><?php echo htmlspecialchars(t("index.info.entry.title", "Eintragen der Ergebnisse"), ENT_QUOTES, "UTF-8"); ?></h3>
          <p><?php echo htmlspecialchars(t("index.info.entry.body", "Tragt direkt vor Ort eure Ergebnisse ein und lasst den Papierkram zu Hause, vergesst auch die halbfertigen Excelsheets. Kein Übertragen von Ergebnissen aus vielen Zetteln und nicht leserliche Schriften."), ENT_QUOTES, "UTF-8"); ?></p>
        </div>
        <div class="info-card">
          <h3><?php echo htmlspecialchars(t("index.info.ranking.title", "Ranking"), ENT_QUOTES, "UTF-8"); ?></h3>
          <p><?php echo htmlspecialchars(t("index.info.ranking.body", "Erstellt ein Overall Ranking oder vergleicht die Ergebnisse nur für eure FMP/MMP Handler/Cutter."), ENT_QUOTES, "UTF-8"); ?></p>
        </div>
        <div class="info-card">
          <h3><?php echo htmlspecialchars(t("index.info.individual.title", "Individuelle Leistungsbetrachtung"), ENT_QUOTES, "UTF-8"); ?></h3>
          <p><?php echo htmlspecialchars(t("index.info.individual.body", "Seht für jeden Spieler wo dessen Stärken und Potentiale im Vergleich zum Rest des Teams sind im eigenen Spinnengraph."), ENT_QUOTES, "UTF-8"); ?></p>
        </div>
         <div class="info-card">
          <h3><?php echo htmlspecialchars(t("index.info.h2h.title", "Head to Head"), ENT_QUOTES, "UTF-8"); ?></h3>
          <p><?php echo htmlspecialchars(t("index.info.h2h.body", "Stellt die Leistung zweier Spieler direkt gegenüber und zeigt eurem Mitspieler wer auf dem Papier das Matchup dominiert. (Vergesst nicht dann auf dem Feld das auch umzusetzen)"), ENT_QUOTES, "UTF-8"); ?></p>
        </div>
        <div class="info-card">
          <h3><?php echo htmlspecialchars(t("index.info.share.title", "Teilen der Ergebnisse"), ENT_QUOTES, "UTF-8"); ?></h3>
          <p><?php echo htmlspecialchars(t("index.info.share.body", "Teilt die Ergebnisse jedes Spielers, H2H oder gleich das Teamranking. Oder ladet eure Daten als csv runter."), ENT_QUOTES, "UTF-8"); ?></p>
        </div>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <a class="footer-link" href="impressum.php"><?php echo htmlspecialchars(t("footer.impressum", "Impressum"), ENT_QUOTES, "UTF-8"); ?></a>
    <a class="footer-link" href="feedback.php"><?php echo htmlspecialchars(t("footer.feedback", "Feedback"), ENT_QUOTES, "UTF-8"); ?></a>
    <script type="text/javascript" src="https://cdnjs.buymeacoffee.com/1.0.0/button.prod.min.js" data-name="bmc-button" data-slug="jakob.christen" data-color="#ff7b4b" data-emoji="☕" data-font="Inter" data-text="Buy me a coffee" data-outline-color="#000000" data-font-color="#000000" data-coffee-color="#FFDD00"></script>
  </footer>

  <script src="theme.js"></script>
  <script>
    const tabs = document.querySelectorAll(".segmented-button");
    const panels = document.querySelectorAll(".form[data-panel]");
    const tabLinks = document.querySelectorAll("[data-tab].js-tab-link");

    const setActiveTab = (tabName) => {
      tabs.forEach((t) => t.classList.toggle("is-active", t.dataset.tab === tabName));
      panels.forEach((panel) => {
        panel.classList.toggle("is-hidden", panel.dataset.panel !== tabName);
      });
    };

    tabs.forEach((tab) => {
      tab.addEventListener("click", () => setActiveTab(tab.dataset.tab));
    });

    tabLinks.forEach((link) => {
      link.addEventListener("click", (event) => {
        event.preventDefault();
        setActiveTab(link.dataset.tab);
        closeDrawer();
        history.replaceState(null, "", link.getAttribute("href"));
      });
    });

    if (window.location.hash === "#register") {
      setActiveTab("register");
    }
  </script>
</body>
</html>
