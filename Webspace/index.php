<?php
$feedback = null;
$activeTab = "login";

$envPath = dirname(__DIR__) . "/.secrets/.env";

$loadEnv = function (string $path): array {
  if (!is_readable($path)) {
    return [];
  }

  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  $env = [];

  foreach ($lines as $line) {
    $line = trim($line);
    if ($line === "" || strncmp($line, "#", 1) === 0) {
      continue;
    }

    $parts = explode("=", $line, 2);
    $key = trim($parts[0]);
    $value = isset($parts[1]) ? trim($parts[1]) : "";

    if ($key !== "") {
      $env[$key] = $value;
    }
  }

  return $env;
};

$env = $loadEnv($envPath);
$pdo = null;

if (!empty($env)) {
  try {
    $dsn = sprintf(
      "mysql:host=%s;dbname=%s;charset=utf8mb4",
      $env["DB_HOST"] ?? "",
      $env["DB_NAME"] ?? ""
    );
    $pdo = new PDO(
      $dsn,
      $env["DB_USER"] ?? "",
      $env["DB_PASSWORD"] ?? "",
      [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]
    );
  } catch (Throwable $e) {
    $feedback = "Datenbankverbindung fehlgeschlagen.";
  }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "register") {
  $activeTab = "register";
  if (!$pdo) {
    $feedback = "Registrierung nicht moeglich, Datenbank ist nicht erreichbar.";
  } else {
    $team = trim($_POST["team"] ?? "");
    $key = (string)($_POST["key"] ?? "");
    $contact = trim($_POST["contact"] ?? "");

    if ($team === "" || $key === "" || $contact === "") {
      $feedback = "Bitte Teamname, Schluesselwort und Kontakt angeben.";
    } else {
      try {
        $pdo->exec(
          "CREATE TABLE IF NOT EXISTS teams (
            id INT AUTO_INCREMENT PRIMARY KEY,
            team_name VARCHAR(120) NOT NULL UNIQUE,
            team_key_hash VARCHAR(255) NOT NULL,
            contact VARCHAR(160) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $stmt = $pdo->prepare(
          "INSERT INTO teams (team_name, team_key_hash, contact)
           VALUES (:team_name, :team_key_hash, :contact)"
        );
        $stmt->execute([
          ":team_name" => $team,
          ":team_key_hash" => password_hash($key, PASSWORD_DEFAULT),
          ":contact" => $contact,
        ]);

        $feedback = "Team wurde angelegt.";
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
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Ultimate Combine – Login</title>
  <link rel="stylesheet" href="ui.css">
</head>
<body>
  <div class="bg-grid"></div>

  <header class="topbar">
    <button class="icon-button" id="navToggle" aria-label="Navigation öffnen">
      <span class="icon-lines"></span>
    </button>
    <div class="brand">
      <span class="brand-mark">UC</span>
      <span class="brand-text">Ultimate Combine</span>
    </div>
    <button class="pill-button" type="button">Hilfe</button>
  </header>

  <nav class="drawer" id="drawer" aria-hidden="true">
    <div class="drawer-header">
      <span>Navigation</span>
      <button class="icon-button" id="navClose" aria-label="Navigation schliessen">
        <span class="icon-close"></span>
      </button>
    </div>
    <a class="drawer-link" href="#login" data-tab="login">Login</a>
    <a class="drawer-link" href="#register" data-tab="register">Registrieren</a>
    <a class="drawer-link" href="#info">Info</a>
  </nav>
  <div class="scrim" id="scrim"></div>

  <main class="auth">
    <section class="auth-card" id="login">
      <h1>Willkommen zur Combine Verwaltung</h1>
      <p class="lead">Melde dich an, um Combines für dein Team zu anzulegen, verwalten und anzusehen.</p>

      <div class="segmented" role="tablist" aria-label="Login oder Registrieren">
        <button class="segmented-button<?php echo $activeTab === "login" ? " is-active" : ""; ?>" type="button" data-tab="login">Login</button>
        <button class="segmented-button<?php echo $activeTab === "register" ? " is-active" : ""; ?>" type="button" data-tab="register">Registrieren</button>
      </div>

      <form class="form<?php echo $activeTab !== "login" ? " is-hidden" : ""; ?>" data-panel="login" method="post" action="">
        <label class="field">
          <span>Teamname</span>
          <input type="text" name="team" placeholder="z. B. Maultaschen Tübingen" required>
        </label>
        <label class="field">
          <span>Schlüsselwort</span>
          <input type="password" name="key" placeholder="Team-Code" required>
        </label>
        <button class="primary-button" type="submit">Jetzt einloggen</button>
        <p class="help">Noch kein Zugang? <a class="text-link js-tab-link" href="#register" data-tab="register">Registrieren</a></p>
      </form>

      <form class="form<?php echo $activeTab !== "register" ? " is-hidden" : ""; ?>" id="register" data-panel="register" method="post" action="">
        <input type="hidden" name="action" value="register">
        <label class="field">
          <span>Teamname</span>
          <input type="text" name="team" placeholder="Neuer Teamname" required>
        </label>
        <label class="field">
          <span>Schlüsselwort</span>
          <input type="password" name="key" placeholder="Gemeinsamer Team-Code" required>
        </label>
        <label class="field">
          <span>Kontakt</span>
          <input type="text" name="contact" placeholder="Name oder E-Mail" required>
        </label>
        <button class="primary-button" type="submit">Team anlegen</button>
        <?php if ($feedback): ?>
          <p class="help"><?php echo htmlspecialchars($feedback, ENT_QUOTES, "UTF-8"); ?></p>
        <?php endif; ?>
        <p class="help">Nach dem Registrieren gelangst du automatisch zum Teambereich.</p>
      </form>
    </section>

    <section class="info" id="info">
      <h2>Was erwartet euch?</h2>
      <div class="info-grid">
        <div class="info-card">
          <h3>Gesicherter Zugang</h3>
          <p>Einfacher Login mit Teamname und Schlüsselwort, ohne komplizierte Passwörter.</p>
        </div>
        <div class="info-card">
          <h3>Mobil bereit</h3>
          <p>Grosse Buttons, klare Abstände und eine Navigation, die sich mit dem Daumen bedienen lässt.</p>
        </div>
        <div class="info-card">
          <h3>Schnelle Wege</h3>
          <p>Der Slide-over Menüpunkt führt dich später direkt zu euren Bereichen.</p>
        </div>
      </div>
    </section>
  </main>

  <script>
    const tabs = document.querySelectorAll(".segmented-button");
    const panels = document.querySelectorAll(".form[data-panel]");
    const tabLinks = document.querySelectorAll("[data-tab].js-tab-link, .drawer-link[data-tab]");

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

    const drawer = document.getElementById("drawer");
    const scrim = document.getElementById("scrim");
    const navToggle = document.getElementById("navToggle");
    const navClose = document.getElementById("navClose");

    const closeDrawer = () => {
      drawer.classList.remove("is-open");
      scrim.classList.remove("is-visible");
      drawer.setAttribute("aria-hidden", "true");
    };

    const openDrawer = () => {
      drawer.classList.add("is-open");
      scrim.classList.add("is-visible");
      drawer.setAttribute("aria-hidden", "false");
    };

    navToggle.addEventListener("click", openDrawer);
    navClose.addEventListener("click", closeDrawer);
    scrim.addEventListener("click", closeDrawer);

    if (window.location.hash === "#register") {
      setActiveTab("register");
    }
  </script>
</body>
</html>
