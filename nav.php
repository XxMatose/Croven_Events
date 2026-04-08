<?php
// ─── nav.php ─────────────────────────────────────────────────────────
// Include this at the top of every page:
//   <?php $currentPage = 'schedule'; require 'nav.php'; 
//
// Set $currentPage to the key matching one of the $navItems below.
// Set $pageTitle  to override the centre title (optional).
// ─────────────────────────────────────────────────────────────────────

$navItems = [
    'home' => ['label' => 'Home',  'href' => 'index.php', 'icon' => '🏠'],
    'schedule' => ['label' => 'Schedule',  'href' => 'schedule.php', 'icon' => '📅'],
    // Add more pages here, e.g.:
    // 'artists'  => ['label' => 'Artists',   'href' => 'artists.php',  'icon' => '🎤'],
    // 'venues'   => ['label' => 'Venues',    'href' => 'venues.php',   'icon' => '📍'],
    // 'stats'    => ['label' => 'Stats',     'href' => 'stats.php',    'icon' => '📊'],
];

$currentPage = $currentPage ?? '';
$pageTitle   = $pageTitle   ?? 'Croven Events';
?>

<!-- ══ Top nav bar ══════════════════════════════════ -->
<header class="site-header">

  <!-- Hamburger button -->
  <button class="hamburger" id="hamburgerBtn" aria-label="Open menu" aria-expanded="false">
    <span class="ham-bar"></span>
    <span class="ham-bar"></span>
    <span class="ham-bar"></span>
  </button>

  <!-- Centre title -->
  <span class="site-title"><?= htmlspecialchars($pageTitle) ?></span>

  <!-- Theme toggle (right side) -->
  <button id="themeToggle" class="theme-toggle-btn" aria-label="Toggle theme">🌙</button>

</header>

<!-- ══ Slide-out drawer ═════════════════════════════ -->
<div class="nav-overlay" id="navOverlay"></div>
<nav class="nav-drawer" id="navDrawer" aria-hidden="true">

  <div class="nav-drawer-header">
    <span class="nav-drawer-title">Croven Events</span>
    <button class="nav-close-btn" id="navCloseBtn" aria-label="Close menu">&#10005;</button>
  </div>

  <ul class="nav-list">
    <?php foreach ($navItems as $key => $item): ?>
      <li>
        <a href="<?= htmlspecialchars($item['href']) ?>"
           class="nav-item <?= $currentPage === $key ? 'nav-item--active' : '' ?>">
          <span class="nav-item-icon"><?= $item['icon'] ?></span>
          <span class="nav-item-label"><?= htmlspecialchars($item['label']) ?></span>
          <?php if ($currentPage === $key): ?>
            <span class="nav-item-dot"></span>
          <?php endif; ?>
        </a>
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

<script>
// ── Nav drawer ────────────────────────────────────────────────────────
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

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeDrawer();
  });

  // ── Theme system ─────────────────────────────────────────────────
  const themeToggleBtn = document.getElementById('themeToggle');
  const themes     = ['dark', 'red', 'light'];
  const themeIcons = { dark: '🌙', red: '🔴', light: '☀️' };

  function applyTheme(theme) {
    document.body.classList.remove('dark', 'red');
    if (theme === 'dark') document.body.classList.add('dark');
    if (theme === 'red')  document.body.classList.add('red');
    themeToggleBtn.textContent = themeIcons[theme];
    localStorage.setItem('theme', theme);

    // Sync drawer pills
    document.querySelectorAll('.theme-pill').forEach(pill => {
      pill.classList.toggle('active', pill.dataset.theme === theme);
    });
  }

  // Cycle on header button click
  themeToggleBtn.addEventListener('click', () => {
    const current = localStorage.getItem('theme') || 'dark';
    const next = themes[(themes.indexOf(current) + 1) % themes.length];
    applyTheme(next);
  });

  // Drawer pills
  document.querySelectorAll('.theme-pill').forEach(pill => {
    pill.addEventListener('click', () => applyTheme(pill.dataset.theme));
  });

  // On load
  applyTheme(localStorage.getItem('theme') || 'dark');
})();
</script>
