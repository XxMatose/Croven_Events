<?php
// ─── db.php — Central database configuration ─────────────────────────
// Include this file at the top of every page that needs a DB connection:
//   require_once 'db.php';

// ─── Session ────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─── Load credentials from outside the web root ─────────────────────
// Project is at: /home/ngrinsell/public_html/crovenlabs.com/events/
// db.php lives in that folder, so __DIR__ resolves to that path.
// We go up 4 levels to reach /home/ngrinsell/ (above public_html).
//
// __DIR__                                → /home/ngrinsell/public_html/crovenlabs.com/events
// dirname(__DIR__)                       → /home/ngrinsell/public_html/crovenlabs.com
// dirname(dirname(__DIR__))              → /home/ngrinsell/public_html
// dirname(dirname(dirname(__DIR__)))     → /home/ngrinsell  ← credentials go here

$credentials_file = dirname(dirname(dirname(__DIR__))) . '/db_credentials.php';

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
