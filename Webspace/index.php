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
      $feedback = $dbError ?? "Registrierung nicht möglich, Datenbank ist nicht erreichbar.";
    } else {
      $team = trim($_POST["team"] ?? "");
      $key = (string)($_POST["key"] ?? "");
      $contact = trim($_POST["contact"] ?? "");
      $registerTeam = $team;
      $registerContact = $contact;

      if (strtolower($team) === "admin") {
        $feedback = "Dieser Teamname ist reserviert.";
      } elseif ($team === "" || $key === "" || $contact === "") {
        $feedback = "Bitte Teamname, Schlüsselwort und Kontakt angeben.";
      } elseif (!filter_var($contact, FILTER_VALIDATE_EMAIL)) {
        $feedback = "Bitte eine gültige E-Mail-Adresse angeben.";
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
            $feedback = "Teamname ist bereits vergeben.";
          } else {
            $feedback = "Registrierung fehlgeschlagen.";
          }
        }
      }
    }
  }

  if ($action === "login") {
    $activeTab = "login";
    if (!$pdo) {
      $feedback = $dbError ?? "Login nicht möglich, Datenbank ist nicht erreichbar.";
    } else {
      $team = trim($_POST["team"] ?? "");
      $key = (string)($_POST["key"] ?? "");

      if ($team === "" || $key === "") {
        $feedback = "Bitte Teamname und Schlüsselwort angeben.";
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
        $feedback = "Admin-Zugang ist falsch.";
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

        $feedback = "Teamname oder Schlüsselwort ist falsch.";
      }
    }
  }
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ultimate Combine – Login</title>
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
      <button class="pill-button is-muted theme-toggle" type="button" data-theme-toggle aria-pressed="false">Dunkel</button>
    </div>
  </header>

  <main class="auth is-wide">
    <section class="auth-card" id="login">
      <h1>Willkommen zur Combine Verwaltung</h1>
      <p class="lead">Melde dich an, um Combines für dein Team zu anzulegen, verwalten und anzusehen.</p>

      <div class="segmented" role="tablist" aria-label="Login oder Registrieren">
        <button class="segmented-button<?php echo $activeTab === "login" ? " is-active" : ""; ?>" type="button" data-tab="login">Login</button>
        <button class="segmented-button<?php echo $activeTab === "register" ? " is-active" : ""; ?>" type="button" data-tab="register">Registrieren</button>
      </div>

      <form class="form<?php echo $activeTab !== "login" ? " is-hidden" : ""; ?>" data-panel="login" method="post" action="">
        <input type="hidden" name="action" value="login">
        <label class="field">
          <span>Teamname</span>
          <input type="text" name="team" placeholder="z. B. Maultaschen" required>
        </label>
        <label class="field">
          <span>Schlüsselwort</span>
          <input type="password" name="key" placeholder="Team-Code" required>
        </label>
        <button class="primary-button" type="submit">Jetzt einloggen</button>
        <?php if ($feedback && $activeTab === "login"): ?>
          <p class="help"><?php echo htmlspecialchars($feedback, ENT_QUOTES, "UTF-8"); ?></p>
        <?php endif; ?>
        <p class="help">Noch kein Zugang? <a class="text-link js-tab-link" href="#register" data-tab="register">Registrieren</a></p>
      </form>

      <form class="form<?php echo $activeTab !== "register" ? " is-hidden" : ""; ?>" id="register" data-panel="register" method="post" action="">
        <input type="hidden" name="action" value="register">
        <label class="field">
          <span>Teamname</span>
          <input type="text" name="team" placeholder="Neuer Teamname" value="<?php echo htmlspecialchars($registerTeam, ENT_QUOTES, "UTF-8"); ?>" required>
        </label>
        <label class="field">
          <span>Schlüsselwort</span>
          <input type="password" name="key" placeholder="Gemeinsamer Team-Code" required>
        </label>
        <label class="field">
          <span>Kontakt</span>
          <input class="<?php echo $registerContactInvalid ? "input-error" : ""; ?>" type="email" name="contact" placeholder="E-Mail-Adresse" value="<?php echo htmlspecialchars($registerContact, ENT_QUOTES, "UTF-8"); ?>" required>
        </label>
        <?php if ($registerContactInvalid): ?>
          <p class="help error">Bitte korrigiere die E-Mail-Adresse, damit du fortfahren kannst.</p>
        <?php endif; ?>
        <button class="primary-button" type="submit">Team anlegen</button>
        <?php if ($feedback && $activeTab === "register"): ?>
          <p class="help"><?php echo htmlspecialchars($feedback, ENT_QUOTES, "UTF-8"); ?></p>
        <?php endif; ?>
        <p class="help">Nach dem Registrieren gelangst du automatisch zum Teambereich.</p>
      </form>
    </section>

    <section class="auth-card" id="info">
      <h2>Was erwartet euch?</h2>
      <div class="info-grid">
        <div class="info-card">
          <h3>Eigenes Combine Setup</h3>
          <p>Erstelle ein individuelles Combine, nutzt bereitgestellte Disziplinen und erstellt eure eigenen. Gruppiert Disziplinen in Kategorien und vergleicht euch. Gewichtet euch relevantere Disziplinen und Kategorien stärker.</p>
        </div>
        <div class="info-card">
          <h3>Eintragen der Ergebnisse</h3>
          <p>Tragt direkt vor Ort eure Ergebnisse ein und lasst den Papierkram zu Hause. Kein Übertragen von Ergebnissen aus vielen Zetteln und nicht leserliche Schriften.</p>
        </div>
        <div class="info-card">
          <h3>Ranking</h3>
          <p>Erstellt ein Overall Ranking oder vergleicht die Ergebnisse nur für eure FMP/MMP Handler/Cutter.</p>
        </div>
        <div class="info-card">
          <h3>Individuelle Leistungsbetrachtung</h3>
          <p>Seht für jeden Spieler wo dessen Stärken und Potentiale im Vergleich zum Rest des Teams sind im eigenen Spinnengraph.</p>
        </div>
         <div class="info-card">
          <h3>Head to Head</h3>
          <p>Stellt die Leistung zweier Spieler direkt gegenüber.</p>
        </div>
        <div class="info-card">
          <h3>Teilen der Ergebnisse</h3>
          <p>Teilt die Ergebnisse jedes Spielers oder gleich das Teamranking.</p>
        </div>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <a class="footer-link" href="impressum.php">Impressum</a>
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
