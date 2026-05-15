<?php
// ─── gallery_test.php ────────────────────────────────────────────────
// Temporary test/admin page. Drop this in the same directory as
// onedrive_helper.php and onedrive_ajax.php.
// Access it in your browser — no login gate here (test only).

define('DEV_MODE', true); // TODO: remove before production

require_once 'db/db_hosted.php';

// Pull all events that have a OneDrive folder linked
$stmt = $pdo->query(
    "SELECT event_id, event_name, event_year, memory_path FROM event
     WHERE memory_path IS NOT NULL AND memory_path != ''
     ORDER BY event_year DESC"
);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gallery Test Page</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Syne:wght@400;600;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:        #0a0a0b;
      --surface:   #111113;
      --card:      #18181c;
      --border:    rgba(255,255,255,.07);
      --border-h:  rgba(255,255,255,.15);
      --text:      #e8e8ea;
      --muted:     #5a5a62;
      --accent:    #f0c14b;
      --accent-dim:rgba(240,193,75,.12);
      --danger:    #e05252;
      --radius:    12px;
    }

    body {
      background: var(--bg);
      color: var(--text);
      font-family: 'Syne', sans-serif;
      min-height: 100vh;
      padding: 48px 24px;
    }

    /* ── Grain overlay ── */
    body::before {
      content: '';
      position: fixed; inset: 0; z-index: 0; pointer-events: none;
      background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
      opacity: .5;
    }

    .wrap {
      position: relative; z-index: 1;
      max-width: 720px;
      margin: 0 auto;
    }

    /* ── Header ── */
    header {
      margin-bottom: 48px;
    }
    .badge {
      display: inline-block;
      font-family: 'DM Mono', monospace;
      font-size: 11px;
      letter-spacing: .12em;
      text-transform: uppercase;
      color: var(--accent);
      background: var(--accent-dim);
      border: 1px solid rgba(240,193,75,.2);
      border-radius: 4px;
      padding: 3px 10px;
      margin-bottom: 14px;
    }
    h1 {
      font-size: clamp(28px, 5vw, 42px);
      font-weight: 800;
      letter-spacing: -.02em;
      line-height: 1.1;
      color: var(--text);
    }
    h1 span { color: var(--accent); }
    .subtitle {
      margin-top: 10px;
      font-size: 14px;
      color: var(--muted);
      font-family: 'DM Mono', monospace;
    }

    /* ── Section label ── */
    .section-label {
      font-family: 'DM Mono', monospace;
      font-size: 11px;
      letter-spacing: .12em;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: 12px;
      padding-left: 2px;
    }

    /* ── Event list ── */
    .event-list {
      display: flex;
      flex-direction: column;
      gap: 8px;
      margin-bottom: 40px;
    }

    .event-row {
      display: flex;
      align-items: center;
      gap: 14px;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 14px 18px;
      cursor: pointer;
      transition: border-color .15s, background .15s, transform .15s;
      user-select: none;
    }
    .event-row:hover {
      border-color: var(--border-h);
      background: #1d1d22;
      transform: translateX(3px);
    }
    .event-row.selected {
      border-color: var(--accent);
      background: var(--accent-dim);
    }

    .event-icon {
      width: 36px; height: 36px;
      border-radius: 8px;
      background: rgba(255,255,255,.05);
      display: flex; align-items: center; justify-content: center;
      font-size: 16px;
      flex-shrink: 0;
    }

    .event-info { flex: 1; min-width: 0; }
    .event-name {
      font-size: 14px;
      font-weight: 600;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .event-meta {
      font-family: 'DM Mono', monospace;
      font-size: 11px;
      color: var(--muted);
      margin-top: 3px;
    }

    .event-folder-id {
      font-family: 'DM Mono', monospace;
      font-size: 11px;
      color: var(--muted);
      background: rgba(255,255,255,.04);
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 3px 8px;
      max-width: 160px;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      flex-shrink: 0;
    }

    .open-btn {
      background: var(--accent);
      color: #000;
      border: none;
      border-radius: 8px;
      padding: 8px 16px;
      font-family: 'Syne', sans-serif;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      flex-shrink: 0;
      transition: opacity .15s, transform .15s;
    }
    .open-btn:hover { opacity: .85; transform: scale(1.03); }

    /* ── Empty state ── */
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      border: 1px dashed var(--border);
      border-radius: var(--radius);
      color: var(--muted);
    }
    .empty-state .icon { font-size: 36px; margin-bottom: 12px; }
    .empty-state p { font-size: 14px; line-height: 1.6; }
    .empty-state code {
      font-family: 'DM Mono', monospace;
      font-size: 12px;
      background: rgba(255,255,255,.06);
      padding: 2px 6px;
      border-radius: 4px;
    }

    /* ── Manual test panel ── */
    .manual-panel {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 20px;
    }
    .manual-panel p {
      font-size: 13px;
      color: var(--muted);
      margin-bottom: 14px;
      line-height: 1.6;
    }
    .input-row {
      display: flex; gap: 10px; align-items: center;
    }
    .input-row input {
      flex: 1;
      background: rgba(255,255,255,.05);
      border: 1px solid var(--border);
      border-radius: 8px;
      padding: 10px 14px;
      font-family: 'DM Mono', monospace;
      font-size: 13px;
      color: var(--text);
      outline: none;
      transition: border-color .15s;
    }
    .input-row input:focus { border-color: var(--accent); }
    .input-row input::placeholder { color: var(--muted); }
    .input-row button {
      background: var(--accent);
      color: #000;
      border: none;
      border-radius: 8px;
      padding: 10px 18px;
      font-family: 'Syne', sans-serif;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      white-space: nowrap;
      transition: opacity .15s;
    }
    .input-row button:hover { opacity: .85; }

    /* ── Divider ── */
    .divider {
      border: none;
      border-top: 1px solid var(--border);
      margin: 32px 0;
    }

    /* ── Warning strip ── */
    .warning {
      display: flex; align-items: flex-start; gap: 10px;
      background: rgba(224,82,82,.08);
      border: 1px solid rgba(224,82,82,.2);
      border-radius: 8px;
      padding: 12px 16px;
      margin-bottom: 32px;
      font-size: 13px;
      color: #e08080;
      line-height: 1.5;
    }
    .warning .icon { flex-shrink: 0; font-size: 15px; margin-top: 1px; }
  </style>
</head>
<body>
<div class="wrap">

  <header>
    <div class="badge">⚙ Dev Tool</div>
    <h1>Event <span>Gallery</span> Tester</h1>
    <p class="subtitle">// temporary test page — remove before production</p>
  </header>

  <div class="warning">
    <span class="icon">⚠</span>
    This page has no login gate. Do not leave it on a public server. Delete it once you're done testing.
  </div>

  <?php if (empty($events)): ?>

    <div class="empty-state">
      <div class="icon">🗂</div>
      <p>
        No events with a linked OneDrive folder found.<br>
        Make sure your <code>events</code> table has rows where<br>
        <code>memory_path</code> is set to a OneDrive folder ID.
      </p>
    </div>

  <?php else: ?>

    <div class="section-label">Events with OneDrive folder</div>
    <div class="event-list">
      <?php foreach ($events as $ev): ?>
        <div class="event-row" onclick="launch(<?= (int)$ev['event_id'] ?>, <?= htmlspecialchars(json_encode($ev['event_name']), ENT_QUOTES) ?>)">
          <div class="event-icon">📁</div>
          <div class="event-info">
            <div class="event-name"><?= htmlspecialchars($ev['event_name']) ?></div>
            <div class="event-meta">
              ID <?= (int)$ev['event_id'] ?>
              <?php if ($ev['event_date']): ?>
                &nbsp;·&nbsp; <?= htmlspecialchars($ev['event_date']) ?>
              <?php endif; ?>
            </div>
          </div>
          <div class="event-folder-id" title="<?= htmlspecialchars($ev['memory_path']) ?>">
            <?= htmlspecialchars($ev['memory_path']) ?>
          </div>
          <button class="open-btn" onclick="event.stopPropagation(); launch(<?= (int)$ev['event_id'] ?>, <?= htmlspecialchars(json_encode($ev['event_name']), ENT_QUOTES) ?>)">
            Open ↗
          </button>
        </div>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>

  <hr class="divider">

  <div class="section-label">Manual test — paste any event ID</div>
  <div class="manual-panel">
    <p>Use this if your event isn't showing above, or if you want to test a raw folder ID directly without a DB row.</p>
    <div class="input-row">
      <input type="number" id="manual-id" placeholder="event_id (e.g. 42)" min="1">
      <button onclick="launchManual()">Load Gallery</button>
    </div>
  </div>

</div>

<?php
// Inline the gallery HTML — same directory as this file
$galleryFile = __DIR__ . '/event_gallery.html';
if (file_exists($galleryFile)) {
    readfile($galleryFile);
} else {
    echo '<p style="color:#e05252;text-align:center;margin-top:32px;">⚠ event_gallery.html not found in ' . htmlspecialchars(__DIR__) . '</p>';
}
?>

<script>
  function launch(eventId, title) {
    document.querySelectorAll('.event-row').forEach(r => r.classList.remove('selected'));
    event.currentTarget?.classList.add('selected');
    openEventGallery(eventId, title || 'Event Gallery');
  }

  function launchManual() {
    const id = parseInt(document.getElementById('manual-id').value, 10);
    if (!id || id < 1) {
      alert('Enter a valid event_id first.');
      return;
    }
    openEventGallery(id, 'Event #' + id);
  }

  // Also allow pressing Enter in the manual input
  document.getElementById('manual-id').addEventListener('keydown', e => {
    if (e.key === 'Enter') launchManual();
  });
</script>
</body>
</html>