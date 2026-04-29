<?php
// ─── db.php — Central database configuration ─────────────────────────
// Include this file at the top of every page that needs a DB connection:
//   require_once 'db.php';

// ─── Session ────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$credentials_file = dirname(dirname(dirname(__DIR__))) . 'db/db_credentials.php';

// ── TEMPORARY DEBUG ──
echo "Path PHP is checking: " . $credentials_file;
die();
// ────────────────────

if (!file_exists($credentials_file)) {
    die("Configuration error: credentials file not found at expected path.");
}
require_once $credentials_file;

// ─── DB Configuration ───────────────────────────────────────────────
define('DB_HOST',    getenv('DB_HOST'));
define('DB_NAME',    getenv('DB_NAME'));
define('DB_USER',    getenv('DB_USER'));
define('DB_PASS',    getenv('DB_PASS'));
define('DB_CHARSET', 'utf8mb4');

// ─── Connect ────────────────────────────────────────────────────────
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Connection failed: " . htmlspecialchars($e->getMessage()));
}
