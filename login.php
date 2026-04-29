<?php
// ─── login.php ────────────────────────────────────────────────────────
require_once 'db/db_hosted.php';

// Already logged in → go home
if (!empty($_SESSION['auth_user_id'])) {
    header('Location: index.php');
    exit;
}

$error    = '';
$redirect = $_GET['redirect'] ?? 'index.php';

// ─── Handle POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, name, password_hash FROM users WHERE name = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Regenerate session ID on login to prevent fixation
                session_regenerate_id(true);
                $_SESSION['auth_user_id']   = $user['id'];
                $_SESSION['auth_user_name'] = $user['name'];
                $_SESSION['nav_user']       = $user['name'];

                $dest = filter_var($redirect, FILTER_SANITIZE_URL);
                // Only allow relative redirects
                if (!$dest || strpos($dest, '//') !== false || strpos($dest, 'http') === 0) {
                    $dest = 'index.php';
                }
                header('Location: ' . $dest);
                exit;
            } else {
                // Generic message — don't reveal whether username exists
                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            $error = 'A database error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login – Croven Events</title>
  <link rel="stylesheet" href="css/styles.css">
  <style>
    body {
      padding-top: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 20px;
    }

    .login-wrap {
      width: 100%;
      max-width: 380px;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .login-brand {
      text-align: center;
    }

    .login-brand-icon {
      font-size: 36px;
      margin-bottom: 8px;
    }

    .login-brand-name {
      font-size: 20px;
      font-weight: 700;
      color: var(--text);
    }

    .login-brand-sub {
      font-size: 13px;
      color: var(--muted);
      margin-top: 4px;
    }

    .login-card {
      background: var(--card-bg);
      border: 0.5px solid var(--border);
      border-radius: 16px;
      padding: 28px 24px;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .login-card-title {
      font-size: 16px;
      font-weight: 600;
      color: var(--text);
      margin-bottom: -4px;
    }

    .login-error {
      background: rgba(220, 50, 50, 0.1);
      border: 0.5px solid rgba(220, 50, 50, 0.4);
      color: #e05555;
      border-radius: 8px;
      padding: 10px 14px;
      font-size: 13px;
      animation: fadeIn 0.2s ease;
    }

    body.dark .login-error  { background: #2a1010; border-color: #ff6b6b; color: #ff6b6b; }
    body.red  .login-error  { background: #2a0000; border-color: #ff4d4d; color: #ff4d4d; }

    .login-field {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .login-field label {
      font-size: 11px;
      font-weight: 600;
      letter-spacing: 0.07em;
      text-transform: uppercase;
      color: var(--muted);
    }

    .login-field input {
      padding: 10px 12px;
      border: 0.5px solid var(--border-strong);
      border-radius: 10px;
      font-size: 14px;
      background: var(--input-bg);
      color: var(--text);
      font-family: inherit;
      outline: none;
      width: 100%;
      transition: border-color 0.15s, background 0.15s;
    }

    .login-field input:focus {
      border-color: var(--accent);
      background: var(--card-bg);
    }

    .login-field input::placeholder {
      color: var(--border-strong);
      opacity: 0.8;
    }

    body.red .login-field input { color: #ff2b2b; }
    body.red .login-field label { color: #ff2b2b; }

    .login-submit {
      width: 100%;
      padding: 11px;
      background: var(--accent);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-size: 15px;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      transition: opacity 0.15s;
      margin-top: 4px;
    }

    .login-submit:hover   { opacity: 0.85; }
    .login-submit:active  { opacity: 0.7; }

    .login-footer {
      text-align: center;
      font-size: 12px;
      color: var(--muted);
    }

    /* Theme toggle on login page */
    .login-theme-row {
      display: flex;
      justify-content: center;
      gap: 8px;
    }

    .login-theme-pill {
      padding: 5px 14px;
      border-radius: 20px;
      border: 0.5px solid var(--border-strong);
      background: transparent;
      color: var(--muted);
      font-size: 12px;
      cursor: pointer;
      transition: all 0.15s;
    }

    .login-theme-pill:hover { border-color: var(--accent); color: var(--text); }
    .login-theme-pill.active { background: var(--accent); color: #fff; border-color: var(--accent); }
  </style>
</head>
<body>

<div class="login-wrap">

  <div class="login-brand">
    <div class="login-brand-icon">🎶</div>
    <div class="login-brand-name">Croven Events</div>
    <div class="login-brand-sub">Sign in to continue</div>
  </div>

  <div class="login-card">
    <div class="login-card-title">Welcome back</div>

    <?php if ($error !== ''): ?>
      <div class="login-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php<?= $redirect !== 'index.php' ? '?redirect=' . urlencode($redirect) : '' ?>">
      <div style="display:flex;flex-direction:column;gap:14px;">
        <div class="login-field">
          <label for="username">Username</label>
          <input type="text" id="username" name="username"
                 value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                 placeholder="Your name" autofocus autocomplete="username" required>
        </div>
        <div class="login-field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password"
                 placeholder="••••••••" autocomplete="current-password" required>
        </div>
        <button type="submit" class="login-submit">Sign In</button>
      </div>
    </form>
  </div>

  <div class="login-footer">
    <div style="margin-bottom:12px;">Theme</div>
    <div class="login-theme-row">
      <button class="login-theme-pill" data-theme="light">☀️ Light</button>
      <button class="login-theme-pill" data-theme="dark">🌙 Dark</button>
      <button class="login-theme-pill" data-theme="red">🔴 Red</button>
    </div>
  </div>

</div>

<script>
// Theme system (same as nav.php)
const themes     = ['dark', 'red', 'light'];
const themeIcons = { dark: '🌙', red: '🔴', light: '☀️' };

function applyTheme(theme) {
  document.body.classList.remove('dark', 'red');
  if (theme === 'dark') document.body.classList.add('dark');
  if (theme === 'red')  document.body.classList.add('red');
  localStorage.setItem('theme', theme);
  document.querySelectorAll('.login-theme-pill').forEach(pill => {
    pill.classList.toggle('active', pill.dataset.theme === theme);
  });
}

document.querySelectorAll('.login-theme-pill').forEach(pill => {
  pill.addEventListener('click', () => applyTheme(pill.dataset.theme));
});

applyTheme(localStorage.getItem('theme') || 'dark');
</script>

</body>
</html>
