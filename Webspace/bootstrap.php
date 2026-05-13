<?php
$isApiRequest = defined("UC_API_REQUEST") && UC_API_REQUEST;
if (!$isApiRequest && session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

function uc_load_env(string $path): array {
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
}

function uc_detect_lang(array $supported, string $default): string {
  $queryLang = $_GET["lang"] ?? null;
  if (is_string($queryLang) && in_array($queryLang, $supported, true)) {
    if (session_status() === PHP_SESSION_ACTIVE) {
      $_SESSION["lang"] = $queryLang;
    }
    return $queryLang;
  }
  if (session_status() === PHP_SESSION_ACTIVE) {
    $sessionLang = $_SESSION["lang"] ?? null;
    if (is_string($sessionLang) && in_array($sessionLang, $supported, true)) {
      return $sessionLang;
    }
  }
  $accept = $_SERVER["HTTP_ACCEPT_LANGUAGE"] ?? "";
  if (is_string($accept) && $accept !== "") {
    $parts = explode(",", $accept);
    foreach ($parts as $part) {
      $code = strtolower(trim(explode(";", $part)[0] ?? ""));
      if ($code === "") {
        continue;
      }
      $short = substr($code, 0, 2);
      if (in_array($short, $supported, true)) {
        return $short;
      }
    }
  }
  return $default;
}

function uc_lang_url(string $lang): string {
  $uri = $_SERVER["REQUEST_URI"] ?? "";
  $parts = explode("?", $uri, 2);
  $base = $parts[0] ?? "";
  $params = [];
  if (!empty($parts[1])) {
    parse_str($parts[1], $params);
  }
  $params["lang"] = $lang;
  $query = http_build_query($params);
  if ($base === "") {
    return $query === "" ? "" : "?" . $query;
  }
  return $base . ($query === "" ? "" : "?" . $query);
}

function uc_load_translations(string $lang): array {
  $path = __DIR__ . "/i18n/" . $lang . ".php";
  if (!is_readable($path)) {
    return [];
  }
  $translations = require $path;
  return is_array($translations) ? $translations : [];
}

function t(string $key, ?string $fallback = null): string {
  $translations = $GLOBALS["translations"] ?? [];
  if (is_array($translations) && array_key_exists($key, $translations)) {
    return (string)$translations[$key];
  }
  if ($fallback !== null) {
    return $fallback;
  }
  return $key;
}

function uc_get_pdo(array $env, ?string &$error): ?PDO {
  if (empty($env)) {
    $error = "Datenbankkonfiguration fehlt.";
    return null;
  }

  try {
    $dsn = sprintf(
      "mysql:host=%s;dbname=%s;charset=utf8mb4",
      $env["DB_HOST"] ?? "",
      $env["DB_NAME"] ?? ""
    );
    return new PDO(
      $dsn,
      $env["DB_USER"] ?? "",
      $env["DB_PASSWORD"] ?? "",
      [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      ]
    );
  } catch (Throwable $e) {
    $error = "Datenbankverbindung fehlgeschlagen.";
    return null;
  }
}

function uc_env(array $env, string $key, string $default = ""): string {
  $value = $env[$key] ?? $default;
  return is_string($value) ? $value : $default;
}

function uc_filter_players(array $players, string $gender, string $position): array {
  return array_values(array_filter($players, function ($player) use ($gender, $position) {
    if ($gender !== "" && ($player["gender"] ?? "") !== $gender) {
      return false;
    }
    if ($position === "handler" && empty($player["position_handler"])) {
      return false;
    }
    if ($position === "cutter" && empty($player["position_cutter"])) {
      return false;
    }
    return true;
  }));
}

function uc_base_url(array $env): string {
  $configured = trim(uc_env($env, "APP_URL", ""));
  if ($configured !== "") {
    return rtrim($configured, "/");
  }
  if (!empty($_SERVER["HTTP_HOST"])) {
    $scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";
    return $scheme . "://" . $_SERVER["HTTP_HOST"];
  }
  return "";
}

function uc_smtp_send(array $env, string $to, string $subject, string $body, ?string &$error = null): bool {
  $host = uc_env($env, "SMTP_HOST");
  $port = (int)uc_env($env, "SMTP_PORT", "587");
  $user = uc_env($env, "SMTP_USER");
  $pass = uc_env($env, "SMTP_PASS");
  $fromEmail = uc_env($env, "SMTP_FROM", $user);
  $fromName = uc_env($env, "SMTP_FROM_NAME", "Ultimate Combine");
  $secure = strtolower(uc_env($env, "SMTP_SECURE", "tls"));

  if ($host === "" || $fromEmail === "") {
    $error = "SMTP Konfiguration fehlt.";
    return false;
  }

  $socket = fsockopen($host, $port, $errno, $errstr, 10);
  if (!$socket) {
    $error = "SMTP Verbindung fehlgeschlagen.";
    return false;
  }
  $read = static function () use ($socket) {
    $data = "";
    while ($line = fgets($socket, 515)) {
      $data .= $line;
      if (isset($line[3]) && $line[3] === " ") {
        break;
      }
    }
    return $data;
  };
  $send = static function (string $cmd) use ($socket) {
    fwrite($socket, $cmd . "\r\n");
  };

  $read();
  $send("EHLO localhost");
  $read();

  if ($secure === "tls") {
    $send("STARTTLS");
    $read();
    stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    $send("EHLO localhost");
    $read();
  }

  if ($user !== "" && $pass !== "") {
    $send("AUTH LOGIN");
    $read();
    $send(base64_encode($user));
    $read();
    $send(base64_encode($pass));
    $read();
  }

  $send("MAIL FROM:<" . $fromEmail . ">");
  $read();
  $send("RCPT TO:<" . $to . ">");
  $read();
  $send("DATA");
  $read();

  $encodedSubject = mb_encode_mimeheader($subject, "UTF-8");
  $headers = [];
  $headers[] = "From: " . $fromName . " <" . $fromEmail . ">";
  $headers[] = "To: <" . $to . ">";
  $headers[] = "Subject: " . $encodedSubject;
  $headers[] = "MIME-Version: 1.0";
  $headers[] = "Content-Type: text/plain; charset=UTF-8";
  $headers[] = "Content-Transfer-Encoding: 8bit";

  $message = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n.", "\n..", $body);
  fwrite($socket, $message . "\r\n.\r\n");
  $read();
  $send("QUIT");
  fclose($socket);
  return true;
}

function uc_ensure_schema(PDO $pdo): void {
  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS teams (
      id INT AUTO_INCREMENT PRIMARY KEY,
      team_name VARCHAR(120) NOT NULL UNIQUE,
      team_key_hash VARCHAR(255) NOT NULL,
      contact VARCHAR(160) NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );

  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS players (
      id INT AUTO_INCREMENT PRIMARY KEY,
      team_id INT NOT NULL,
      first_name VARCHAR(80) NOT NULL,
      last_name VARCHAR(120) NOT NULL,
      position_cutter TINYINT(1) NOT NULL,
      position_handler TINYINT(1) NOT NULL,
      jersey_number INT NULL,
      gender VARCHAR(12) NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_players_team
        FOREIGN KEY (team_id) REFERENCES teams(id)
        ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );

  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS combines (
      id INT AUTO_INCREMENT PRIMARY KEY,
      team_id INT NOT NULL,
      combine_name VARCHAR(120) NOT NULL,
      event_date DATE NOT NULL,
      combine_location VARCHAR(160) NULL,
      combine_notes TEXT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_combines_team
        FOREIGN KEY (team_id) REFERENCES teams(id)
        ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );

  $columns = $pdo
    ->query(
      "SELECT column_name
       FROM information_schema.columns
       WHERE table_schema = DATABASE()
         AND table_name = 'combines'"
    )
    ->fetchAll(PDO::FETCH_COLUMN);

  if (!in_array("combine_location", $columns, true)) {
    $pdo->exec("ALTER TABLE combines ADD COLUMN combine_location VARCHAR(160) NULL AFTER event_date");
  }
  if (!in_array("combine_notes", $columns, true)) {
    $pdo->exec("ALTER TABLE combines ADD COLUMN combine_notes TEXT NULL AFTER combine_location");
  }

  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS disciplines (
      id INT AUTO_INCREMENT PRIMARY KEY,
      team_id INT NULL,
      discipline_name VARCHAR(120) NOT NULL,
      description TEXT NOT NULL,
      unit VARCHAR(60) NOT NULL,
      category VARCHAR(80) NOT NULL,
      rating_direction VARCHAR(12) NOT NULL,
      expected_min DECIMAL(8,2) NULL,
      expected_max DECIMAL(8,2) NULL,
      bonus_relative DECIMAL(8,2) NULL,
      bonus_absolute DECIMAL(8,2) NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_disciplines_team
        FOREIGN KEY (team_id) REFERENCES teams(id)
        ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );

  $disciplineColumns = $pdo
    ->query(
      "SELECT column_name, is_nullable
       FROM information_schema.columns
       WHERE table_schema = DATABASE()
         AND table_name = 'disciplines'"
    )
    ->fetchAll(PDO::FETCH_ASSOC);

  foreach ($disciplineColumns as $column) {
    if ($column["column_name"] === "team_id" && $column["is_nullable"] === "NO") {
      $pdo->exec("ALTER TABLE disciplines MODIFY COLUMN team_id INT NULL");
      break;
    }
  }

  $disciplineColumnNames = array_column($disciplineColumns, "column_name");
  if (!in_array("expected_min", $disciplineColumnNames, true)) {
    try {
      $pdo->exec("ALTER TABLE disciplines ADD COLUMN expected_min DECIMAL(8,2) NULL AFTER rating_direction");
    } catch (PDOException $e) {
      if (($e->errorInfo[1] ?? null) !== 1060) {
        throw $e;
      }
    }
  }
  if (!in_array("expected_max", $disciplineColumnNames, true)) {
    try {
      $pdo->exec("ALTER TABLE disciplines ADD COLUMN expected_max DECIMAL(8,2) NULL AFTER expected_min");
    } catch (PDOException $e) {
      if (($e->errorInfo[1] ?? null) !== 1060) {
        throw $e;
      }
    }
  }
  if (!in_array("bonus_relative", $disciplineColumnNames, true)) {
    try {
      $pdo->exec("ALTER TABLE disciplines ADD COLUMN bonus_relative DECIMAL(8,2) NULL AFTER expected_max");
    } catch (PDOException $e) {
      if (($e->errorInfo[1] ?? null) !== 1060) {
        throw $e;
      }
    }
  }
  if (!in_array("bonus_absolute", $disciplineColumnNames, true)) {
    try {
      $pdo->exec("ALTER TABLE disciplines ADD COLUMN bonus_absolute DECIMAL(8,2) NULL AFTER bonus_relative");
    } catch (PDOException $e) {
      if (($e->errorInfo[1] ?? null) !== 1060) {
        throw $e;
      }
    }
  }

  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS units (
      id INT AUTO_INCREMENT PRIMARY KEY,
      team_id INT NULL,
      unit_name VARCHAR(80) NOT NULL,
      unit_abbreviation VARCHAR(24) NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_units_team
        FOREIGN KEY (team_id) REFERENCES teams(id)
        ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );

  $unitColumns = $pdo
    ->query(
      "SELECT column_name, is_nullable
       FROM information_schema.columns
       WHERE table_schema = DATABASE()
         AND table_name = 'units'"
    )
    ->fetchAll(PDO::FETCH_ASSOC);
  $unitColumnNames = array_column($unitColumns, "column_name");
  if (!in_array("team_id", $unitColumnNames, true)) {
    try {
      $pdo->exec("ALTER TABLE units ADD COLUMN team_id INT NULL AFTER id");
      $pdo->exec("ALTER TABLE units ADD CONSTRAINT fk_units_team FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE");
    } catch (PDOException $e) {
      if (($e->errorInfo[1] ?? null) !== 1060) {
        throw $e;
      }
    }
  } else {
    foreach ($unitColumns as $column) {
      if ($column["column_name"] === "team_id" && $column["is_nullable"] === "NO") {
        $pdo->exec("ALTER TABLE units MODIFY COLUMN team_id INT NULL");
        break;
      }
    }
  }

  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS admins (
      id INT AUTO_INCREMENT PRIMARY KEY,
      username VARCHAR(120) NOT NULL UNIQUE,
      password_hash VARCHAR(255) NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );

  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS feedback (
      id INT AUTO_INCREMENT PRIMARY KEY,
      team_id INT NULL,
      sender_name VARCHAR(120) NOT NULL,
      sender_email VARCHAR(160) NOT NULL,
      subject VARCHAR(160) NOT NULL,
      message TEXT NOT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'Neu',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_feedback_team
        FOREIGN KEY (team_id) REFERENCES teams(id)
        ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );

  $feedbackColumns = $pdo
    ->query(
      "SELECT column_name
       FROM information_schema.columns
       WHERE table_schema = DATABASE()
         AND table_name = 'feedback'"
    )
    ->fetchAll(PDO::FETCH_COLUMN);

  if (!in_array("status", $feedbackColumns, true)) {
    $pdo->exec("ALTER TABLE feedback ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'Neu' AFTER message");
  }

  $pdo->exec("UPDATE feedback SET status = 'Neu' WHERE status IS NULL OR status = ''");

  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS password_resets (
      id INT AUTO_INCREMENT PRIMARY KEY,
      team_id INT NOT NULL,
      token_hash VARCHAR(255) NOT NULL,
      expires_at DATETIME NOT NULL,
      used_at DATETIME NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_password_resets_team
        FOREIGN KEY (team_id) REFERENCES teams(id)
        ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );

  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS api_tokens (
      id INT AUTO_INCREMENT PRIMARY KEY,
      team_id INT NOT NULL,
      token_hash CHAR(64) NOT NULL UNIQUE,
      name VARCHAR(120) NOT NULL,
      scopes VARCHAR(120) NOT NULL DEFAULT 'read',
      last_used_at DATETIME NULL,
      revoked_at DATETIME NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_api_tokens_team (team_id),
      CONSTRAINT fk_api_tokens_team
        FOREIGN KEY (team_id) REFERENCES teams(id)
        ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );

  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS combine_players (
      id INT AUTO_INCREMENT PRIMARY KEY,
      combine_id INT NOT NULL,
      player_id INT NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uq_combine_player (combine_id, player_id),
      CONSTRAINT fk_combine_players_combine
        FOREIGN KEY (combine_id) REFERENCES combines(id)
        ON DELETE CASCADE,
      CONSTRAINT fk_combine_players_player
        FOREIGN KEY (player_id) REFERENCES players(id)
        ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );

  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS combine_disciplines (
      id INT AUTO_INCREMENT PRIMARY KEY,
      combine_id INT NOT NULL,
      discipline_id INT NOT NULL,
      weight DECIMAL(6,2) NOT NULL DEFAULT 1.00,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uq_combine_discipline (combine_id, discipline_id),
      CONSTRAINT fk_combine_disciplines_combine
        FOREIGN KEY (combine_id) REFERENCES combines(id)
        ON DELETE CASCADE,
      CONSTRAINT fk_combine_disciplines_discipline
        FOREIGN KEY (discipline_id) REFERENCES disciplines(id)
        ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );

  $combineDisciplineColumns = $pdo
    ->query(
      "SELECT column_name
       FROM information_schema.columns
       WHERE table_schema = DATABASE()
         AND table_name = 'combine_disciplines'"
    )
    ->fetchAll(PDO::FETCH_COLUMN);

  if (!in_array("weight", $combineDisciplineColumns, true)) {
    $pdo->exec("ALTER TABLE combine_disciplines ADD COLUMN weight DECIMAL(6,2) NOT NULL DEFAULT 1.00 AFTER discipline_id");
  }

  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS combine_category_weights (
      id INT AUTO_INCREMENT PRIMARY KEY,
      combine_id INT NOT NULL,
      category VARCHAR(80) NOT NULL,
      weight DECIMAL(6,2) NOT NULL DEFAULT 1.00,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uq_combine_category (combine_id, category),
      CONSTRAINT fk_combine_category_weights_combine
        FOREIGN KEY (combine_id) REFERENCES combines(id)
        ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );

  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS combine_results (
      id INT AUTO_INCREMENT PRIMARY KEY,
      combine_id INT NOT NULL,
      discipline_id INT NOT NULL,
      player_id INT NOT NULL,
      result_value VARCHAR(60) NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uq_combine_result (combine_id, discipline_id, player_id),
      CONSTRAINT fk_combine_results_combine
        FOREIGN KEY (combine_id) REFERENCES combines(id)
        ON DELETE CASCADE,
      CONSTRAINT fk_combine_results_discipline
        FOREIGN KEY (discipline_id) REFERENCES disciplines(id)
        ON DELETE CASCADE,
      CONSTRAINT fk_combine_results_player
        FOREIGN KEY (player_id) REFERENCES players(id)
        ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
  );
}

$envPath = dirname(__DIR__) . "/.secrets/.env";
$env = uc_load_env($envPath);
$supportedLangs = ["de", "en"];
$lang = uc_detect_lang($supportedLangs, "de");
$translations = uc_load_translations($lang);
$dbError = null;
$pdo = uc_get_pdo($env, $dbError);

if ($pdo) {
  try {
    uc_ensure_schema($pdo);
  } catch (Throwable $e) {
    $dbError = "Datenbankschema konnte nicht aktualisiert werden: " . $e->getMessage();
    $pdo = null;
  }
}
