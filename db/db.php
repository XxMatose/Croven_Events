<?php
// ─── db.php — Central database configuration ─────────────────────────
// Include this file at the top of every page that needs a DB connection:
//   require_once 'db.php';
// ─────────────────────────────────────────────────────────────────────

// ─── Session ────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─── DB Configuration ───────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'croven_events');
define('DB_USER',    'root');
define('DB_PASS',    '');
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