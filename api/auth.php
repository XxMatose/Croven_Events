<?php
// ─── auth.php — Session gate ──────────────────────────────────────────
// Include this on every protected page AFTER require_once 'db.php':
//   require_once 'api/auth.php';
//
// It checks for a valid session and redirects to login.php if not found.
// ─────────────────────────────────────────────────────────────────────

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['auth_user_id'])) {
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
    header('Location: login.php' . ($redirect ? '?redirect=' . $redirect : ''));
    exit;
}

// ─── Convenience globals set by auth ────────────────────────────────
$authUserId   = (int)$_SESSION['auth_user_id'];
$authUserName = $_SESSION['auth_user_name'] ?? '';

// ─── Auto-set nav_user from logged-in user (if not already set) ──────
if (empty($_SESSION['nav_user'])) {
    $_SESSION['nav_user'] = $authUserName;
}
