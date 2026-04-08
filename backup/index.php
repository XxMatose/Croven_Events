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

// ─── Query the view ─────────────────────────────────────────────────
$stmt = $pdo->query("SELECT * FROM vw_full_event");
$rows = $stmt->fetchAll();

// ─── Group rows by event_ID ──────────────────────────────────────────
$events = [];
foreach ($rows as $row) {
    $id = $row['event_ID'];
    if (!isset($events[$id])) {
        $events[$id] = [
            'event_ID'        => $id,
            'event_Name'      => $row['event_Name'],
            'event_Year'      => $row['event_Year'],
            'event_StartDate' => $row['event_StartDate'],
            'event_EndDate'   => $row['event_EndDate'],
            'venue_Name'      => $row['venue_Name'],
            'performers'      => [],
        ];
    }
    if (!empty($row['performer_Name'])) {
        $events[$id]['performers'][] = [
            'name'            => $row['performer_Name'],
            'is_Headliner'    => $row['is_Headliner'],
            'order_performed' => $row['order_performed'],
            'watched'         => $row['watched'],
        ];
    }
}

// Pass events to JS as JSON for client-side filtering
$eventsJson = json_encode(array_values($events));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Croven Events</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: system-ui, -apple-system, sans-serif;
      background: #f5f5f3;
      color: #1a1a18;
      padding: 2rem;
      line-height: 1.6;
    }

    /* ── Page header ── */
    .page-header {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 1rem;
    }
    .page-header h1 { font-size: 20px; font-weight: 500; }
    .record-count {
      font-size: 12px;
      background: #fff;
      border: 0.5px solid #ddd;
      border-radius: 8px;
      padding: 3px 10px;
      color: #666;
    }

    /* ── Search bar ── */
    .search-wrap {
      background: #fff;
      border: 0.5px solid #e0e0de;
      border-radius: 12px;
      padding: 1rem 1.25rem;
      margin-bottom: 1.5rem;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .filter-tabs {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
    }
    .filter-tab {
      font-size: 12px;
      padding: 4px 12px;
      border-radius: 20px;
      border: 0.5px solid #ddd;
      background: #f5f5f3;
      color: #666;
      cursor: pointer;
      transition: all 0.15s;
      user-select: none;
    }
    .filter-tab:hover { background: #ebebea; }
    .filter-tab.active {
      background: #1a1a18;
      color: #fff;
      border-color: #1a1a18;
    }

    .search-input-row {
      display: flex;
      gap: 8px;
      align-items: center;
    }
    .search-input-wrap {
      position: relative;
      flex: 1;
    }
    .search-icon {
      position: absolute;
      left: 10px;
      top: 50%;
      transform: translateY(-50%);
      color: #aaa;
      font-size: 14px;
      pointer-events: none;
    }
    #searchInput {
      width: 100%;
      padding: 8px 10px 8px 32px;
      border: 0.5px solid #ddd;
      border-radius: 8px;
      font-size: 14px;
      background: #f9f9f7;
      color: #1a1a18;
      outline: none;
      transition: border-color 0.15s;
    }
    #searchInput:focus { border-color: #999; background: #fff; }
    #searchInput::placeholder { color: #bbb; }

    #datePickerBtn {
      padding: 8px 14px;
      border: 0.5px solid #ddd;
      border-radius: 8px;
      font-size: 13px;
      background: #f9f9f7;
      color: #555;
      cursor: pointer;
      white-space: nowrap;
      transition: all 0.15s;
      display: none;
    }
    #datePickerBtn:hover { background: #ebebea; }
    #datePickerBtn.has-date {
      background: #EEEDFE;
      border-color: #AFA9EC;
      color: #3C3489;
      font-weight: 500;
    }

    .clear-btn {
      padding: 8px 14px;
      border: 0.5px solid #ddd;
      border-radius: 8px;
      font-size: 13px;
      background: #fff;
      color: #888;
      cursor: pointer;
      transition: all 0.15s;
      display: none;
    }
    .clear-btn:hover { background: #f5f5f3; color: #1a1a18; }
    .clear-btn.visible { display: block; }

    /* ── No results ── */
    .no-results {
      text-align: center;
      padding: 3rem;
      color: #aaa;
      font-size: 14px;
      display: none;
    }
    .no-results.visible { display: block; }

    /* ── Card grid ── */
    .card-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 16px;
    }

    .card {
      background: #fff;
      border: 0.5px solid #e0e0de;
      border-radius: 12px;
      padding: 1.25rem;
      display: flex;
      flex-direction: column;
      gap: 12px;
      transition: opacity 0.2s, transform 0.2s;
    }
    .card.hidden {
      display: none;
    }

    .card-header { display: flex; flex-direction: column; gap: 4px; }
    .event-name  { font-size: 16px; font-weight: 500; color: #1a1a18; }
    .event-meta  { font-size: 12px; color: #888; display: flex; gap: 10px; flex-wrap: wrap; }

    .venue-row {
      font-size: 13px;
      color: #555;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .venue-icon {
      width: 14px; height: 14px;
      background: #E1F5EE;
      border-radius: 50%;
      display: inline-flex; align-items: center; justify-content: center;
      font-size: 9px; color: #085041; flex-shrink: 0;
    }

    .divider { border: none; border-top: 0.5px solid #e0e0de; }

    .performer-list { display: flex; flex-direction: column; gap: 6px; }
    .performer-list-label {
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: #aaa;
      margin-bottom: 2px;
    }
    .performer-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 13px;
      padding: 5px 8px;
      border-radius: 8px;
      background: #f9f9f7;
    }
    .performer-row.headliner { background: #EEEDFE; }
    .performer-left { display: flex; align-items: center; gap: 8px; }
    .order-num { font-size: 11px; color: #aaa; min-width: 16px; text-align: right; }
    .performer-name { color: #1a1a18; }
    .performer-row.headliner .performer-name { font-weight: 500; color: #3C3489; }

    .badge { font-size: 10px; padding: 1px 7px; border-radius: 6px; flex-shrink: 0; }
    .badge-headliner { background: #EEEDFE; color: #3C3489; }
    .badge-watched   { background: #EAF3DE; color: #27500A; }
    .badge-row { display: flex; gap: 4px; }

    .no-performers { font-size: 13px; color: #aaa; font-style: italic; }
    .no-records    { text-align: center; color: #888; padding: 3rem; font-size: 14px; }

    /* ── Highlight matched text ── */
    mark {
      background: #FAC775;
      color: #412402;
      border-radius: 2px;
      padding: 0 1px;
    }

    /* ══════════════════════════════════════════
       CALENDAR MODAL
    ══════════════════════════════════════════ */
    .modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.35);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }
    .modal-overlay.open { display: flex; }

    .calendar-modal {
      background: #fff;
      border-radius: 14px;
      padding: 1.25rem;
      width: 320px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.12);
    }

    .cal-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1rem;
    }
    .cal-header span {
      font-size: 15px;
      font-weight: 500;
    }
    .cal-nav {
      background: none;
      border: 0.5px solid #ddd;
      border-radius: 6px;
      width: 28px; height: 28px;
      cursor: pointer;
      font-size: 14px;
      display: flex; align-items: center; justify-content: center;
      color: #555;
      transition: background 0.1s;
    }
    .cal-nav:hover { background: #f5f5f3; }

    .cal-grid {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 2px;
    }
    .cal-day-label {
      font-size: 10px;
      text-align: center;
      color: #aaa;
      padding: 4px 0;
      font-weight: 500;
    }
    .cal-day {
      font-size: 13px;
      text-align: center;
      padding: 6px 0;
      border-radius: 6px;
      cursor: pointer;
      color: #1a1a18;
      transition: background 0.1s;
    }
    .cal-day:hover { background: #f5f5f3; }
    .cal-day.empty { cursor: default; }
    .cal-day.empty:hover { background: none; }
    .cal-day.has-event { font-weight: 500; color: #3C3489; }
    .cal-day.selected {
      background: #1a1a18;
      color: #fff;
    }
    .cal-day.today { border: 0.5px solid #ddd; }

    .cal-footer {
      display: flex;
      justify-content: space-between;
      margin-top: 1rem;
      gap: 8px;
    }
    .cal-footer button {
      flex: 1;
      padding: 8px;
      border-radius: 8px;
      border: 0.5px solid #ddd;
      font-size: 13px;
      cursor: pointer;
      transition: background 0.15s;
    }
    .cal-clear { background: #f5f5f3; color: #666; }
    .cal-clear:hover { background: #ebebea; }
    .cal-confirm { background: #1a1a18; color: #fff; border-color: #1a1a18; }
    .cal-confirm:hover { background: #333; }
  </style>
</head>
<body>

<!-- ══ Page header ══════════════════════════════════ -->
<div class="page-header">
  <h1>Croven Events</h1>
  <span class="record-count" id="visibleCount"><?= count($events) ?> events</span>
</div>

<!-- ══ Search bar ═══════════════════════════════════ -->
<div class="search-wrap">
  <div class="filter-tabs">
    <span class="filter-tab active" data-mode="event">Event</span>
    <span class="filter-tab" data-mode="venue">Venue</span>
    <span class="filter-tab" data-mode="performer">Performer</span>
    <span class="filter-tab" data-mode="date">Date</span>
  </div>
  <div class="search-input-row">
    <div class="search-input-wrap">
      <span class="search-icon">&#9906;</span>
      <input type="text" id="searchInput" placeholder="Search events…" autocomplete="off">
    </div>
    <button id="datePickerBtn">&#128197; Pick date</button>
    <button class="clear-btn" id="clearBtn">Clear</button>
  </div>
</div>

<!-- ══ No results message ═══════════════════════════ -->
<p class="no-results" id="noResults">No events match your search.</p>

<!-- ══ Card grid ════════════════════════════════════ -->
<?php if (empty($events)): ?>
  <p class="no-records">No events found.</p>
<?php else: ?>
<div class="card-grid" id="cardGrid">
  <?php foreach ($events as $event): ?>
    <?php
      $start = $event['event_StartDate'] ? date('d M Y', strtotime($event['event_StartDate'])) : '—';
      $end   = $event['event_EndDate']   ? date('d M Y', strtotime($event['event_EndDate']))   : null;
      $performers = array_map(fn($p) => $p['name'], $event['performers']);
    ?>
    <div class="card"
         data-event="<?= htmlspecialchars(strtolower($event['event_Name'])) ?>"
         data-venue="<?= htmlspecialchars(strtolower($event['venue_Name'] ?? '')) ?>"
         data-performers="<?= htmlspecialchars(strtolower(implode('|', $performers))) ?>"
         data-startdate="<?= htmlspecialchars($event['event_StartDate'] ?? '') ?>"
         data-enddate="<?= htmlspecialchars($event['event_EndDate'] ?? '') ?>">

      <div class="card-header">
        <span class="event-name"><?= htmlspecialchars($event['event_Name']) ?></span>
        <div class="event-meta">
          <span><?= htmlspecialchars($event['event_Year']) ?></span>
          <span><?= $start ?><?= $end && $end !== $start ? ' – ' . $end : '' ?></span>
        </div>
      </div>

      <?php if (!empty($event['venue_Name'])): ?>
        <div class="venue-row">
          <span class="venue-icon">&#9679;</span>
          <?= htmlspecialchars($event['venue_Name']) ?>
        </div>
      <?php endif; ?>

      <hr class="divider">

      <div class="performer-list">
        <span class="performer-list-label">Performers</span>
        <?php if (empty($event['performers'])): ?>
          <span class="no-performers">No performers listed</span>
        <?php else: ?>
          <?php foreach ($event['performers'] as $p): ?>
            <div class="performer-row <?= $p['is_Headliner'] ? 'headliner' : '' ?>">
              <div class="performer-left">
                <?php if ($p['order_performed'] !== null): ?>
                  <span class="order-num"><?= (int)$p['order_performed'] ?>.</span>
                <?php endif; ?>
                <span class="performer-name"><?= htmlspecialchars($p['name']) ?></span>
              </div>
              <div class="badge-row">
                <?php if ($p['is_Headliner']): ?>
                  <span class="badge badge-headliner">Headliner</span>
                <?php endif; ?>
                <?php if ($p['watched']): ?>
                  <span class="badge badge-watched">Watched</span>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ══ Calendar modal ═══════════════════════════════ -->
<div class="modal-overlay" id="calModal">
  <div class="calendar-modal">
    <div class="cal-header">
      <button class="cal-nav" id="calPrev">&#8249;</button>
      <span id="calTitle"></span>
      <button class="cal-nav" id="calNext">&#8250;</button>
    </div>
    <div class="cal-grid" id="calGrid"></div>
    <div class="cal-footer">
      <button class="cal-clear" id="calClear">Clear date</button>
      <button class="cal-confirm" id="calConfirm">Apply</button>
    </div>
  </div>
</div>

<script>
// ── State ──────────────────────────────────────────────────────────
const events      = <?= $eventsJson ?>;
const cards       = document.querySelectorAll('.card');
const searchInput = document.getElementById('searchInput');
const clearBtn    = document.getElementById('clearBtn');
const noResults   = document.getElementById('noResults');
const countEl     = document.getElementById('visibleCount');
const dateBtn     = document.getElementById('datePickerBtn');

let currentMode    = 'event';
let selectedDate   = null;  // YYYY-MM-DD string
let pendingDate    = null;  // date being chosen in calendar

// ── Filter tabs ───────────────────────────────────────────────────
document.querySelectorAll('.filter-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    currentMode = tab.dataset.mode;

    // Show/hide calendar button vs text input
    if (currentMode === 'date') {
      searchInput.style.display = 'none';
      dateBtn.style.display = 'block';
    } else {
      searchInput.style.display = '';
      dateBtn.style.display = 'none';
      searchInput.placeholder = `Search by ${currentMode}…`;
      searchInput.focus();
    }

    // Reset on mode switch
    clearSearch();
  });
});

// ── Text search ───────────────────────────────────────────────────
searchInput.addEventListener('input', () => {
  const q = searchInput.value.trim();
  clearBtn.classList.toggle('visible', q.length > 0);
  filterCards(q);
});

function clearSearch() {
  searchInput.value = '';
  selectedDate = null;
  pendingDate  = null;
  dateBtn.textContent = '📅 Pick date';
  dateBtn.classList.remove('has-date');
  clearBtn.classList.remove('visible');
  filterCards('');
}

clearBtn.addEventListener('click', clearSearch);

// ── Filter logic ──────────────────────────────────────────────────
function filterCards(query) {
  const q = query.toLowerCase().trim();
  let visible = 0;

  cards.forEach(card => {
    let match = false;

    if (currentMode === 'date' && selectedDate) {
      const start = card.dataset.startdate;
      const end   = card.dataset.enddate || start;
      match = selectedDate >= start && selectedDate <= (end || start);
    } else if (q === '') {
      match = true;
    } else if (currentMode === 'event') {
      match = card.dataset.event.includes(q);
    } else if (currentMode === 'venue') {
      match = card.dataset.venue.includes(q);
    } else if (currentMode === 'performer') {
      match = card.dataset.performers.includes(q);
    }

    card.classList.toggle('hidden', !match);
    if (match) visible++;
  });

  countEl.textContent = visible + (visible === 1 ? ' event' : ' events');
  noResults.classList.toggle('visible', visible === 0);
}

// ══════════════════════════════════════════════════════════════════
// CALENDAR
// ══════════════════════════════════════════════════════════════════
const calModal  = document.getElementById('calModal');
const calGrid   = document.getElementById('calGrid');
const calTitle  = document.getElementById('calTitle');

// Collect all event dates for dot highlights
const eventDates = new Set();
events.forEach(ev => {
  if (ev.event_StartDate) {
    // Mark every day in the range
    const s = new Date(ev.event_StartDate);
    const e = ev.event_EndDate ? new Date(ev.event_EndDate) : s;
    for (let d = new Date(s); d <= e; d.setDate(d.getDate() + 1)) {
      eventDates.add(d.toISOString().slice(0, 10));
    }
  }
});

let calYear, calMonth;

function openCalendar() {
  const ref = selectedDate ? new Date(selectedDate) : new Date();
  calYear  = ref.getFullYear();
  calMonth = ref.getMonth();
  pendingDate = selectedDate;
  renderCalendar();
  calModal.classList.add('open');
}

dateBtn.addEventListener('click', openCalendar);

// Close on overlay click
calModal.addEventListener('click', e => {
  if (e.target === calModal) calModal.classList.remove('open');
});

document.getElementById('calPrev').addEventListener('click', () => {
  calMonth--;
  if (calMonth < 0) { calMonth = 11; calYear--; }
  renderCalendar();
});
document.getElementById('calNext').addEventListener('click', () => {
  calMonth++;
  if (calMonth > 11) { calMonth = 0; calYear++; }
  renderCalendar();
});
document.getElementById('calClear').addEventListener('click', () => {
  pendingDate = null;
  renderCalendar();
});
document.getElementById('calConfirm').addEventListener('click', () => {
  selectedDate = pendingDate;
  calModal.classList.remove('open');
  if (selectedDate) {
    const [y,m,d] = selectedDate.split('-');
    dateBtn.textContent = `📅 ${d}/${m}/${y}`;
    dateBtn.classList.add('has-date');
    clearBtn.classList.add('visible');
  } else {
    dateBtn.textContent = '📅 Pick date';
    dateBtn.classList.remove('has-date');
    clearBtn.classList.remove('visible');
  }
  filterCards('');
});

function renderCalendar() {
  const monthNames = ['January','February','March','April','May','June',
                      'July','August','September','October','November','December'];
  calTitle.textContent = `${monthNames[calMonth]} ${calYear}`;

  const today     = new Date().toISOString().slice(0, 10);
  const firstDay  = new Date(calYear, calMonth, 1).getDay(); // 0=Sun
  const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();

  calGrid.innerHTML = '';

  // Day labels
  ['Su','Mo','Tu','We','Th','Fr','Sa'].forEach(d => {
    const el = document.createElement('div');
    el.className = 'cal-day-label';
    el.textContent = d;
    calGrid.appendChild(el);
  });

  // Empty cells before first day
  for (let i = 0; i < firstDay; i++) {
    const el = document.createElement('div');
    el.className = 'cal-day empty';
    calGrid.appendChild(el);
  }

  // Day cells
  for (let day = 1; day <= daysInMonth; day++) {
    const iso = `${calYear}-${String(calMonth+1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
    const el = document.createElement('div');
    el.className = 'cal-day';
    el.textContent = day;

    if (iso === today)       el.classList.add('today');
    if (eventDates.has(iso)) el.classList.add('has-event');
    if (iso === pendingDate) el.classList.add('selected');

    el.addEventListener('click', () => {
      pendingDate = iso;
      renderCalendar();
    });
    calGrid.appendChild(el);
  }
}
</script>
</body>
</html>