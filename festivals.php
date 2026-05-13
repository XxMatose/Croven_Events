<?php
// ─── festivals.php ────────────────────────────────────────────────────
require_once 'db/db_hosted.php';
require_once 'api/auth.php';

// ─── Handle save (AJAX POST) ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_festival_id') {
    header('Content-Type: application/json');
    $eventName  = trim($_POST['event_Name']  ?? '');
    $festivalId = trim($_POST['festival_ID'] ?? '');

    if ($eventName === '') {
        echo json_encode(['success' => false, 'error' => 'Event name is required.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE event SET festival_ID = ? WHERE event_Name = ?");
        $stmt->execute([$festivalId === '' ? null : $festivalId, $eventName]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => htmlspecialchars($e->getMessage())]);
    }
    exit;
}

// ─── Load festival list ───────────────────────────────────────────────
$festivals = [];
try {
    $festivals = $pdo->query("SELECT festival_ID, event_Name, event_Year, event_StartDate, event_EndDate FROM vw_festival_list ORDER BY event_StartDate")->fetchAll();
} catch (Exception $e) { /* handled in view */ }

// ─── Load event dropdown ──────────────────────────────────────────────
$events = [];
try {
    $events = $pdo->query("SELECT event_Year, event_Name, MIN(festival_ID) AS festival_ID FROM vw_full_event GROUP BY event_Year, event_Name ORDER BY event_Year, event_Name;")->fetchAll();
} catch (Exception $e) { /* handled in view */ }

$currentPage = 'festivals';
$pageTitle   = 'Festivals';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Festivals — Croven Events</title>
  <link rel="stylesheet" href="css/styles.css">
  <style>
    /* ── Page wrapper ─────────────────────────────────────────── */
    .festivals-wrap {
      max-width: 1200px;
      margin: 0 auto;
    }

    .festivals-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 1.5rem;
    }

    .festivals-title {
      font-size: 18px;
      font-weight: 600;
    }

    /* ── Tabs ─────────────────────────────────────────────────── */
    .tab-bar {
      display: flex;
      gap: 2px;
      border-bottom: 0.5px solid var(--border);
      margin-bottom: 1.5rem;
    }

    .tab-btn {
      background: none;
      border: none;
      border-bottom: 2px solid transparent;
      margin-bottom: -1px;
      padding: 9px 18px;
      font-size: 13px;
      font-weight: 500;
      color: var(--muted);
      cursor: pointer;
      transition: color 0.15s, border-color 0.15s;
      white-space: nowrap;
    }

    .tab-btn:hover { color: var(--text); }

    .tab-btn.active {
      color: var(--accent);
      border-bottom-color: var(--accent);
    }

    .tab-panel { display: none; }
    .tab-panel.active { display: block; }

    /* ── Toolbar above list ───────────────────────────────────── */
    .list-toolbar {
      background: var(--card-bg);
      border: 0.5px solid var(--border);
      border-radius: 12px;
      padding: 1rem 1.25rem;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }

    .list-count {
      font-size: 12px;
      background: var(--input-bg);
      border: 0.5px solid var(--border);
      border-radius: 20px;
      padding: 4px 12px;
      color: var(--muted);
      white-space: nowrap;
    }

    .spacer { flex: 1; }

    .btn-add {
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 8px 16px;
      background: var(--accent);
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      transition: opacity 0.15s;
      white-space: nowrap;
    }

    .btn-add:hover { opacity: 0.85; }

    /* ── Festival table ───────────────────────────────────────── */
    .table-container {
      background: var(--card-bg);
      border: 0.5px solid var(--border);
      border-radius: 12px;
      overflow: hidden;
    }

    .table-scroll { overflow-x: auto; }

    .festival-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }

    .festival-table thead {
      background: var(--input-bg);
      border-bottom: 0.5px solid var(--border);
    }

    .festival-table th {
      padding: 10px 14px;
      text-align: left;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--muted);
      white-space: nowrap;
    }

    .festival-table td {
      padding: 11px 14px;
      border-bottom: 0.5px solid var(--border);
      vertical-align: middle;
      font-size: 13px;
    }

    .festival-table tr:last-child td { border-bottom: none; }

    .festival-table tbody tr:hover td {
      background: color-mix(in srgb, var(--accent) 4%, transparent);
    }

    .badge-id {
      display: inline-block;
      background: color-mix(in srgb, var(--accent) 12%, transparent);
      color: var(--accent);
      border: 0.5px solid color-mix(in srgb, var(--accent) 30%, transparent);
      border-radius: 5px;
      padding: 2px 8px;
      font-size: 11px;
      font-weight: 600;
      letter-spacing: 0.03em;
    }

    .badge-empty {
      display: inline-block;
      background: var(--input-bg);
      color: var(--muted);
      border: 0.5px solid var(--border);
      border-radius: 5px;
      padding: 2px 8px;
      font-size: 11px;
    }

    /* ── Empty state ──────────────────────────────────────────── */
    .admin-empty {
      text-align: center;
      padding: 3rem 1rem;
      color: var(--muted);
    }

    .admin-empty-icon {
      font-size: 36px;
      margin-bottom: 10px;
    }

    /* ── Placeholder panels ───────────────────────────────────── */
    .placeholder-panel {
      background: var(--card-bg);
      border: 0.5px dashed var(--border);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 200px;
      color: var(--muted);
      font-size: 13px;
      gap: 8px;
    }

    /* ── Modal ────────────────────────────────────────────────── */
    .modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }

    .modal-overlay.open { display: flex; }

    .fest-modal {
      background: var(--card-bg);
      border: 0.5px solid var(--border);
      border-radius: 14px;
      padding: 1.5rem;
      width: 440px;
      max-width: 95vw;
      box-shadow: 0 12px 40px rgba(0,0,0,0.3);
    }

    .fest-modal h3 {
      font-size: 15px;
      font-weight: 600;
      margin-bottom: 4px;
    }

    .modal-subtitle {
      font-size: 12px;
      color: var(--muted);
      margin-bottom: 1.25rem;
    }

    .modal-field { margin-bottom: 14px; }

    .modal-field label {
      display: block;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--muted);
      margin-bottom: 5px;
    }

    .modal-field select,
    .modal-field input {
      width: 100%;
      padding: 9px 12px;
      border: 0.5px solid var(--border-strong);
      border-radius: 8px;
      font-size: 14px;
      background: var(--input-bg);
      color: var(--text);
      outline: none;
      font-family: inherit;
      appearance: none;
      -webkit-appearance: none;
    }

    .modal-field select:focus,
    .modal-field input:focus { border-color: var(--accent); }

    .select-wrap { position: relative; }

    .select-wrap svg {
      position: absolute;
      right: 10px; top: 50%;
      transform: translateY(-50%);
      width: 14px; height: 14px;
      opacity: 0.4;
      pointer-events: none;
    }

    .modal-hint {
      font-size: 11px;
      color: var(--muted);
      margin-top: 5px;
    }

    .modal-hint.prefilled { color: #22c55e; }

    .modal-actions {
      display: flex;
      gap: 8px;
      justify-content: flex-end;
      margin-top: 1.25rem;
    }

    .btn-modal-cancel {
      padding: 8px 16px;
      border: 0.5px solid var(--border-strong);
      border-radius: 8px;
      background: var(--input-bg);
      color: var(--muted);
      font-size: 13px;
      cursor: pointer;
      transition: opacity 0.15s;
    }

    .btn-modal-cancel:hover { opacity: 0.75; }

    .btn-modal-save {
      padding: 8px 18px;
      border-radius: 8px;
      background: var(--accent);
      color: #fff;
      border: none;
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      transition: opacity 0.15s;
    }

    .btn-modal-save:hover    { opacity: 0.85; }
    .btn-modal-save:disabled { opacity: 0.35; cursor: not-allowed; }

    /* ── Toast ────────────────────────────────────────────────── */
    #festToast {
      position: fixed;
      bottom: 24px;
      right: 24px;
      padding: 10px 18px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 500;
      z-index: 9999;
      opacity: 0;
      transform: translateY(8px);
      transition: opacity 0.2s ease, transform 0.2s ease;
      pointer-events: none;
    }

    #festToast.show    { opacity: 1; transform: translateY(0); }
    #festToast.success { background: #22c55e; color: #fff; }
    #festToast.error   { background: #ef4444; color: #fff; }
  </style>
</head>
<body>

<?php
  $currentPage = 'festivals';
  $pageTitle   = 'Festivals';
  require 'nav.php';
?>

<div class="festivals-wrap">

  <!-- ── Page header ── -->
  <div class="festivals-header">
    <div class="festivals-title">🎪 Festivals</div>
  </div>

  <!-- ── Tab bar ── -->
  <div class="tab-bar" role="tablist">
    <button class="tab-btn active" role="tab" data-tab="list"     aria-selected="true">Festival List</button>
    <button class="tab-btn"        role="tab" data-tab="overview" aria-selected="false">Overview</button>
    <button class="tab-btn"        role="tab" data-tab="reports"  aria-selected="false">Reports</button>
  </div>

  <!-- ── Tab 1: Festival list ── -->
  <div class="tab-panel active" id="panel-list" role="tabpanel">

    <div class="list-toolbar">
      <span class="list-count" id="rowCountBadge">
        <?= count($festivals) ?> festival<?= count($festivals) !== 1 ? 's' : '' ?>
      </span>
      <div class="spacer"></div>
      <button class="btn-add" id="openModalBtn">＋ Add Festival</button>
    </div>

    <div class="table-container">
      <?php if (!empty($festivals)): ?>
      <div class="table-scroll">
        <table class="festival-table">
          <thead>
            <tr>
              <th>Festival ID</th>
              <th>Event Name</th>
              <th>Year</th>
              <th>Start Date</th>
              <th>End Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($festivals as $f): ?>
            <tr>
              <td>
                <?php if (!empty($f['festival_ID'])): ?>
                  <span class="badge-id"><?= htmlspecialchars($f['festival_ID']) ?></span>
                <?php else: ?>
                  <span class="badge-empty">—</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($f['event_Name']      ?? '—') ?></td>
              <td><?= htmlspecialchars($f['event_Year']      ?? '—') ?></td>
              <td><?= htmlspecialchars($f['event_StartDate'] ?? '—') ?></td>
              <td><?= htmlspecialchars($f['event_EndDate']   ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div class="admin-empty">
        <div class="admin-empty-icon">🎪</div>
        <p>No festivals found. Add one to get started.</p>
      </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- ── Tab 2: Placeholder ── -->
  <div class="tab-panel" id="panel-overview" role="tabpanel">
    <div class="placeholder-panel">🚧 Overview — coming soon</div>
  </div>

  <!-- ── Tab 3: Placeholder ── -->
  <div class="tab-panel" id="panel-reports" role="tabpanel">
    <div class="placeholder-panel">🚧 Reports — coming soon</div>
  </div>

</div><!-- /festivals-wrap -->

<!-- ── Add Festival Modal ── -->
<div class="modal-overlay" id="addFestModal">
  <div class="fest-modal">
    <h3>Add / Update Festival</h3>
    <p class="modal-subtitle">Select an event and assign or update its Festival ID.</p>

    <div class="modal-field">
      <label for="eventSelect">Event Name</label>
      <div class="select-wrap">
        <select id="eventSelect">
          <option value="">— Choose an event —</option>
          <?php foreach ($events as $ev): ?>
            <option value="<?= htmlspecialchars($ev['event_Name']) ?>"
                    data-festival="<?= htmlspecialchars($ev['festival_ID'] ?? '') ?>">
               <?= htmlspecialchars($ev['event_Year']) ?> || <?= htmlspecialchars($ev['event_Name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
          <polyline points="4,6 8,10 12,6"/>
        </svg>
      </div>
    </div>

    <div class="modal-field">
      <label for="festivalIdInput">Festival ID</label>
      <input type="text" id="festivalIdInput" placeholder="e.g. FEST-2025" autocomplete="off">
      <div class="modal-hint" id="modalHint"></div>
    </div>

    <div class="modal-actions">
      <button class="btn-modal-cancel" id="cancelBtn">Cancel</button>
      <button class="btn-modal-save"   id="saveBtn" disabled>Save</button>
    </div>
  </div>
</div>

<!-- ── Toast ── -->
<div id="festToast"></div>

<script>
(function () {

  // ── Tab switching ──────────────────────────────────────────────
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('active');
        b.setAttribute('aria-selected', 'false');
      });
      document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
      btn.classList.add('active');
      btn.setAttribute('aria-selected', 'true');
      document.getElementById('panel-' + btn.dataset.tab).classList.add('active');
    });
  });

  // ── Modal open / close ─────────────────────────────────────────
  const modal       = document.getElementById('addFestModal');
  const eventSelect = document.getElementById('eventSelect');
  const festInput   = document.getElementById('festivalIdInput');
  const saveBtn     = document.getElementById('saveBtn');
  const hint        = document.getElementById('modalHint');

  function openModal() {
    eventSelect.value = '';
    festInput.value   = '';
    hint.textContent  = '';
    hint.className    = 'modal-hint';
    saveBtn.disabled  = true;
    modal.classList.add('open');
    setTimeout(() => eventSelect.focus(), 120);
  }

  function closeModal() {
    modal.classList.remove('open');
  }

  document.getElementById('openModalBtn').addEventListener('click', openModal);
  document.getElementById('cancelBtn').addEventListener('click', closeModal);
  modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

  // ── Pre-fill festival ID when event selected ───────────────────
  eventSelect.addEventListener('change', () => {
    const opt = eventSelect.selectedOptions[0];
    if (!opt || !opt.value) {
      festInput.value  = '';
      hint.textContent = '';
      hint.className   = 'modal-hint';
      saveBtn.disabled = true;
      return;
    }

    const existing   = opt.dataset.festival || '';
    festInput.value  = existing;
    saveBtn.disabled = false;

    if (existing) {
      hint.textContent = '✔ Existing ID pre-filled — edit to change.';
      hint.className   = 'modal-hint prefilled';
    } else {
      hint.textContent = 'No Festival ID set yet. Enter one above.';
      hint.className   = 'modal-hint';
    }

    festInput.focus();
  });

  festInput.addEventListener('input', () => {
    saveBtn.disabled = !eventSelect.value;
  });

  // ── Save ───────────────────────────────────────────────────────
  saveBtn.addEventListener('click', async () => {
    const eventName  = eventSelect.value;
    const festivalId = festInput.value.trim();
    if (!eventName) return;

    saveBtn.disabled    = true;
    saveBtn.textContent = 'Saving…';

    try {
      const form = new FormData();
      form.append('action',      'save_festival_id');
      form.append('event_Name',  eventName);
      form.append('festival_ID', festivalId);

      const res  = await fetch('festivals.php', { method: 'POST', body: form });
      const data = await res.json();

      if (data.success) {
        toast('Festival ID saved successfully.', 'success');
        closeModal();
        setTimeout(() => location.reload(), 900);
      } else {
        toast(data.error || 'Save failed. Please try again.', 'error');
      }
    } catch (err) {
      toast('Network error. Please try again.', 'error');
    }

    saveBtn.disabled    = false;
    saveBtn.textContent = 'Save';
  });

  // ── Toast ──────────────────────────────────────────────────────
  let toastTimer;
  function toast(msg, type = 'success') {
    const el       = document.getElementById('festToast');
    el.textContent = msg;
    el.className   = `show ${type}`;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { el.className = ''; }, 2800);
  }

})();
</script>

</body>
</html>