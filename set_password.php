<?php
// ─── set_password.php — One-time admin utility ───────────────────────
// Use this to set or reset a user's password.
// DELETE or password-protect this file after initial setup.
//
// Usage: visit set_password.php in your browser while logged into
// your server, or run via CLI:
//   php set_password.php
// ─────────────────────────────────────────────────────────────────────
require_once 'db/db_hosted.php';
require_once 'auth.php';

// ── CLI usage ─────────────────────────────────────────────────────────
if (php_sapi_name() === 'cli') {
    $users = $pdo->query("SELECT id, name FROM users ORDER BY name")->fetchAll();
    echo "Users:\n";
    foreach ($users as $u) {
        echo "  [{$u['id']}] {$u['name']}\n";
    }
    echo "\nEnter user ID: ";
    $userId = trim(fgets(STDIN));
    echo "Enter new password: ";
    $pw = trim(fgets(STDIN));
    $hash = password_hash($pw, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([$hash, (int)$userId]);
    echo "Password updated for user ID {$userId}.\n";
    exit;
}

// ── Web usage ─────────────────────────────────────────────────────────
$message = '';
$users   = [];
try {
    $users = $pdo->query("SELECT id, name FROM users ORDER BY name")->fetchAll();
} catch (Exception $e) {
    $message = 'Error fetching users: ' . htmlspecialchars($e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)($_POST['user_id'] ?? 0);
    $pw1    = $_POST['password']         ?? '';
    $pw2    = $_POST['password_confirm'] ?? '';

    if ($userId <= 0) {
        $message = 'Please select a user.';
    } elseif (strlen($pw1) < 6) {
        $message = 'Password must be at least 6 characters.';
    } elseif ($pw1 !== $pw2) {
        $message = 'Passwords do not match.';
    } else {
        try {
            $hash = password_hash($pw1, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hash, $userId]);
            $name = '';
            foreach ($users as $u) { if ($u['id'] === $userId) $name = $u['name']; }
            $message = "✅ Password set for <strong>" . htmlspecialchars($name) . "</strong>.";
        } catch (PDOException $e) {
            $message = 'Database error: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Set Password – Croven Events</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    body { display:flex; align-items:center; justify-content:center; min-height:100vh; padding:20px; padding-top:20px; }
    .sp-wrap { width:100%; max-width:400px; display:flex; flex-direction:column; gap:18px; }
    .sp-card { background:var(--card-bg); border:0.5px solid var(--border); border-radius:16px; padding:28px 24px; display:flex; flex-direction:column; gap:14px; }
    .sp-title { font-size:16px; font-weight:700; }
    .sp-warn { background:rgba(245,197,24,0.12); border:0.5px solid rgba(245,197,24,0.45); color:#b8960c; border-radius:8px; padding:10px 14px; font-size:12px; }
    body.dark .sp-warn { color:#f5c518; }
    .sp-msg { border-radius:8px; padding:10px 14px; font-size:13px; background:rgba(76,175,80,0.1); border:0.5px solid rgba(76,175,80,0.4); }
    .sp-field { display:flex; flex-direction:column; gap:5px; }
    .sp-field label { font-size:11px; font-weight:600; letter-spacing:0.07em; text-transform:uppercase; color:var(--muted); }
    .sp-field select,
    .sp-field input { padding:9px 12px; border:0.5px solid var(--border-strong); border-radius:8px; font-size:14px; background:var(--input-bg); color:var(--text); font-family:inherit; outline:none; width:100%; }
    .sp-field select:focus,
    .sp-field input:focus { border-color:var(--accent); }
    .sp-btn { width:100%; padding:11px; background:var(--accent); color:#fff; border:none; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; font-family:inherit; }
    .sp-btn:hover { opacity:0.85; }
  </style>
</head>
<body>
<div class="sp-wrap">
  <div class="sp-card">
    <div class="sp-title">🔑 Set User Password</div>
    <div class="sp-warn">⚠️ Delete or restrict this file after use — it requires no login.</div>

    <?php if ($message): ?>
      <div class="sp-msg"><?= $message ?></div>
    <?php endif; ?>

    <form method="POST">
      <div style="display:flex;flex-direction:column;gap:12px;">
        <div class="sp-field">
          <label>User</label>
          <select name="user_id" required>
            <option value="">— Select a user —</option>
            <?php foreach ($users as $u): ?>
              <option value="<?= $u['id'] ?>" <?= (($_POST['user_id'] ?? '') == $u['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($u['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="sp-field">
          <label>New Password</label>
          <input type="password" name="password" placeholder="Min. 6 characters" required>
        </div>
        <div class="sp-field">
          <label>Confirm Password</label>
          <input type="password" name="password_confirm" placeholder="Repeat password" required>
        </div>
        <button type="submit" class="sp-btn">Set Password</button>
      </div>
    </form>
  </div>
</div>
<script>
function applyTheme(t) {
  document.body.classList.remove('dark','red');
  if (t==='dark') document.body.classList.add('dark');
  if (t==='red')  document.body.classList.add('red');
}
applyTheme(localStorage.getItem('theme')||'dark');
</script>
</body>
</html>
