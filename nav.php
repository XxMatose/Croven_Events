<?php
// ─── nav.php ─────────────────────────────────────────────────────────
// Logged-in user (set by auth.php) drives all user-scoped data.
// No more "Viewing as" dropdown — the session user IS the current user.
// ─────────────────────────────────────────────────────────────────────

$authUserName = $authUserName ?? ($_SESSION['auth_user_name'] ?? '');
$selectedUser = $authUserName;

// ─── Fetch favorites for the logged-in user ──────────────────────────
$favorites = [];
try {
    if ($selectedUser !== '') {
        $favStmt = $pdo->prepare("SELECT label, path FROM vw_favorites WHERE user = ? ORDER BY label LIMIT 5");
        $favStmt->execute([$selectedUser]);
    } else {
        $favStmt = $pdo->query("SELECT label, path FROM vw_favorites ORDER BY label");
    }
    $favorites = $favStmt->fetchAll();
} catch (Exception $e) { /* skip */ }

$navItems = [
    'home'      => ['label' => 'Home',      'href' => 'index.php',     'icon' => '🏠'],
    'schedule'  => ['label' => 'Schedule',  'href' => 'schedule.php',  'icon' => '📅'],
    'new'       => ['label' => 'Add Event', 'href' => 'add_event.php', 'icon' => '➕'],
    'favorites' => ['label' => 'Favorites', 'href' => 'favorites.php', 'icon' => '⭐'],
    'admin'     => ['label' => 'Admin',     'href' => 'admin.php',     'icon' => '🛠️'],
];

$currentPage = $currentPage ?? '';
$pageTitle   = $pageTitle   ?? 'Croven Events';
?>

<header class="site-header">
  <button class="hamburger" id="hamburgerBtn" aria-label="Open menu" aria-expanded="false">
    <span class="ham-bar"></span>
    <span class="ham-bar"></span>
    <span class="ham-bar"></span>
  </button>
  <span class="site-title"><?= htmlspecialchars($pageTitle) ?></span>
  <button id="themeToggle" class="theme-toggle-btn" aria-label="Toggle theme">🌙</button>
</header>

<div class="nav-overlay" id="navOverlay"></div>
<nav class="nav-drawer" id="navDrawer" aria-hidden="true">

  <div class="nav-drawer-header">
    <span class="nav-drawer-label">Menu</span>
    <button class="nav-close-btn" id="navCloseBtn" aria-label="Close menu">&#10005;</button>
  </div>

  <?php if ($authUserName !== ''): ?>
  <div class="nav-auth-badge">
    <span class="nav-auth-avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr($authUserName, 0, 1))) ?></span>
    <span class="nav-auth-name"><?= htmlspecialchars($authUserName) ?></span>
    <a href="api/logout.php" class="nav-logout-btn">Sign out</a>
  </div>
  <?php endif; ?>

  <div class="nav-divider"></div>

  <ul class="nav-list">
    <?php foreach ($navItems as $key => $item): ?>
      <li>
        <a href="<?= htmlspecialchars($item['href']) ?>"
           class="nav-item <?= $currentPage === $key ? 'nav-item--active' : '' ?>">
          <span class="nav-item-icon"><?= $item['icon'] ?></span>
          <span class="nav-item-label"><?= htmlspecialchars($item['label']) ?></span>
          <?php if ($currentPage === $key): ?><span class="nav-item-dot"></span><?php endif; ?>
        </a>

        <?php if ($key === 'favorites' && !empty($favorites)): ?>
        <ul class="nav-fav-list">
          <?php foreach ($favorites as $fav): ?>
            <li>
              <a href="<?= htmlspecialchars($fav['path']) ?>" class="nav-fav-item">
                <span class="nav-fav-dot"></span>
                <?= htmlspecialchars($fav['label']) ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>

  <div class="nav-drawer-footer">
    <div class="theme-picker-label">Theme</div>
    <div class="theme-picker">
      <button class="theme-pill" data-theme="light">☀️ Light</button>
      <button class="theme-pill" data-theme="dark">🌙 Dark</button>
      <button class="theme-pill" data-theme="red">🔴 Red</button>
    </div>
  </div>

</nav>

<style>
.hamburger {
  width: 38px; height: 38px;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.10);
  border-radius: 10px;
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 5px;
  cursor: pointer; transition: background 0.2s; padding: 0;
}
.hamburger:hover { background: rgba(255,255,255,0.12); }
.ham-bar {
  display: block; width: 16px; height: 1.5px;
  background: rgba(255,255,255,0.85); border-radius: 2px;
  transition: transform 0.3s cubic-bezier(0.4,0,0.2,1), opacity 0.3s cubic-bezier(0.4,0,0.2,1);
}
.hamburger.is-open .ham-bar:nth-child(1) { transform: translateY(6.5px) rotate(45deg); }
.hamburger.is-open .ham-bar:nth-child(2) { opacity: 0; transform: scaleX(0); }
.hamburger.is-open .ham-bar:nth-child(3) { transform: translateY(-6.5px) rotate(-45deg); }
.nav-drawer-label { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; opacity: 0.4; }
.nav-auth-badge { display: flex; align-items: center; gap: 10px; padding: 12px 14px; }
.nav-auth-avatar {
  width: 30px; height: 30px; border-radius: 50%;
  background: var(--accent, #e74c3c); color: #fff;
  font-size: 0.85rem; font-weight: 700;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.nav-auth-name { flex: 1; font-size: 0.9rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.nav-logout-btn {
  font-size: 0.75rem; color: inherit; opacity: 0.45; text-decoration: none;
  border: 1px solid rgba(255,255,255,0.14); border-radius: 6px; padding: 3px 10px;
  white-space: nowrap; flex-shrink: 0; transition: opacity 0.15s, background 0.15s;
}
.nav-logout-btn:hover { opacity: 1; background: rgba(255,255,255,0.08); }
.nav-divider { height: 1px; background: var(--border, rgba(255,255,255,0.07)); margin: 0 10px 8px; }
.nav-list { list-style: none; margin: 0; padding: 0 10px; flex: 1; overflow-y: auto; }
.nav-item {
  display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 10px;
  font-size: 0.88rem; font-weight: 500; color: inherit; text-decoration: none;
  opacity: 0.65; transition: background 0.15s, opacity 0.15s; margin-bottom: 2px;
}
.nav-item:hover        { background: var(--nav-hover, rgba(255,255,255,0.06)); opacity: 1; }
.nav-item--active      { background: rgba(231,76,60,0.14); opacity: 1; color: var(--accent, #e74c3c); }
.nav-item-icon         { font-size: 16px; width: 20px; text-align: center; flex-shrink: 0; }
.nav-item-label        { flex: 1; }
.nav-item-dot          { width: 6px; height: 6px; border-radius: 50%; background: var(--accent, #e74c3c); flex-shrink: 0; }
.nav-fav-list { list-style: none; margin: 0; padding: 0 0 4px 20px; }
.nav-fav-item {
  display: flex; align-items: center; gap: 8px; padding: 7px 10px; border-radius: 8px;
  font-size: 0.82rem; text-decoration: none; color: inherit; opacity: 0.5;
  border-left: 2px solid transparent; transition: background 0.15s, opacity 0.15s, border-color 0.15s; margin-bottom: 1px;
}
.nav-fav-item:hover { background: var(--nav-hover, rgba(255,255,255,0.05)); opacity: 1; border-left-color: var(--accent, #e74c3c); }
.nav-fav-dot { width: 4px; height: 4px; border-radius: 50%; background: currentColor; opacity: 0.4; flex-shrink: 0; }
</style>

<script>
(function () {
  const hamburger = document.getElementById('hamburgerBtn');
  const drawer    = document.getElementById('navDrawer');
  const overlay   = document.getElementById('navOverlay');
  const closeBtn  = document.getElementById('navCloseBtn');

  function openDrawer() {
    drawer.classList.add('open'); overlay.classList.add('open');
    hamburger.setAttribute('aria-expanded', 'true'); drawer.setAttribute('aria-hidden', 'false');
    hamburger.classList.add('is-open');
  }
  function closeDrawer() {
    drawer.classList.remove('open'); overlay.classList.remove('open');
    hamburger.setAttribute('aria-expanded', 'false'); drawer.setAttribute('aria-hidden', 'true');
    hamburger.classList.remove('is-open');
  }
  hamburger.addEventListener('click', openDrawer);
  closeBtn.addEventListener('click',  closeDrawer);
  overlay.addEventListener('click',   closeDrawer);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });

  const themeToggleBtn = document.getElementById('themeToggle');
  const themes = ['dark', 'red', 'light'];
  const themeIcons = { dark: '🌙', red: '🔴', light: '☀️' };

  function applyTheme(theme) {
    document.body.classList.remove('dark', 'red');
    if (theme === 'dark') document.body.classList.add('dark');
    if (theme === 'red')  document.body.classList.add('red');
    themeToggleBtn.textContent = themeIcons[theme];
    localStorage.setItem('theme', theme);
    document.querySelectorAll('.theme-pill').forEach(p => p.classList.toggle('active', p.dataset.theme === theme));
  }
  themeToggleBtn.addEventListener('click', () => {
    const current = localStorage.getItem('theme') || 'dark';
    applyTheme(themes[(themes.indexOf(current) + 1) % themes.length]);
  });
  document.querySelectorAll('.theme-pill').forEach(p => p.addEventListener('click', () => applyTheme(p.dataset.theme)));
  applyTheme(localStorage.getItem('theme') || 'dark');
})();
</script>