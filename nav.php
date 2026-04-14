<?php
// ─── nav.php ─────────────────────────────────────────────────────────
// Include this at the top of every page:
//   <?php $currentPage = 'schedule'; require 'nav.php';
//
// Set $currentPage to the key matching one of the $navItems below.
// Set $pageTitle  to override the centre title (optional).
// ─────────────────────────────────────────────────────────────────────

// ─── Fetch users for dropdown ────────────────────────────────────────
$users = [];
try {
    $userStmt = $pdo->query("SELECT name FROM croven_events.users ORDER BY name");
    $users    = $userStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // Silently skip if table doesn't exist yet
}

// ─── Selected user (persisted via GET param or session) ──────────────
$selectedUser = $_GET['nav_user'] ?? ($_SESSION['nav_user'] ?? '');
if (isset($_GET['nav_user'])) {
    if ($_GET['nav_user'] === '') {
        unset($_SESSION['nav_user']);
    } else {
        $_SESSION['nav_user'] = $_GET['nav_user'];
    }
    $selectedUser = $_GET['nav_user'];
}

// ─── Fetch favorites filtered by selected user ───────────────────────
$favorites = [];
try {
    if ($selectedUser !== '') {
        $favStmt = $pdo->prepare("SELECT label, path FROM vw_favorites WHERE user = ? ORDER BY label");
        $favStmt->execute([$selectedUser]);
    } else {
        $favStmt = $pdo->query("SELECT label, path FROM vw_favorites ORDER BY label");
    }
    $favorites = $favStmt->fetchAll();
} catch (Exception $e) {
    // Silently skip if view doesn't exist yet
}

$navItems = [
    'home'     => ['label' => 'Home',     'href' => 'index.php',    'icon' => '🏠'],
    'schedule' => ['label' => 'Schedule', 'href' => 'schedule.php', 'icon' => '📅'],
    'stats' => ['label' => 'Stats', 'href' => 'stats.php', 'icon' => '📜'],
    'new' => ['label' => 'Add Event', 'href' => 'add_event.php', 'icon' => '➕'],
    // Add more pages here, e.g.:
    // 'artists' => ['label' => 'Artists', 'href' => 'artists.php', 'icon' => '🎤'],
    // 'venues'  => ['label' => 'Venues',  'href' => 'venues.php',  'icon' => '📍'],
    // 'stats'   => ['label' => 'Stats',   'href' => 'stats.php',   'icon' => '📊'],
];

$currentPage = $currentPage ?? '';
$pageTitle   = $pageTitle   ?? 'Croven Events';
?>

<!-- ══ Top nav bar ══════════════════════════════════ -->
<header class="site-header">

  <button class="hamburger" id="hamburgerBtn" aria-label="Open menu" aria-expanded="false">
    <span class="ham-bar"></span>
    <span class="ham-bar"></span>
    <span class="ham-bar"></span>
  </button>

  <span class="site-title"><?= htmlspecialchars($pageTitle) ?></span>

  <button id="themeToggle" class="theme-toggle-btn" aria-label="Toggle theme">🌙</button>

</header>

<!-- ══ Slide-out drawer ═════════════════════════════ -->
<div class="nav-overlay" id="navOverlay"></div>
<nav class="nav-drawer" id="navDrawer" aria-hidden="true">

  <div class="nav-drawer-header">
    <span class="nav-drawer-label">Menu</span>
    <button class="nav-close-btn" id="navCloseBtn" aria-label="Close menu">&#10005;</button>
  </div>

  <!-- ── User dropdown ── -->
  <div class="nav-user-section">
    <label class="nav-user-label" for="navUserSelect">Viewing as</label>
    <div class="nav-user-select-wrap">
      <select class="nav-user-select" id="navUserSelect">
        <option value="">Everyone</option>
        <?php foreach ($users as $user): ?>
          <option value="<?= htmlspecialchars($user) ?>"
            <?= $selectedUser === $user ? 'selected' : '' ?>>
            <?= htmlspecialchars($user) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <svg class="nav-user-chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
        <polyline points="4,6 8,10 12,6"/>
      </svg>
    </div>
  </div>

  <div class="nav-divider"></div>

  <ul class="nav-list">
    <?php foreach ($navItems as $key => $item): ?>
      <li>
        <?php if ($key === 'schedule'):
            $scheduleHref = $item['href'];
            if ($selectedUser !== '') {
                $scheduleHref .= (strpos($scheduleHref, '?') === false ? '?' : '&') . 'nav_user=' . urlencode($selectedUser);
            }
          ?>
          <!-- Schedule item -->
          <a href="<?= htmlspecialchars($scheduleHref) ?>"
             class="nav-item <?= $currentPage === $key ? 'nav-item--active' : '' ?>">
            <span class="nav-item-icon"><?= $item['icon'] ?></span>
            <span class="nav-item-label"><?= htmlspecialchars($item['label']) ?></span>
            <?php if ($currentPage === $key): ?><span class="nav-item-dot"></span><?php endif; ?>
          </a>

          <?php if (!empty($favorites)): ?>
          <ul class="nav-fav-list open" id="favList">
            <?php foreach ($favorites as $fav):
                $favHref = $fav['path'];
                if ($selectedUser !== '') {
                    $favHref .= (strpos($favHref, '?') === false ? '?' : '&') . 'nav_user=' . urlencode($selectedUser);
                }
              ?>
              <li>
                <a href="<?= htmlspecialchars($favHref) ?>" class="nav-fav-item">
                  <span class="nav-fav-dot"></span>
                  <?= htmlspecialchars($fav['label']) ?>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>

        <?php else:
            $navHref = $item['href'];
            if ($selectedUser !== '') {
                $navHref .= (strpos($navHref, '?') === false ? '?' : '&') . 'nav_user=' . urlencode($selectedUser);
            }
          ?>
          <a href="<?= htmlspecialchars($navHref) ?>"
             class="nav-item <?= $currentPage === $key ? 'nav-item--active' : '' ?>">
            <span class="nav-item-icon"><?= $item['icon'] ?></span>
            <span class="nav-item-label"><?= htmlspecialchars($item['label']) ?></span>
            <?php if ($currentPage === $key): ?><span class="nav-item-dot"></span><?php endif; ?>
          </a>
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
/* ── Hamburger (modern pill) ─────────────────────────────────────────── */
.hamburger {
  width: 38px;
  height: 38px;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.10);
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 5px;
  cursor: pointer;
  transition: background 0.2s;
  padding: 0;
}
.hamburger:hover { background: rgba(255,255,255,0.12); }
.ham-bar {
  display: block;
  width: 16px;
  height: 1.5px;
  background: rgba(255,255,255,0.85);
  border-radius: 2px;
  transition: transform 0.3s cubic-bezier(0.4,0,0.2,1),
              opacity  0.3s cubic-bezier(0.4,0,0.2,1);
}
.hamburger.is-open .ham-bar:nth-child(1) { transform: translateY(6.5px) rotate(45deg); }
.hamburger.is-open .ham-bar:nth-child(2) { opacity: 0; transform: scaleX(0); }
.hamburger.is-open .ham-bar:nth-child(3) { transform: translateY(-6.5px) rotate(-45deg); }

/* ── Drawer header ───────────────────────────────────────────────────── */
.nav-drawer-label {
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  opacity: 0.4;
}

/* ── User dropdown ───────────────────────────────────────────────────── */
.nav-user-section {
  padding: 12px 14px 10px;
}
.nav-user-label {
  display: block;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  opacity: 0.4;
  margin-bottom: 6px;
}
.nav-user-select-wrap {
  position: relative;
  display: flex;
  align-items: center;
}
.nav-user-select {
  width: 100%;
  appearance: none;
  -webkit-appearance: none;
  background: rgba(255,255,255,0.06);
  border: 1px solid rgba(255,255,255,0.10);
  border-radius: 10px;
  padding: 8px 34px 8px 12px;
  font-size: 0.88rem;
  font-weight: 500;
  color: inherit;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s;
  outline: none;
}
.nav-user-select:hover  { background: rgba(255,255,255,0.10); }
.nav-user-select:focus  { border-color: rgba(255,255,255,0.25); }
.nav-user-select option { background: #1a1a2e; color: #fff; }
.nav-user-chevron {
  position: absolute;
  right: 10px;
  width: 14px;
  height: 14px;
  opacity: 0.4;
  pointer-events: none;
}

/* ── Divider ─────────────────────────────────────────────────────────── */
.nav-divider {
  height: 1px;
  background: var(--border, rgba(255,255,255,0.07));
  margin: 4px 10px 8px;
}

/* ── Nav list & items ────────────────────────────────────────────────── */
.nav-list {
  list-style: none;
  margin: 0;
  padding: 0 10px;
}
.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border-radius: 10px;
  font-size: 0.88rem;
  font-weight: 500;
  color: inherit;
  text-decoration: none;
  opacity: 0.65;
  transition: background 0.15s, opacity 0.15s;
  margin-bottom: 2px;
}
.nav-item:hover        { background: var(--nav-hover, rgba(255,255,255,0.06)); opacity: 1; }
.nav-item--active      { background: rgba(231,76,60,0.14); opacity: 1; color: var(--accent, #e74c3c); }
.nav-item-icon         { font-size: 16px; width: 20px; text-align: center; flex-shrink: 0; }
.nav-item-label        { flex: 1; }
.nav-item-dot          { width: 6px; height: 6px; border-radius: 50%; background: var(--accent, #e74c3c); flex-shrink: 0; }

/* ── Favorites — direct children of Schedule ─────────────────────────── */

.nav-fav-list {
  list-style: none;
  margin: 0;
  padding: 0 0 4px 20px;
}

.nav-fav-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 7px 10px;
  border-radius: 8px;
  font-size: 0.82rem;
  text-decoration: none;
  color: inherit;
  opacity: 0.5;
  border-left: 2px solid transparent;
  transition: background 0.15s, opacity 0.15s, border-color 0.15s;
  margin-bottom: 1px;
}
.nav-fav-item:hover {
  background: var(--nav-hover, rgba(255,255,255,0.05));
  opacity: 1;
  border-left-color: var(--accent, #e74c3c);
}
.nav-fav-dot {
  width: 4px;
  height: 4px;
  border-radius: 50%;
  background: currentColor;
  opacity: 0.4;
  flex-shrink: 0;
}
</style>

<script>
(function () {
  const hamburger = document.getElementById('hamburgerBtn');
  const drawer    = document.getElementById('navDrawer');
  const overlay   = document.getElementById('navOverlay');
  const closeBtn  = document.getElementById('navCloseBtn');

  function openDrawer() {
    drawer.classList.add('open');
    overlay.classList.add('open');
    hamburger.setAttribute('aria-expanded', 'true');
    drawer.setAttribute('aria-hidden', 'false');
    hamburger.classList.add('is-open');
  }

  function closeDrawer() {
    drawer.classList.remove('open');
    overlay.classList.remove('open');
    hamburger.setAttribute('aria-expanded', 'false');
    drawer.setAttribute('aria-hidden', 'true');
    hamburger.classList.remove('is-open');
  }

  hamburger.addEventListener('click', openDrawer);
  closeBtn.addEventListener('click',  closeDrawer);
  overlay.addEventListener('click',   closeDrawer);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });

  // ── User dropdown — reload page with nav_user param ─────────────────
  const userSelect = document.getElementById('navUserSelect');
  if (userSelect) {
    userSelect.addEventListener('change', () => {
      const url = new URL(window.location.href);
      if (userSelect.value) {
        url.searchParams.set('nav_user', userSelect.value);
      } else {
        url.searchParams.delete('nav_user');
      }
      window.location.href = url.toString();
    });
  }

  // ── Theme system ────────────────────────────────────────────────────
  const themeToggleBtn = document.getElementById('themeToggle');
  const themes     = ['dark', 'red', 'light'];
  const themeIcons = { dark: '🌙', red: '🔴', light: '☀️' };

  function applyTheme(theme) {
    document.body.classList.remove('dark', 'red');
    if (theme === 'dark') document.body.classList.add('dark');
    if (theme === 'red')  document.body.classList.add('red');
    themeToggleBtn.textContent = themeIcons[theme];
    localStorage.setItem('theme', theme);
    document.querySelectorAll('.theme-pill').forEach(pill => {
      pill.classList.toggle('active', pill.dataset.theme === theme);
    });
  }

  themeToggleBtn.addEventListener('click', () => {
    const current = localStorage.getItem('theme') || 'dark';
    const next = themes[(themes.indexOf(current) + 1) % themes.length];
    applyTheme(next);
  });

  document.querySelectorAll('.theme-pill').forEach(pill => {
    pill.addEventListener('click', () => applyTheme(pill.dataset.theme));
  });

  // On load
  applyTheme(localStorage.getItem('theme') || 'dark');
})();
</script>