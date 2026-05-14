<?php

function uc_api_send_json(array $payload, int $statusCode = 200): void {
  http_response_code($statusCode);
  header("Content-Type: application/json; charset=UTF-8");
  header("Cache-Control: no-store");
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function uc_api_error(string $code, string $message, int $statusCode): void {
  uc_api_send_json([
    "error" => [
      "code" => $code,
      "message" => $message,
    ],
  ], $statusCode);
}

function uc_api_require_method(string $method): void {
  if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== $method) {
    header("Allow: " . $method);
    uc_api_error("method_not_allowed", "Method not allowed.", 405);
  }
}

function uc_api_int_param(string $name, bool $required = false): ?int {
  if (!array_key_exists($name, $_GET) || $_GET[$name] === "") {
    if ($required) {
      uc_api_error("invalid_request", "Missing required parameter: " . $name, 400);
    }
    return null;
  }

  $value = filter_var($_GET[$name], FILTER_VALIDATE_INT);
  if ($value === false || $value <= 0) {
    uc_api_error("invalid_request", "Invalid integer parameter: " . $name, 400);
  }
  return $value;
}

function uc_api_normalize_row(array $row): array {
  foreach ($row as $key => $value) {
    if (is_string($value) && preg_match('/^-?\d+$/', $value)) {
      $row[$key] = (int)$value;
    }
  }
  return $row;
}
