<?php
require_once 'db.php';
require_once 'auth.php';

// ─── Fetch all user tables (exclude views and system tables) ──────────
$tablesStmt = $pdo->query("
    SELECT TABLE_NAME
    FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_TYPE = 'BASE TABLE'
    ORDER BY TABLE_NAME
");
$tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

// ─── Selected table ──────────────────────────────────────────────────
$selectedTable = $_GET['table'] ?? '';
$tableRows     = [];
$tableColumns  = [];
$primaryKey    = 'id'; // fallback

if ($selectedTable !== '' && in_array($selectedTable, $tables)) {

    // Get columns + detect primary key
    $colStmt = $pdo->prepare("
        SELECT COLUMN_NAME, DATA_TYPE, COLUMN_KEY, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
        ORDER BY ORDINAL_POSITION
    ");
    $colStmt->execute([$selectedTable]);
    $columnMeta = $colStmt->fetchAll();

    foreach ($columnMeta as $col) {
        $tableColumns[] = $col['COLUMN_NAME'];
        if ($col['COLUMN_KEY'] === 'PRI') {
            $primaryKey = $col['COLUMN_NAME'];
        }
    }

    // Fetch rows (cap at 500 for safety)
    $safeTable = '`' . str_replace('`', '', $selectedTable) . '`';
    $rowStmt   = $pdo->query("SELECT * FROM {$safeTable} LIMIT 500");
    $tableRows = $rowStmt->fetchAll();
}

$columnMetaJson = json_encode($columnMeta ?? []);
$rowsJson       = json_encode($tableRows);
$tablesJson     = json_encode($tables);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin — Croven Events</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    /* ── Admin layout ─────────────────────────────────────── */
    .admin-wrap {
      max-width: 1200px;
      margin: 0 auto;
    }

    .admin-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 12px;
      margin-bottom: 1.5rem;
    }

    .admin-title {
      font-size: 18px;
      font-weight: 600;
    }

    /* ── Table selector ───────────────────────────────────── */
    .table-selector-bar {
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

    .table-selector-label {
      font-size: 13px;
      font-weight: 500;
      color: var(--muted);
      white-space: nowrap;
    }

    .table-select-wrap {
      position: relative;
      flex: 1;
      min-width: 160px;
      max-width: 280px;
    }

    .table-select {
      width: 100%;
      appearance: none;
      -webkit-appearance: none;
      background: var(--input-bg);
      border: 0.5px solid var(--border-strong);
      border-radius: 8px;
      padding: 8px 32px 8px 12px;
      font-size: 14px;
      color: var(--text);
      cursor: pointer;
      outline: none;
      transition: border-color 0.15s;
    }

    .table-select:focus { border-color: var(--accent); }

    .table-chevron {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      width: 14px;
      height: 14px;
      opacity: 0.4;
      pointer-events: none;
    }

    .table-row-count {
      font-size: 12px;
      background: var(--input-bg);
      border: 0.5px solid var(--border);
      border-radius: 20px;
      padding: 4px 12px;
      color: var(--muted);
      white-space: nowrap;
    }

    .btn-add-row {
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
      text-decoration: none;
    }

    .btn-add-row:hover { opacity: 0.85; }

    /* ── Table container ──────────────────────────────────── */
    .table-container {
      background: var(--card-bg);
      border: 0.5px solid var(--border);
      border-radius: 12px;
      overflow: hidden;
    }

    .table-scroll {
      overflow-x: auto;
    }

    .admin-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }

    .admin-table thead {
      background: var(--input-bg);
      border-bottom: 0.5px solid var(--border);
    }

    .admin-table th {
      padding: 10px 14px;
      text-align: left;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--muted);
      white-space: nowrap;
    }

    .admin-table td {
      padding: 0;
      border-bottom: 0.5px solid var(--border);
      vertical-align: middle;
    }

    .admin-table tr:last-child td {
      border-bottom: none;
    }

    .admin-table tr:hover td {
      background: color-mix(in srgb, var(--accent) 4%, transparent);
    }

    /* ── Editable cell ────────────────────────────────────── */
    .cell-input {
      display: block;
      width: 100%;
      padding: 9px 14px;
      background: transparent;
      border: none;
      color: var(--text);
      font-size: 13px;
      font-family: inherit;
      outline: none;
      min-width: 80px;
      transition: background 0.15s;
    }

    .cell-input:focus {
      background: color-mix(in srgb, var(--accent) 8%, var(--card-bg));
      border-radius: 4px;
    }

    .cell-input.pk-cell {
      color: var(--muted);
      font-size: 12px;
      font-family: monospace;
    }

    /* ── Actions column ───────────────────────────────────── */
    .col-actions {
      width: 100px;
      text-align: right;
      padding-right: 10px !important;
    }

    .th-actions {
      width: 100px;
      text-align: right;
      padding-right: 14px !important;
    }

    .row-actions {
      display: flex;
      gap: 6px;
      justify-content: flex-end;
      padding: 6px 10px;
    }

    .btn-save, .btn-delete {
      padding: 5px 10px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 500;
      border: none;
      cursor: pointer;
      transition: opacity 0.15s;
    }

    .btn-save {
      background: #22c55e;
      color: #fff;
      display: none; /* shown only when a cell is dirty */
    }

    .btn-save:hover   { opacity: 0.85; }
    .btn-save.visible { display: inline-flex; align-items: center; gap: 4px; }

    .btn-delete {
      background: var(--input-bg);
      border: 0.5px solid var(--border-strong);
      color: var(--muted);
    }

    .btn-delete:hover { background: #ef4444; color: #fff; border-color: #ef4444; }

    /* ── Empty / placeholder ──────────────────────────────── */
    .admin-empty {
      text-align: center;
      padding: 3rem 1rem;
      color: var(--muted);
    }

    .admin-empty-icon {
      font-size: 36px;
      margin-bottom: 10px;
    }

    /* ── Toast notification ───────────────────────────────── */
    #adminToast {
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

    #adminToast.show {
      opacity: 1;
      transform: translateY(0);
    }

    #adminToast.success { background: #22c55e; color: #fff; }
    #adminToast.error   { background: #ef4444; color: #fff; }

    /* ── Add row modal ────────────────────────────────────── */
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

    .admin-modal {
      background: var(--card-bg);
      border: 0.5px solid var(--border);
      border-radius: 14px;
      padding: 1.5rem;
      width: 480px;
      max-width: 95vw;
      max-height: 80vh;
      overflow-y: auto;
      box-shadow: 0 12px 40px rgba(0,0,0,0.3);
    }

    .admin-modal h3 {
      font-size: 15px;
      font-weight: 600;
      margin-bottom: 1.25rem;
    }

    .modal-field {
      margin-bottom: 14px;
    }

    .modal-field label {
      display: block;
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: var(--muted);
      margin-bottom: 5px;
    }

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
    }

    .modal-field input:focus { border-color: var(--accent); }

    .modal-field .pk-note {
      font-size: 11px;
      color: var(--muted);
      font-style: italic;
      padding: 6px 0;
    }

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
    }

    .btn-modal-insert {
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

    .btn-modal-insert:hover { opacity: 0.85; }

    /* ── Confirm delete modal ─────────────────────────────── */
    .confirm-modal {
      background: var(--card-bg);
      border: 0.5px solid var(--border);
      border-radius: 14px;
      padding: 1.5rem;
      width: 360px;
      max-width: 95vw;
      box-shadow: 0 12px 40px rgba(0,0,0,0.3);
      text-align: center;
    }

    .confirm-modal h3 { font-size: 15px; font-weight: 600; margin-bottom: 8px; }
    .confirm-modal p  { font-size: 13px; color: var(--muted); margin-bottom: 1.25rem; }

    .btn-confirm-delete {
      padding: 8px 18px;
      border-radius: 8px;
      background: #ef4444;
      color: #fff;
      border: none;
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
    }

    /* ── Search filter ────────────────────────────────────── */
    .search-col-wrap {
      display: flex;
      align-items: center;
      gap: 6px;
      flex: 1;
      min-width: 280px;
      max-width: 480px;
    }

    .search-col-select-wrap {
      position: relative;
      min-width: 130px;
      max-width: 180px;
      flex-shrink: 0;
    }

    .table-search-wrap {
      position: relative;
      flex: 1;
    }

    .table-search-icon {
      position: absolute;
      left: 10px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--muted);
      font-size: 13px;
      pointer-events: none;
    }

    #adminSearch {
      width: 100%;
      padding: 8px 10px 8px 30px;
      border: 0.5px solid var(--border-strong);
      border-radius: 8px;
      font-size: 13px;
      background: var(--input-bg);
      color: var(--text);
      outline: none;
    }

    #adminSearch:focus { border-color: var(--accent); }

    tr.hidden-row { display: none; }
  </style>
</head>
<body>

<?php
  $currentPage = 'admin';
  $pageTitle   = 'Admin';
  require 'nav.php';
?>

<div class="admin-wrap">

  <!-- ── Page header ── -->
  <div class="admin-header">
    <div class="admin-title">🛠️ Admin</div>
  </div>

  <!-- ── Table selector bar ── -->
  <div class="table-selector-bar">
    <span class="table-selector-label">Table</span>

    <div class="table-select-wrap">
      <select class="table-select" id="tableSelect" onchange="switchTable(this.value)">
        <option value="">— select a table —</option>
        <?php foreach ($tables as $t): ?>
          <option value="<?= htmlspecialchars($t) ?>"
            <?= $selectedTable === $t ? 'selected' : '' ?>>
            <?= htmlspecialchars($t) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <svg class="table-chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
        <polyline points="4,6 8,10 12,6"/>
      </svg>
    </div>

    <?php if ($selectedTable !== ''): ?>
      <span class="table-row-count" id="rowCountBadge">
        <?= count($tableRows) ?> row<?= count($tableRows) !== 1 ? 's' : '' ?>
      </span>

      <!-- ── Column selector + search input ── -->
      <div class="search-col-wrap">
        <div class="search-col-select-wrap">
          <select class="table-select" id="searchColSelect">
            <option value="">All columns</option>
            <?php foreach ($tableColumns as $col): ?>
              <option value="<?= htmlspecialchars($col) ?>"><?= htmlspecialchars($col) ?></option>
            <?php endforeach; ?>
          </select>
          <svg class="table-chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <polyline points="4,6 8,10 12,6"/>
          </svg>
        </div>
        <div class="table-search-wrap">
          <span class="table-search-icon">🔍</span>
          <input type="text" id="adminSearch" placeholder="Search…" oninput="filterRows(this.value)">
        </div>
      </div>

      <button class="btn-add-row" onclick="openAddModal()">＋ Add Row</button>
    <?php endif; ?>
  </div>

  <!-- ── Data table ── -->
  <?php if ($selectedTable === ''): ?>
    <div class="table-container">
      <div class="admin-empty">
        <div class="admin-empty-icon">🗄️</div>
        <p>Select a table above to view and edit its data.</p>
      </div>
    </div>

  <?php elseif (empty($tableRows) && empty($tableColumns)): ?>
    <div class="table-container">
      <div class="admin-empty">
        <div class="admin-empty-icon">📭</div>
        <p>No rows found in <strong><?= htmlspecialchars($selectedTable) ?></strong>.</p>
      </div>
    </div>

  <?php else: ?>
    <div class="table-container">
      <div class="table-scroll">
        <table class="admin-table" id="adminTable">
          <thead>
            <tr>
              <?php foreach ($tableColumns as $col): ?>
                <th><?= htmlspecialchars($col) ?></th>
              <?php endforeach; ?>
              <th class="th-actions">Actions</th>
            </tr>
          </thead>
          <tbody id="adminTbody">
            <?php foreach ($tableRows as $row): ?>
              <tr data-id="<?= htmlspecialchars($row[$primaryKey]) ?>" data-dirty="0">
                <?php foreach ($tableColumns as $col): ?>
                  <td>
                    <input
                      class="cell-input <?= $col === $primaryKey ? 'pk-cell' : '' ?>"
                      data-col="<?= htmlspecialchars($col) ?>"
                      value="<?= htmlspecialchars($row[$col] ?? '') ?>"
                      <?= $col === $primaryKey ? 'readonly tabindex="-1"' : '' ?>
                      oninput="markDirty(this)"
                    >
                  </td>
                <?php endforeach; ?>
                <td class="col-actions">
                  <div class="row-actions">
                    <button class="btn-save" onclick="saveRow(this)">✓ Save</button>
                    <button class="btn-delete" onclick="confirmDelete(this)">✕</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

</div><!-- /admin-wrap -->

<!-- ── Toast ── -->
<div id="adminToast"></div>

<!-- ── Add Row modal ── -->
<div class="modal-overlay" id="addRowModal">
  <div class="admin-modal">
    <h3>Add New Row</h3>
    <div id="addRowFields"></div>
    <div class="modal-actions">
      <button class="btn-modal-cancel" onclick="closeAddModal()">Cancel</button>
      <button class="btn-modal-insert" onclick="insertRow()">Insert</button>
    </div>
  </div>
</div>

<!-- ── Confirm delete modal ── -->
<div class="modal-overlay" id="confirmDeleteModal">
  <div class="confirm-modal">
    <h3>Delete Row?</h3>
    <p>This action cannot be undone.</p>
    <div class="modal-actions">
      <button class="btn-modal-cancel" onclick="closeConfirmDelete()">Cancel</button>
      <button class="btn-confirm-delete" onclick="executeDelete()">Delete</button>
    </div>
  </div>
</div>

<script>
// ── State ──────────────────────────────────────────────────────────────
const SELECTED_TABLE  = <?= json_encode($selectedTable) ?>;
const PRIMARY_KEY     = <?= json_encode($primaryKey) ?>;
const COLUMN_META     = <?= $columnMetaJson ?>;

let pendingDeleteRow  = null; // tr element pending deletion

// ── Switch table ──────────────────────────────────────────────────────
function switchTable(table) {
  const url = new URL(window.location.href);
  if (table) {
    url.searchParams.set('table', table);
  } else {
    url.searchParams.delete('table');
  }
  window.location.href = url.toString();
}

// ── Mark row dirty → show Save button ────────────────────────────────
function markDirty(input) {
  const tr = input.closest('tr');
  tr.dataset.dirty = '1';
  const saveBtn = tr.querySelector('.btn-save');
  saveBtn.classList.add('visible');
}

// ── Save row (UPDATE) ─────────────────────────────────────────────────
async function saveRow(btn) {
  const tr     = btn.closest('tr');
  const pkVal  = tr.dataset.id;
  const inputs = tr.querySelectorAll('.cell-input');

  const payload = { table: SELECTED_TABLE, pk: PRIMARY_KEY, pk_val: pkVal, data: {} };
  inputs.forEach(inp => {
    if (inp.dataset.col !== PRIMARY_KEY) {
      payload.data[inp.dataset.col] = inp.value;
    }
  });

  btn.textContent = '…';
  btn.disabled = true;

  try {
    const res  = await fetch('admin_api.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ action: 'update', ...payload })
    });
    const json = await res.json();

    if (json.success) {
      tr.dataset.dirty = '0';
      btn.classList.remove('visible');
      toast('Row updated', 'success');
    } else {
      toast(json.error || 'Update failed', 'error');
    }
  } catch (e) {
    toast('Network error', 'error');
  }

  btn.textContent = '✓ Save';
  btn.disabled = false;
}

// ── Delete flow ───────────────────────────────────────────────────────
function confirmDelete(btn) {
  pendingDeleteRow = btn.closest('tr');
  document.getElementById('confirmDeleteModal').classList.add('open');
}

function closeConfirmDelete() {
  pendingDeleteRow = null;
  document.getElementById('confirmDeleteModal').classList.remove('open');
}

async function executeDelete() {
  if (!pendingDeleteRow) return;
  const tr    = pendingDeleteRow;
  const pkVal = tr.dataset.id;

  closeConfirmDelete();

  try {
    const res  = await fetch('admin_api.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ action: 'delete', table: SELECTED_TABLE, pk: PRIMARY_KEY, pk_val: pkVal })
    });
    const json = await res.json();

    if (json.success) {
      tr.remove();
      updateRowCount();
      toast('Row deleted', 'success');
    } else {
      toast(json.error || 'Delete failed', 'error');
    }
  } catch (e) {
    toast('Network error', 'error');
  }
}

// ── Add row modal ─────────────────────────────────────────────────────
function openAddModal() {
  const container = document.getElementById('addRowFields');
  container.innerHTML = '';

  COLUMN_META.forEach(col => {
    const isPK = col.COLUMN_KEY === 'PRI';
    const div  = document.createElement('div');
    div.className = 'modal-field';

    if (isPK) {
      div.innerHTML = `
        <label>${escHtml(col.COLUMN_NAME)} <span style="opacity:.5">(PK)</span></label>
        <p class="pk-note">Auto-generated — leave blank.</p>
      `;
    } else {
      div.innerHTML = `
        <label>${escHtml(col.COLUMN_NAME)}</label>
        <input type="text" data-col="${escHtml(col.COLUMN_NAME)}"
          placeholder="${col.IS_NULLABLE === 'YES' ? 'NULL' : 'required'}">
      `;
    }

    container.appendChild(div);
  });

  document.getElementById('addRowModal').classList.add('open');
  container.querySelector('input')?.focus();
}

function closeAddModal() {
  document.getElementById('addRowModal').classList.remove('open');
}

async function insertRow() {
  const inputs  = document.querySelectorAll('#addRowFields input[data-col]');
  const rowData = {};
  inputs.forEach(inp => { rowData[inp.dataset.col] = inp.value; });

  try {
    const res  = await fetch('admin_api.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ action: 'insert', table: SELECTED_TABLE, data: rowData })
    });
    const json = await res.json();

    if (json.success) {
      closeAddModal();
      toast('Row inserted — reloading…', 'success');
      setTimeout(() => window.location.reload(), 800);
    } else {
      toast(json.error || 'Insert failed', 'error');
    }
  } catch (e) {
    toast('Network error', 'error');
  }
}

// ── Row filter ────────────────────────────────────────────────────────
function filterRows(query) {
  const q      = query.toLowerCase();
  const colSel = document.getElementById('searchColSelect');
  const selCol = colSel ? colSel.value : ''; // '' = all columns
  const rows   = document.querySelectorAll('#adminTbody tr');
  let visible  = 0;

  rows.forEach(tr => {
    let match;
    if (q === '') {
      match = true;
    } else if (selCol === '') {
      // Search across all cells
      match = tr.textContent.toLowerCase().includes(q);
    } else {
      // Search only the chosen column's input value
      const inp = tr.querySelector(`.cell-input[data-col="${CSS.escape(selCol)}"]`);
      match = inp ? inp.value.toLowerCase().includes(q) : false;
    }

    if (match) { tr.classList.remove('hidden-row'); visible++; }
    else        { tr.classList.add('hidden-row'); }
  });

  document.getElementById('rowCountBadge').textContent =
    visible + ' row' + (visible !== 1 ? 's' : '');
}

function updateRowCount() {
  const visible = document.querySelectorAll('#adminTbody tr:not(.hidden-row)').length;
  const badge   = document.getElementById('rowCountBadge');
  if (badge) badge.textContent = visible + ' row' + (visible !== 1 ? 's' : '');
}

// ── Toast ─────────────────────────────────────────────────────────────
let toastTimer;
function toast(msg, type = 'success') {
  const el = document.getElementById('adminToast');
  el.textContent = msg;
  el.className   = `show ${type}`;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { el.className = ''; }, 2800);
}

// ── Helpers ───────────────────────────────────────────────────────────
function escHtml(str) {
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Wire column selector to re-filter on change ───────────────────────
document.getElementById('searchColSelect')?.addEventListener('change', () => {
  filterRows(document.getElementById('adminSearch').value);
});

// Close modals on overlay click
document.getElementById('addRowModal').addEventListener('click', function(e) {
  if (e.target === this) closeAddModal();
});
document.getElementById('confirmDeleteModal').addEventListener('click', function(e) {
  if (e.target === this) closeConfirmDelete();
});
</script>

</body>
</html>