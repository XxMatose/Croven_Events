<?php
require_once 'db/db_hosted.php';
require_once 'api/auth.php';

// ─── Get event_ID from query string ─────────────────────────────────
$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

// ─── Fetch the event name for the page header ────────────────────────
$eventName = '';
if ($eventId) {
    $evStmt = $pdo->prepare("SELECT event_Name FROM event WHERE event_ID = ?");
    $evStmt->execute([$eventId]);
    $evRow = $evStmt->fetch();
    if ($evRow) {
        $eventName = $evRow['event_Name'];
    }
}

// ─── Fetch memories for this event ───────────────────────────────────
$memories = [];
if ($eventId) {
    $memStmt = $pdo->prepare(
        "SELECT * FROM memories WHERE event_ID = ? ORDER BY id ASC"
    );
    $memStmt->execute([$eventId]);
    $memories = $memStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Memories<?= $eventName ? ' – ' . htmlspecialchars($eventName) : '' ?> – Croven Events</title>
  <link rel="stylesheet" href="css/styles.css">
</head>
<body>

<?php
  $currentPage = 'schedule';
  $pageTitle   = 'Memories';
  require 'nav.php';
?>

<!-- Sub-header -->
<div class="page-subheader">
  <a class="mem-back-btn" href="schedule.php">&#8592; Back to Schedule</a>
  <?php if ($eventName): ?>
    <span class="mem-event-title"><?= htmlspecialchars($eventName) ?></span>
  <?php endif; ?>
  <span class="record-count"><?= count($memories) ?> memor<?= count($memories) === 1 ? 'y' : 'ies' ?></span>
</div>

<!-- Memory Cards -->
<div class="mem-list">
  <?php if (empty($memories)): ?>
    <p class="no-records">No memories found for this event.</p>
  <?php else: ?>
    <?php foreach ($memories as $mem): ?>
      <div class="mem-card">

        <!-- Left: Image (1/4 width) -->
        <div class="mem-image-wrap">
          <?php if (!empty($mem['image_path'])): ?>
            <img
              class="mem-image"
              src="<?= htmlspecialchars($mem['image_path']) ?>"
              alt="Memory image"
              loading="lazy"
            >
          <?php else: ?>
            <div class="mem-image-placeholder">
              <span>&#128444;</span>
            </div>
          <?php endif; ?>
        </div>

        <!-- Right: Fields -->
        <div class="mem-content">

          <div class="mem-field">
            <span class="mem-label">Direction</span>
            <div class="mem-direction"><?= htmlspecialchars($mem['direction'] ?? '—') ?></div>
          </div>

          <div class="mem-field">
            <span class="mem-label">Story</span>
            <div class="mem-story"><?= nl2br(htmlspecialchars($mem['story'] ?? '')) ?></div>
          </div>

        </div>

      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<style>
/* ── Sub-header extras ────────────────────────────────────────────── */
.page-subheader {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
}
.mem-back-btn {
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--accent, #a78bfa);
  text-decoration: none;
  opacity: 0.75;
  transition: opacity 0.15s;
  white-space: nowrap;
}
.mem-back-btn:hover { opacity: 1; }

.mem-event-title {
  font-size: 0.9rem;
  font-weight: 700;
  opacity: 0.85;
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ── Memory list ──────────────────────────────────────────────────── */
.mem-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 16px;
  max-width: 900px;
  margin: 0 auto;
}

/* ── Memory card ──────────────────────────────────────────────────── */
.mem-card {
  display: flex;
  align-items: stretch;
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 14px;
  overflow: hidden;
  min-height: 160px;
  transition: box-shadow 0.15s;
}
.mem-card:hover {
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.18);
}

/* ── Left: image panel (25% width) ───────────────────────────────── */
.mem-image-wrap {
  flex: 0 0 25%;
  width: 25%;
  min-width: 100px;
  max-width: 220px;
  position: relative;
  overflow: hidden;
  background: var(--input-bg, rgba(255,255,255,0.04));
}
.mem-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.mem-image-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2.2rem;
  opacity: 0.2;
  min-height: 160px;
}

/* ── Right: content panel ─────────────────────────────────────────── */
.mem-content {
  flex: 1;
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  min-width: 0;
}

/* ── Field layout ─────────────────────────────────────────────────── */
.mem-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.mem-label {
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  opacity: 0.4;
}

/* ── Direction ────────────────────────────────────────────────────── */
.mem-direction {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--accent, #a78bfa);
  line-height: 1.4;
}

/* ── Story text box ───────────────────────────────────────────────── */
.mem-story {
  font-size: 0.875rem;
  line-height: 1.65;
  color: var(--text);
  opacity: 0.88;
  background: var(--input-bg, rgba(255,255,255,0.04));
  border: 1px solid var(--border);
  border-radius: 9px;
  padding: 10px 13px;
  white-space: pre-wrap;
  word-break: break-word;
  max-height: 140px;
  overflow-y: auto;
  scrollbar-width: thin;
  scrollbar-color: rgba(255,255,255,0.12) transparent;
}

/* ── No records ───────────────────────────────────────────────────── */
.no-records {
  text-align: center;
  opacity: 0.4;
  padding: 48px 0;
  font-size: 0.9rem;
}

/* ── Mobile ───────────────────────────────────────────────────────── */
@media (max-width: 560px) {
  .mem-card {
    flex-direction: column;
    min-height: unset;
  }
  .mem-image-wrap {
    flex: none;
    width: 100%;
    max-width: 100%;
    height: 180px;
  }
  .mem-image-placeholder {
    min-height: 180px;
  }
}
</style>

</body>
</html>