<?php
/**
 * cors.php — CORS + response helper
 * Di-include di setiap endpoint API
 */

// Allowed origins
$allowedOrigins = [
    'https://sales.petikprofit.id',
    'http://sales.petikprofit.local.test',
    'http://localhost:3005',
    'http://127.0.0.1:3005',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: https://sales.petikprofit.id');
}

header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Response helpers ──

function jsonOk(mixed $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonList(array $data, int $total = 0, int $code = 200): never {
    http_response_code($code);
    echo json_encode([
        'ok'    => true,
        'data'  => $data,
        'total' => $total ?: count($data),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Auth helper ──

function requireAuth(): array {
    $token = null;

    // Cek header Authorization: Bearer <token>
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (str_starts_with($authHeader, 'Bearer ')) {
        $token = substr($authHeader, 7);
    }

    // Fallback: cek cookie
    if (!$token) {
        $token = $_COOKIE['fawz_token'] ?? null;
    }

    if (!$token) {
        jsonError('Unauthorized', 401);
    }

    $payload = verifyToken($token);
    if (!$payload) {
        jsonError('Token tidak valid atau sudah expired', 401);
    }

    return $payload;
}

function verifyToken(string $token): ?array {
    $secret = defined('APP_SECRET') ? APP_SECRET : 'default_secret_change_me';
    $parts  = explode('.', $token);
    if (count($parts) !== 2) return null;

    [$payloadB64, $sig] = $parts;
    $expected = hash_hmac('sha256', $payloadB64, $secret);
    if (!hash_equals($expected, $sig)) return null;

    $payload = json_decode(base64_decode($payloadB64), true);
    if (!$payload || (isset($payload['exp']) && $payload['exp'] < time())) return null;

    return $payload;
}

function createToken(array $payload, int $ttlSeconds = 86400 * 30): string {
    $secret  = defined('APP_SECRET') ? APP_SECRET : 'default_secret_change_me';
    $payload['exp'] = time() + $ttlSeconds;
    $payloadB64 = base64_encode(json_encode($payload));
    $sig        = hash_hmac('sha256', $payloadB64, $secret);
    return $payloadB64 . '.' . $sig;
}

// ── Input helper ──

function getBody(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

function method(): string {
    return $_SERVER['REQUEST_METHOD'];
}
