<?php
// ─── festivals.php ────────────────────────────────────────────────────
require_once 'db/db_hosted.php';
require_once 'api/auth.php';
require_once 'imports_logic.php';

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

// ─── Handle get next festival ID (AJAX POST) ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_next_festival_id') {
    header('Content-Type: application/json');
    try {
        $pdo->exec("CALL sp_get_next_festival_id(@next_id)");
        $next = $pdo->query("SELECT @next_id AS next_id")->fetchColumn();
        echo json_encode(['success' => true, 'next_id' => (int)$next]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => htmlspecialchars($e->getMessage())]);
    }
    exit;
}

// ─── Handle setlist data (AJAX GET) ──────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'setlist_data') {
    header('Content-Type: application/json');
    $fid = isset($_GET['festival_id']) ? (int)$_GET['festival_id'] : null;

    if (!$fid) {
        echo json_encode(['error' => 'No festival_id supplied.']);
        exit;
    }

    try {
        // 1. Config + name
        $stmt = $pdo->prepare(
            "SELECT il.valid_days, il.stage_format,
                    CONCAT(e.event_Year, ' ', e.event_Name) AS festival_name
               FROM import_logic il
               JOIN event e ON il.festival_id = e.festival_ID
              WHERE il.festival_id = :fid LIMIT 1"
        );
        $stmt->execute([':fid' => $fid]);
        $logic = $stmt->fetch();

        if (!$logic) {
            echo json_encode(['error' => "No import_logic record found for festival ID {$fid}."]);
            exit;
        }

        $festivalName = $logic['festival_name'] ?? "Festival #{$fid}";
        $rawDays  = json_decode($logic['valid_days'],   true) ?: [];
        $rawStages = json_decode($logic['stage_format'], true) ?: [];
        $days = array_map('ucfirst', $rawDays);

        uasort($rawStages, fn($a, $b) => (int)($a['order'] ?? 99) <=> (int)($b['order'] ?? 99));
        $stageFormat = [];
        foreach ($rawStages as $key => $meta) {
            $stageFormat[ucfirst(strtolower($key))] = $meta;
        }

        // 2. Transactions
        $stmt2 = $pdo->prepare(
            "SELECT * FROM festival_transactions
              WHERE festival_ID = :fid ORDER BY day ASC, start_Time ASC"
        );
        $stmt2->execute([':fid' => $fid]);
        $transactions = $stmt2->fetchAll();

        // 3. Preferences
        $stmt3 = $pdo->prepare(
            "SELECT trans_ID, viewer, want, need FROM festival_preferences WHERE festival_ID = :fid"
        );
        $stmt3->execute([':fid' => $fid]);
        $prefsByTrans = [];
        $viewersMap   = [];
        foreach ($stmt3->fetchAll() as $pref) {
            $type = $pref['need'] ? 'need' : ($pref['want'] ? 'want' : null);
            if ($type) {
                $prefsByTrans[$pref['trans_ID']][$pref['viewer']] = $type;
                $viewersMap[$pref['viewer']] = true;
            }
        }
        ksort($viewersMap);
        $viewers = array_keys($viewersMap);

        // 4. Build schedule [day][stage] => [tx, ...]
        $schedule = [];
        foreach ($days as $day) {
            $schedule[$day] = [];
            foreach (array_keys($stageFormat) as $stage) $schedule[$day][$stage] = [];
        }

        // Helper: parse time string to minutes
        $parseMinutes = function(string $t): int {
            $t = strtoupper(trim($t));
            if (preg_match('/(\d+):(\d+)\s*([AP])M?/', $t, $m)) {
                $h = (int)$m[1] % 12; $min = (int)$m[2];
                if ($m[3] === 'P') $h += 12;
                $mins = $h * 60 + $min;
                return $mins < 360 ? $mins + 1440 : $mins;
            }
            return 0;
        };

        foreach ($transactions as $tx) {
            $txDay   = ucfirst(strtolower($tx['day']   ?? ''));
            $txStage = ucfirst(strtolower($tx['stage'] ?? ''));
            if (isset($schedule[$txDay][$txStage])) {
                $row = [
                    'performer'  => $tx['performer']  ?? '',
                    'start_Time' => $tx['start_Time'] ?? '',
                    'end_Time'   => $tx['end_Time']   ?? '',
                    'notes'      => $tx['notes']      ?? '',
                    'prefs'      => $prefsByTrans[$tx['ID']] ?? [],
                ];
                $schedule[$txDay][$txStage][] = $row;
            }
        }
        foreach ($schedule as $day => &$stages) {
            foreach ($stages as $stage => &$bands) {
                usort($bands, fn($a, $b) =>
                    $parseMinutes($b['start_Time']) <=> $parseMinutes($a['start_Time'])
                );
            }
        }
        unset($stages, $bands);

        echo json_encode([
            'festival_id'       => $fid,
            'festival_name'     => $festivalName,
            'days'              => $days,
            'stage_format'      => $stageFormat,
            'schedule'          => $schedule,
            'viewers'           => $viewers,
            'transaction_count' => count($transactions),
        ]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . htmlspecialchars($e->getMessage())]);
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

$currentPage        = 'festivals';
$pageTitle          = 'Festivals';
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

    /* ── Global festival selector ────────────────────────────── */
    .global-festival-select-wrap {
      position: relative;
      display: flex;
      align-items: center;
      gap: 0;
    }

    .gfs-icon {
      position: absolute;
      left: 11px;
      width: 15px;
      height: 15px;
      color: var(--muted);
      pointer-events: none;
      z-index: 1;
    }

    .global-festival-select-wrap select {
      appearance: none;
      -webkit-appearance: none;
      background: var(--input-bg);
      border: 0.5px solid var(--border-strong);
      border-radius: 10px;
      padding: 9px 36px 9px 32px;
      font-size: 14px;
      font-weight: 500;
      font-family: inherit;
      color: var(--text);
      cursor: pointer;
      min-width: 280px;
      outline: none;
      transition: border-color 0.15s, box-shadow 0.15s;
    }

    .global-festival-select-wrap select:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 15%, transparent);
    }

    .global-festival-select-wrap select:hover {
      border-color: var(--accent);
    }

    .gfs-chevron {
      position: absolute;
      right: 10px;
      width: 14px;
      height: 14px;
      color: var(--muted);
      pointer-events: none;
    }

    /* ── Gated (disabled) tabs ────────────────────────────────── */
    .tab-btn.tab-gated:disabled {
      opacity: 0.35;
      cursor: not-allowed;
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

    /* ── Festival ID row with Generate button ─────────────────── */
    .festival-id-wrap {
      display: flex;
      gap: 8px;
      align-items: center;
    }

    .festival-id-wrap input {
      flex: 1;
    }

    .btn-generate {
      padding: 9px 14px;
      border: 0.5px solid var(--border-strong);
      border-radius: 8px;
      background: var(--input-bg);
      color: var(--text);
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      white-space: nowrap;
      transition: opacity 0.15s;
      flex-shrink: 0;
    }

    .btn-generate:hover:not(:disabled) { opacity: 0.75; }
    .btn-generate:disabled { opacity: 0.35; cursor: not-allowed; }

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
  require 'nav.php';;
?>

<div class="festivals-wrap">

  <!-- ── Page header with global festival selector ── -->
  <div class="festivals-header">
    <div class="global-festival-select-wrap">
      <svg class="gfs-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
        <circle cx="10" cy="10" r="8"/>
        <path d="M10 6v4l2.5 2.5"/>
      </svg>
      <select id="globalFestivalSelect">
        <option value="">🎪 — Select a Festival —</option>
        <?php foreach ($festivals as $f): ?>
          <option
            value="<?= htmlspecialchars($f['festival_ID']) ?>"
            data-name="<?= htmlspecialchars($f['event_Name']) ?>"
            data-year="<?= htmlspecialchars($f['event_Year']) ?>"
          >
            <?= htmlspecialchars($f['event_Year']) ?> || <?= htmlspecialchars($f['event_Name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <svg class="gfs-chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
        <polyline points="4,6 8,10 12,6"/>
      </svg>
    </div>
  </div>

  <!-- ── Tab bar ── -->
  <div class="tab-bar" role="tablist">
    <button class="tab-btn active"   role="tab" data-tab="list"    aria-selected="true">Festival List</button>
    <button class="tab-btn tab-gated" role="tab" data-tab="imports" aria-selected="false" disabled title="Select a festival first">Import</button>
    <button class="tab-btn tab-gated" role="tab" data-tab="setlist" aria-selected="false" disabled title="Select a festival first">Set List</button>
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

  <!-- ── Tab 2: Imports ── -->
  <div class="tab-panel" id="panel-imports" role="tabpanel">
    <?php include 'imports_partial.php'; ?>
  </div>

  <!-- ── Tab 3: Set List ── -->
  <div class="tab-panel" id="panel-setlist" role="tabpanel">
    <?php include 'RockvilleSetList.php'; ?>
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
      <div class="festival-id-wrap">
        <input type="text" id="festivalIdInput" placeholder="e.g. 101" autocomplete="off">
        <button type="button" id="generateIdBtn" class="btn-generate" disabled>Generate</button>
      </div>
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

  // ── Global festival selection ──────────────────────────────────
  const globalFestivalSelect = document.getElementById('globalFestivalSelect');
  const gatedTabs = document.querySelectorAll('.tab-btn.tab-gated');

  // Expose selected festival globally so imports_partial can read it
  window.selectedFestival = { id: '', name: '', year: '' };

  function applyFestivalSelection() {
    const opt    = globalFestivalSelect.selectedOptions[0];
    const hasVal = !!globalFestivalSelect.value;

    window.selectedFestival = hasVal ? {
      id:   globalFestivalSelect.value,
      name: opt.dataset.name || '',
      year: opt.dataset.year || ''
    } : { id: '', name: '', year: '' };

    // Enable / disable gated tabs
    gatedTabs.forEach(btn => {
      btn.disabled = !hasVal;
      btn.title    = hasVal ? '' : 'Select a festival first';
    });

    // If a gated tab is currently active and selection is cleared, fall back to list
    const activeBtn = document.querySelector('.tab-btn.active');
    if (!hasVal && activeBtn && activeBtn.classList.contains('tab-gated')) {
      switchTab('list');
    }

    // Notify imports_partial and setlist if listening
    document.dispatchEvent(new CustomEvent('festivalChanged', { detail: window.selectedFestival }));
  }

  globalFestivalSelect.addEventListener('change', applyFestivalSelection);
  applyFestivalSelection(); // run once on load

  // ── Tab switching ──────────────────────────────────────────────
  function switchTab(tabName) {
    document.querySelectorAll('.tab-btn').forEach(b => {
      b.classList.remove('active');
      b.setAttribute('aria-selected', 'false');
    });
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    const btn   = document.querySelector(`.tab-btn[data-tab="${tabName}"]`);
    const panel = document.getElementById('panel-' + tabName);
    if (btn)   { btn.classList.add('active');   btn.setAttribute('aria-selected', 'true'); }
    if (panel) { panel.classList.add('active'); }
  }

  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      if (btn.disabled) return;
      switchTab(btn.dataset.tab);
    });
  });

  // ── Modal open / close ─────────────────────────────────────────
  const modal       = document.getElementById('addFestModal');
  const eventSelect = document.getElementById('eventSelect');
  const festInput   = document.getElementById('festivalIdInput');
  const saveBtn     = document.getElementById('saveBtn');
  const generateBtn = document.getElementById('generateIdBtn');
  const hint        = document.getElementById('modalHint');

  function openModal() {
    eventSelect.value     = '';
    festInput.value       = '';
    hint.textContent      = '';
    hint.className        = 'modal-hint';
    saveBtn.disabled      = true;
    generateBtn.disabled  = true;
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
      festInput.value      = '';
      hint.textContent     = '';
      hint.className       = 'modal-hint';
      saveBtn.disabled     = true;
      generateBtn.disabled = true;
      return;
    }

    const existing   = opt.dataset.festival || '';
    festInput.value  = existing;
    saveBtn.disabled = false;
    generateBtn.disabled = false;

    if (existing) {
      hint.textContent = '✔ Existing ID pre-filled — edit to change.';
      hint.className   = 'modal-hint prefilled';
    } else {
      hint.textContent = 'No Festival ID set yet. Enter one above or generate.';
      hint.className   = 'modal-hint';
    }

    festInput.focus();
  });

  festInput.addEventListener('input', () => {
    saveBtn.disabled = !eventSelect.value;
  });

  // ── Generate next festival ID ──────────────────────────────────
  generateBtn.addEventListener('click', async () => {
    generateBtn.disabled    = true;
    generateBtn.textContent = '…';

    try {
      const form = new FormData();
      form.append('action', 'get_next_festival_id');

      const res  = await fetch('festivals.php', { method: 'POST', body: form });
      const data = await res.json();

      if (data.success) {
        festInput.value  = data.next_id;
        hint.textContent = '✔ Next available ID generated.';
        hint.className   = 'modal-hint prefilled';
        saveBtn.disabled = false;
      } else {
        hint.textContent = data.error || 'Could not generate ID.';
        hint.className   = 'modal-hint';
      }
    } catch (err) {
      hint.textContent = 'Network error.';
      hint.className   = 'modal-hint';
    }

    generateBtn.disabled    = false;
    generateBtn.textContent = 'Generate';
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