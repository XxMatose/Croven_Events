<?php
// ─── DB Configuration ───────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'croven_events');
define('DB_USER', 'root');
define('DB_PASS', '');
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

// ─── Quick stats for the home page ──────────────────────────────────
$totalEvents    = $pdo->query("SELECT COUNT(DISTINCT event_ID) FROM vw_full_event")->fetchColumn();
$totalPerformers= $pdo->query("SELECT COUNT(DISTINCT performer_Name) FROM vw_full_event WHERE performer_Name IS NOT NULL")->fetchColumn();
$totalWatched   = $pdo->query("SELECT COUNT(DISTINCT performer_Name) FROM vw_full_event WHERE watched = 1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Croven Events</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php
  $currentPage = 'home';
  $pageTitle   = 'Croven Events';
  require 'nav.php';
?>

<!-- ══ Home content ══════════════════════════════════════════════════ -->
<main class="home-wrap">

  <div class="home-hero">
    <div class="home-hero-icon">🎶</div>
    <h2 class="home-hero-title">Your events, all in one place.</h2>
    <p class="home-hero-sub">Browse schedules, track performers, and relive your favourite shows.</p>
    <a href="schedule.php" class="home-cta-btn">View Schedule →</a>
  </div>

  <div class="stat-grid">
    <div class="stat-card">
      <span class="stat-number"><?= number_format($totalEvents) ?></span>
      <span class="stat-label">Events</span>
    </div>
    <div class="stat-card">
      <span class="stat-number"><?= number_format($totalPerformers) ?></span>
      <span class="stat-label">Performers</span>
    </div>
    <div class="stat-card">
      <span class="stat-number"><?= number_format($totalWatched) ?></span>
      <span class="stat-label">Watched</span>
    </div>
  </div>

  <div class="home-nav-cards">
    <a href="schedule.php" class="home-nav-card">
      <span class="home-nav-card-icon">📅</span>
      <div>
        <div class="home-nav-card-title">Schedule</div>
        <div class="home-nav-card-sub">Browse all events &amp; performers</div>
      </div>
      <span class="home-nav-card-arrow">›</span>
    </a>
    <!-- Add more nav cards here as you add pages -->
  </div>

</main>

</body>
</html>
