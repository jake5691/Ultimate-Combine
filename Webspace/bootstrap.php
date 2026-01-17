<?php
session_start();

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
      team_id INT NOT NULL,
      discipline_name VARCHAR(120) NOT NULL,
      description TEXT NOT NULL,
      unit VARCHAR(60) NOT NULL,
      category VARCHAR(80) NOT NULL,
      rating_direction VARCHAR(12) NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_disciplines_team
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
$dbError = null;
$pdo = uc_get_pdo($env, $dbError);

if ($pdo) {
  uc_ensure_schema($pdo);
}
