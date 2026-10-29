<?php
/**
 * db.php — Koneksi MySQL untuk Sales Petik Profit
 */

// Load kredensial dari .env.php
$envFile = __DIR__ . '/.env.php';
if (file_exists($envFile)) {
    require_once $envFile;
}

// Baca dari constant (_DB_*) atau fallback getenv() jika tersedia
function _cfg(string $const, string $envKey, string $default = ''): string {
    if (defined($const)) return constant($const);
    if (function_exists('getenv') && getenv($envKey) !== false) return getenv($envKey);
    return $default;
}

define('DB_HOST',    _cfg('_DB_HOST',    'DB_HOST',    'localhost'));
define('DB_PORT',    _cfg('_DB_PORT',    'DB_PORT',    '3306'));
define('DB_NAME',    _cfg('_DB_NAME',    'DB_NAME',    'sales_petikprofit_id'));
define('DB_USER',    _cfg('_DB_USER',    'DB_USER',    'sales_petikprofit_id'));
define('DB_PASS',    _cfg('_DB_PASS',    'DB_PASS',    ''));
define('DB_PP_NAME', _cfg('_DB_PP_NAME', 'DB_PP_NAME', 'pp_ikutin_id'));
define('APP_SECRET', _cfg('_APP_SECRET', 'APP_SECRET', 'fallback_secret_change_me'));

function getDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT
         . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

function getPPDB(): PDO {
    static $ppPdo = null;
    if ($ppPdo !== null) return $ppPdo;

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT
         . ';dbname=' . DB_PP_NAME . ';charset=utf8mb4';

    $ppPdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $ppPdo;
}
