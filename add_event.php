<?php
require_once 'db/db_hosted.php';
require_once 'auth.php';

// ─── Fetch all venues for the dropdown ──────────────────────────────
$venues = [];
try {
    $vStmt = $pdo->query("SELECT venue_ID, venue_Name, venue_Address, venue_City, venue_State, venue_Type FROM venue ORDER BY venue_Name");
    $venues = $vStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* skip */ }

// ─── Fetch all performers for the dropdown ───────────────────────────
$performers = [];
try {
    $pStmt = $pdo->query("SELECT performer_ID, performer_Name FROM performer ORDER BY performer_Name");
    $performers = $pStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* skip */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Event – Croven Events</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    /* ── Select inputs ───────────────────────────────────────────── */
    .field select {
      padding: 8px 10px;
      border: 0.5px solid var(--border-strong);
      border-radius: 8px;
      font-size: 14px;
      background: var(--input-bg);
      color: var(--text);
      font-family: inherit;
      outline: none;
      transition: border-color 0.15s, background 0.15s;
      width: 100%;
      appearance: none;
      cursor: pointer;
    }
    .field select:focus {
      border-color: var(--accent);
      background: var(--card-bg);
    }
    body.red .field select { color: #ff2b2b; }

    .add-event-wrap {
      max-width: 620px;
      margin: 0 auto;
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .page-heading {
      font-size: 20px;
      font-weight: 600;
      color: var(--text);
      margin-bottom: 0.25rem;
    }

    .form-banner {
      padding: 12px 16px;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 500;
      animation: fadeIn 0.3s ease;
    }
    .form-banner.success {
      background: var(--watched-bg);
      color: var(--watched-text);
      border: 0.5px solid var(--watched-text);
    }
    .form-banner.error {
      background: #fff0f0;
      color: #c0392b;
      border: 0.5px solid #c0392b;
    }
    body.dark .form-banner.error { background: #2a1010; color: #ff6b6b; border-color: #ff6b6b; }
    body.red  .form-banner.error { background: #2a0000; color: #ff4d4d; border-color: #ff4d4d; }

    body.red input[type="date"] {
      color-scheme: dark;
      accent-color: #ff2b2b;
    }
    body.red input[type="date"]::-webkit-calendar-picker-indicator {
      filter: brightness(0) saturate(100%) invert(20%) sepia(100%) saturate(700%) hue-rotate(340deg) brightness(120%);
      cursor: pointer;
      opacity: 1;
    }
    body.red ::-webkit-datetime-edit            { color: #ff2b2b; }
    body.red ::-webkit-datetime-edit-fields-wrapper { background: transparent; }
    body.red ::-webkit-datetime-edit-text       { color: #ff2b2b; opacity: 0.5; }
    body.red ::-webkit-datetime-edit-month-field,
    body.red ::-webkit-datetime-edit-day-field,
    body.red ::-webkit-datetime-edit-year-field { color: #ff2b2b; }
    body.red ::-webkit-datetime-edit-month-field:focus,
    body.red ::-webkit-datetime-edit-day-field:focus,
    body.red ::-webkit-datetime-edit-year-field:focus {
      background: #ff2b2b;
      color: #000;
      border-radius: 2px;
    }

    .form-card {
      background: var(--card-bg);
      border: 0.5px solid var(--border);
      border-radius: 12px;
      padding: 1.25rem;
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .form-card-title {
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.07em;
      color: var(--muted);
      margin-bottom: -4px;
    }

    .field-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .field-full   { grid-column: 1 / -1; }
    @media (max-width: 520px) { .field-grid-2 { grid-template-columns: 1fr; } }

    .field {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }

    .field label {
      font-size: 12px;
      font-weight: 500;
      color: var(--muted);
    }
    .field label .req { color: var(--accent); margin-left: 2px; }

    .field input[type="text"],
    .field input[type="date"],
    .field input[type="number"] {
      padding: 8px 10px;
      border: 0.5px solid var(--border-strong);
      border-radius: 8px;
      font-size: 14px;
      background: var(--input-bg);
      color: var(--text);
      font-family: inherit;
      outline: none;
      transition: border-color 0.15s, background 0.15s;
      width: 100%;
    }
    .field input:focus {
      border-color: var(--accent);
      background: var(--card-bg);
    }
    .field input::placeholder { color: var(--border-strong); }
    body.red .field input::placeholder,
    body.red .p-row input::placeholder { color: rgba(255,43,43,0.45); }

    body.red .field label,
    body.red .field label .req,
    body.red .form-card-title,
    body.red .page-heading,
    body.red .p-header span,
    body.red .p-toggle-label            { color: #ff2b2b; }

    body.red .field input[type="text"],
    body.red .field input[type="date"],
    body.red .field input[type="number"],
    body.red .p-row input[type="text"],
    body.red .p-row input[type="number"] { color: #ff2b2b; }

    body.red .btn-add-performer          { color: #ff2b2b; }
    body.red .btn-add-performer:hover    { color: #ff2b2b; border-color: #ff2b2b; }
    body.red .p-remove                   { color: #ff2b2b; border-color: #2a0000; }

    #performers-list {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .p-row {
      display: grid;
      grid-template-columns: 1fr 56px auto auto auto auto;
      gap: 8px;
      align-items: center;
      background: var(--input-bg);
      border: 0.5px solid var(--border);
      border-radius: 10px;
      padding: 10px 12px;
      animation: fadeIn 0.2s ease;
    }
    @media (max-width: 480px) {
      .p-row { grid-template-columns: 1fr 44px auto auto auto auto; gap: 5px; }
    }

    .p-row input[type="text"],
    .p-row input[type="number"] {
      padding: 7px 9px;
      border: 0.5px solid var(--border-strong);
      border-radius: 7px;
      font-size: 13px;
      background: var(--card-bg);
      color: var(--text);
      font-family: inherit;
      outline: none;
      width: 100%;
      transition: border-color 0.15s;
    }
    .p-row input:focus { border-color: var(--accent); }

    .p-toggle {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 3px;
      cursor: pointer;
      user-select: none;
    }
    .p-toggle input[type="checkbox"] { display: none; }

    .p-toggle-label {
      font-size: 9px;
      font-weight: 600;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: var(--muted);
    }

    .p-toggle-box {
      width: 32px;
      height: 26px;
      border-radius: 6px;
      border: 0.5px solid var(--border-strong);
      background: var(--card-bg);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      transition: background 0.15s, border-color 0.15s;
    }

    .p-toggle.is-headliner input:checked ~ .p-toggle-box {
      background: var(--headliner-bg);
      border-color: var(--headliner-text);
    }
    .p-toggle.is-opener input:checked ~ .p-toggle-box {
      background: var(--highlight);
      border-color: #9a6000;
    }
    .p-toggle.is-watched input:checked ~ .p-toggle-box {
      background: var(--watched-bg);
      border-color: var(--watched-text);
    }

    .p-remove {
      background: none;
      border: 0.5px solid var(--border-strong);
      color: var(--muted);
      width: 28px;
      height: 28px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 15px;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background 0.15s, color 0.15s, border-color 0.15s;
      flex-shrink: 0;
    }
    .p-remove:hover            { background: #fff0f0; border-color: #c0392b; color: #c0392b; }
    body.dark .p-remove:hover  { background: #2a1010; border-color: #ff6b6b; color: #ff6b6b; }
    body.red  .p-remove:hover  { background: #2a0000; border-color: #ff4d4d; color: #ff4d4d; }

    .p-header {
      display: grid;
      grid-template-columns: 1fr 56px auto auto auto auto;
      gap: 8px;
      padding: 0 12px 2px;
      font-size: 10px;
      font-weight: 600;
      letter-spacing: 0.07em;
      text-transform: uppercase;
      color: var(--muted);
    }
    .p-header span { text-align: center; }
    .p-header span:first-child { text-align: left; }
    @media (max-width: 480px) { .p-header { display: none; } }

    .btn-add-performer {
      width: 100%;
      padding: 9px;
      border: 0.5px dashed var(--border-strong);
      border-radius: 8px;
      background: none;
      color: var(--muted);
      font-size: 13px;
      font-family: inherit;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      transition: border-color 0.15s, color 0.15s, background 0.15s;
    }
    .btn-add-performer:hover {
      border-color: var(--accent);
      color: var(--text);
      background: var(--input-bg);
    }

    .btn-submit {
      width: 100%;
      padding: 12px;
      background: var(--accent);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-size: 15px;
      font-weight: 600;
      font-family: inherit;
      cursor: pointer;
      transition: opacity 0.15s;
    }
    .btn-submit:hover    { opacity: 0.85; }
    .btn-submit:active   { opacity: 0.7; }
    .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }

    .venue-dd {
      display: none;
      position: absolute;
      top: 100%;
      left: 0; right: 0;
      background: var(--card-bg);
      border: 0.5px solid var(--border-strong);
      border-radius: 8px;
      margin-top: 4px;
      max-height: 220px;
      overflow-y: auto;
      z-index: 200;
      list-style: none;
      padding: 4px;
      box-shadow: 0 4px 16px rgba(0,0,0,0.18);
    }
    .venue-dd.open { display: block; }
    .venue-dd li {
      padding: 9px 12px;
      border-radius: 6px;
      font-size: 13px;
      cursor: pointer;
      color: var(--text);
      transition: background 0.12s;
    }
    .venue-dd li:hover,
    .venue-dd li.active { background: var(--input-bg); }
    .venue-dd li .dd-sub {
      font-size: 11px;
      color: var(--muted);
      margin-top: 1px;
    }
    .venue-dd li.dd-new { color: var(--muted); font-style: italic; }
    body.red .venue-dd li       { color: #ff2b2b; }
    body.red .venue-dd li .dd-sub { color: rgba(255,43,43,0.55); }

    .p-name-wrap { width: 100%; }
    .p-name-wrap .p-name-input {
      width: 100%;
      padding: 7px 9px;
      border: 0.5px solid var(--border-strong);
      border-radius: 7px;
      font-size: 13px;
      background: var(--card-bg);
      color: var(--text);
      font-family: inherit;
      outline: none;
      transition: border-color 0.15s;
    }
    .p-name-wrap .p-name-input:focus { border-color: var(--accent); }
    body.red .p-name-wrap .p-name-input { color: #ff2b2b; }
    body.red .p-name-wrap .p-name-input::placeholder { color: rgba(255,43,43,0.45); }
  </style>
</head>
<body>

<?php
  $currentPage = 'new';
  $pageTitle   = 'Add Event';
  require 'nav.php';
?>

<div class="add-event-wrap">

  <div class="page-subheader">
    <span class="record-count">Add Event</span>
  </div>

  <div>
    <h2 class="page-heading">Add Event</h2>
    <p style="font-size:13px;color:var(--muted)">New venues and performers are created automatically. Existing records are reused.</p>
  </div>

  <div class="form-banner" id="formBanner" style="display:none"></div>

  <!-- ── 1. Event ──────────────────────────────────────────────── -->
  <div class="form-card">
    <div class="form-card-title">Event</div>
    <div class="field-grid-2">
      <div class="field field-full">
        <label>Event Name <span class="req">*</span></label>
        <input type="text" id="eventName" placeholder="e.g. Lollapalooza 2024">
      </div>
      <div class="field">
        <label>Start Date <span class="req">*</span></label>
        <input type="date" id="startDate">
      </div>
      <div class="field">
        <label>End Date <span style="font-weight:400;opacity:.7">(blank = single day)</span></label>
        <input type="date" id="endDate">
      </div>
    </div>
  </div>

  <!-- ── 2. Venue ──────────────────────────────────────────────── -->
  <div class="form-card">
    <div class="form-card-title">Venue</div>
    <div class="field-grid-2">
      <div class="field field-full" style="position:relative">
        <label>Venue Name <span class="req">*</span></label>
        <input type="text" id="venueName" placeholder="Search or enter a new venue..." autocomplete="off">
        <ul id="venueDropdown" class="venue-dd" role="listbox"></ul>
      </div>
      <div class="field field-full">
        <label>Address</label>
        <input type="text" id="venueAddress" placeholder="e.g. 337 E Randolph St">
      </div>
      <div class="field">
        <label>City <span class="req">*</span></label>
        <input type="text" id="venueCity" placeholder="e.g. Chicago">
      </div>
      <div class="field">
        <label>State <span class="req">*</span></label>
        <input type="text" id="venueState" placeholder="e.g. IL">
      </div>
      <div class="field field-full">
        <label>Venue Type</label>
        <input type="text" id="venueType" placeholder="e.g. Outdoor Festival, Arena, Club">
      </div>
    </div>
  </div>

  <!-- ── 3. Performers ─────────────────────────────────────────── -->
  <div class="form-card">
    <div class="form-card-title">Performers</div>
    <div class="p-header">
      <span>Name</span>
      <span>Order</span>
      <span>Head</span>
      <span>Open</span>
      <span>Watched</span>
      <span></span>
    </div>
    <div id="performersList"></div>
    <button type="button" class="btn-add-performer" id="addPerformerBtn">+ Add Performer</button>
  </div>

  <button type="button" class="btn-submit" id="submitBtn">Save Event</button>

</div>

<script>
const VENUES     = <?= json_encode(array_values($venues),     JSON_HEX_TAG) ?>;
const PERFORMERS = <?= json_encode(array_values($performers), JSON_HEX_TAG) ?>;

let rowCount = 0;

// ── Performer rows ────────────────────────────────────────────────────
function addPerformerRow(opts = {}) {
  const i    = rowCount++;
  const list = document.getElementById('performersList');
  const row  = document.createElement('div');
  row.className = 'p-row';
  row.id = 'prow-' + i;
  row.dataset.epId = opts.ep_id || '';

  row.innerHTML = `
    <div class="p-name-wrap" style="position:relative">
      <input type="text" class="p-name-input" placeholder="Performer name"
             value="${esc(opts.name || '')}" autocomplete="off">
      <ul class="venue-dd p-dd" role="listbox"></ul>
    </div>
    <input type="number" class="p-order-input" placeholder="#" min="1"
           value="${opts.order !== undefined ? opts.order : list.children.length + 1}">
    <label class="p-toggle is-headliner" title="Headliner">
      <input type="checkbox" class="p-head-cb" ${opts.is_headliner ? 'checked' : ''}>
      <span class="p-toggle-label">Head</span>
      <span class="p-toggle-box">🎤</span>
    </label>
    <label class="p-toggle is-opener" title="Main Opener">
      <input type="checkbox" class="p-opener-cb" ${opts.is_opener ? 'checked' : ''}>
      <span class="p-toggle-label">Open</span>
      <span class="p-toggle-box">🎸</span>
    </label>
    <label class="p-toggle is-watched" title="I watched this">
      <input type="checkbox" class="p-watched-cb" ${opts.watched ? 'checked' : ''}>
      <span class="p-toggle-label">Watched</span>
      <span class="p-toggle-box">👁️</span>
    </label>
    <button type="button" class="p-remove" title="Remove">×</button>
  `;

  row.querySelector('.p-remove').addEventListener('click', () => row.remove());
  attachPerformerCombobox(row.querySelector('.p-name-input'), row.querySelector('.p-dd'));
  list.appendChild(row);
}

document.getElementById('addPerformerBtn').addEventListener('click', () => addPerformerRow());
addPerformerRow(); // start with one empty row

// ── Performer combobox ────────────────────────────────────────────────
function attachPerformerCombobox(input, dd) {
  function renderList(q) {
    dd.innerHTML = '';
    const lower   = q.toLowerCase().trim();
    const matches = lower
      ? PERFORMERS.filter(p => p.performer_Name.toLowerCase().includes(lower))
      : PERFORMERS;

    matches.slice(0, 8).forEach(p => {
      const li = document.createElement('li');
      li.textContent = p.performer_Name;
      li.addEventListener('mousedown', e => {
        e.preventDefault();
        input.value = p.performer_Name;
        dd.classList.remove('open');
      });
      dd.appendChild(li);
    });

    if (lower && !matches.find(p => p.performer_Name.toLowerCase() === lower)) {
      const li = document.createElement('li');
      li.className = 'dd-new';
      li.textContent = `+ Add new: "${q}"`;
      dd.appendChild(li);
    }
    dd.classList.toggle('open', dd.children.length > 0);
  }

  input.addEventListener('focus', () => renderList(input.value));
  input.addEventListener('input', () => renderList(input.value));
  input.addEventListener('blur',  () => setTimeout(() => dd.classList.remove('open'), 150));
}

// ── Venue combobox ────────────────────────────────────────────────────
(function () {
  const input = document.getElementById('venueName');
  const dd    = document.getElementById('venueDropdown');

  function fill(v) {
    input.value = v.venue_Name || '';
    document.getElementById('venueAddress').value = v.venue_Address || '';
    document.getElementById('venueCity').value    = v.venue_City    || '';
    document.getElementById('venueState').value   = v.venue_State   || '';
    document.getElementById('venueType').value    = v.venue_Type    || '';
  }

  function renderList(q) {
    dd.innerHTML = '';
    const lower   = q.toLowerCase().trim();
    const matches = lower
      ? VENUES.filter(v => v.venue_Name.toLowerCase().includes(lower))
      : VENUES;

    matches.forEach(v => {
      const li = document.createElement('li');
      li.innerHTML = `<div>${esc(v.venue_Name)}</div>
        <div class="dd-sub">${esc([v.venue_City, v.venue_State].filter(Boolean).join(', '))}</div>`;
      li.addEventListener('mousedown', e => {
        e.preventDefault();
        fill(v);
        dd.classList.remove('open');
      });
      dd.appendChild(li);
    });

    if (lower && !matches.find(v => v.venue_Name.toLowerCase() === lower)) {
      const li = document.createElement('li');
      li.className = 'dd-new';
      li.textContent = `+ Add new venue: "${q}"`;
      dd.appendChild(li);
    }
    dd.classList.toggle('open', dd.children.length > 0);
  }

  input.addEventListener('focus', () => renderList(input.value));
  input.addEventListener('input', () => renderList(input.value));
  input.addEventListener('blur',  () => setTimeout(() => dd.classList.remove('open'), 150));
  document.addEventListener('keydown', e => { if (e.key === 'Escape') dd.classList.remove('open'); });
})();

// ── Submit ────────────────────────────────────────────────────────────
document.getElementById('submitBtn').addEventListener('click', async () => {
  const eventName = document.getElementById('eventName').value.trim();
  const startDate = document.getElementById('startDate').value;
  const endDate   = document.getElementById('endDate').value;
  const venueName = document.getElementById('venueName').value.trim();
  const venueCity = document.getElementById('venueCity').value.trim();
  const venueState= document.getElementById('venueState').value.trim();

  if (!eventName || !startDate || !venueName || !venueCity || !venueState) {
    showBanner('Please fill in all required fields.', 'error');
    return;
  }

  // Collect performers
  const performers = [];
  document.querySelectorAll('#performersList .p-row').forEach((row, idx) => {
    const name = row.querySelector('.p-name-input').value.trim();
    if (!name) return;
    performers.push({
      name,
      order:        parseInt(row.querySelector('.p-order-input').value) || (idx + 1),
      is_headliner: row.querySelector('.p-head-cb').checked   ? 1 : 0,
      is_opener:    row.querySelector('.p-opener-cb').checked  ? 1 : 0,
      watched:      row.querySelector('.p-watched-cb').checked ? 1 : 0,
    });
  });

  if (performers.length === 0) {
    showBanner('Please add at least one performer.', 'error');
    return;
  }

  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.textContent = 'Saving…';

  try {
    const res  = await fetch('event_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action:        'insert',
        event_name:    eventName,
        start_date:    startDate,
        end_date:      endDate,
        venue_name:    venueName,
        venue_address: document.getElementById('venueAddress').value.trim(),
        venue_city:    venueCity,
        venue_state:   venueState,
        venue_type:    document.getElementById('venueType').value.trim(),
        performers,
      })
    });
    const data = await res.json();

    if (data.success) {
      showBanner(`Event <strong>${esc(eventName)}</strong> saved with ${performers.length} performer(s).`, 'success');
      // Reset form
      document.getElementById('eventName').value   = '';
      document.getElementById('startDate').value   = '';
      document.getElementById('endDate').value     = '';
      document.getElementById('venueName').value   = '';
      document.getElementById('venueAddress').value= '';
      document.getElementById('venueCity').value   = '';
      document.getElementById('venueState').value  = '';
      document.getElementById('venueType').value   = '';
      document.getElementById('performersList').innerHTML = '';
      rowCount = 0;
      addPerformerRow();
    } else {
      showBanner(data.error || 'Failed to save.', 'error');
    }
  } catch (err) {
    showBanner('Network error. Please try again.', 'error');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Save Event';
  }
});

function showBanner(msg, type) {
  const el = document.getElementById('formBanner');
  el.innerHTML  = msg;
  el.className  = 'form-banner ' + type;
  el.style.display = 'block';
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function esc(s) {
  return String(s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

</body>
</html>
