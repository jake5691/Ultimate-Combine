<?php

function uc_api_bearer_token(): ?string {
  $header = $_SERVER["HTTP_AUTHORIZATION"]
    ?? $_SERVER["REDIRECT_HTTP_AUTHORIZATION"]
    ?? $_SERVER["Authorization"]
    ?? "";

  if ($header === "" && function_exists("getallheaders")) {
    $headers = getallheaders();
    foreach ($headers as $name => $value) {
      if (strcasecmp((string)$name, "Authorization") === 0) {
        $header = (string)$value;
        break;
      }
    }
  }

  if (is_string($header) && preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
    $token = trim($matches[1]);
    return $token === "" ? null : $token;
  }

  $token = $_SERVER["HTTP_X_API_TOKEN"] ?? "";
  if ($token === "" && function_exists("getallheaders")) {
    $headers = getallheaders();
    foreach ($headers as $name => $value) {
      if (strcasecmp((string)$name, "X-API-Token") === 0) {
        $token = (string)$value;
        break;
      }
    }
  }

  $token = is_string($token) ? trim($token) : "";
  return $token === "" ? null : $token;
}

function uc_api_token_hash(string $token): string {
  return hash("sha256", $token);
}

function uc_api_require_auth(PDO $pdo): array {
  $token = uc_api_bearer_token();
  if ($token === null) {
    uc_api_error("unauthorized", "Missing API token.", 401);
  }

  $stmt = $pdo->prepare(
    "SELECT api_tokens.id,
            api_tokens.team_id,
            api_tokens.name,
            api_tokens.scopes,
            teams.team_name
     FROM api_tokens
     INNER JOIN teams ON teams.id = api_tokens.team_id
     WHERE api_tokens.token_hash = :token_hash
       AND api_tokens.revoked_at IS NULL
     LIMIT 1"
  );
  $stmt->execute([":token_hash" => uc_api_token_hash($token)]);
  $tokenRow = $stmt->fetch();

  if (!$tokenRow) {
    uc_api_error("unauthorized", "Invalid bearer token.", 401);
  }

  $scopes = array_filter(array_map("trim", explode(",", (string)($tokenRow["scopes"] ?? ""))));
  if (!in_array("read", $scopes, true)) {
    uc_api_error("forbidden", "Token is missing read scope.", 403);
  }

  $stmt = $pdo->prepare("UPDATE api_tokens SET last_used_at = CURRENT_TIMESTAMP WHERE id = :id");
  $stmt->execute([":id" => (int)$tokenRow["id"]]);

  return [
    "token_id" => (int)$tokenRow["id"],
    "team_id" => (int)$tokenRow["team_id"],
    "team_name" => (string)$tokenRow["team_name"],
    "scopes" => $scopes,
  ];
}
