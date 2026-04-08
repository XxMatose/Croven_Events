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
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<!-- ══ Page header ══════════════════════════════════ -->
<div class="page-header">
  <h1>Croven Events</h1>
  <span class="record-count" id="visibleCount"><?= count($events) ?> events</span>
  <button id="themeToggle" class="theme-toggle-btn">🌙</button>
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
let selectedStartDate = null;
let selectedEndDate   = null;

let pendingStartDate  = null;
let pendingEndDate    = null;

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
  selectedStartDate = null;
  selectedEndDate   = null;
  pendingStartDate  = null;
  pendingEndDate    = null;
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

    if (currentMode === 'date' && selectedStartDate) {
  const eventStart = card.dataset.startdate;
  const eventEnd   = card.dataset.enddate || eventStart;

  const filterStart = selectedStartDate;
  const filterEnd   = selectedEndDate || selectedStartDate;

  // Overlap logic
  match = eventStart <= filterEnd && eventEnd >= filterStart;
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
  // Use start date if it exists, otherwise today
  const ref = selectedStartDate ? new Date(selectedStartDate) : new Date();

  calYear  = ref.getFullYear();
  calMonth = ref.getMonth();

  // Load existing selection into the calendar
  pendingStartDate = selectedStartDate;
  pendingEndDate   = selectedEndDate;

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
  selectedStartDate = pendingStartDate;
selectedEndDate   = pendingEndDate || pendingStartDate;
  calModal.classList.remove('open');
  if (selectedStartDate) {
  const format = (d) => {
    const [y,m,day] = d.split('-');
    return `${day}/${m}/${y}`;
  };

  if (selectedEndDate && selectedEndDate !== selectedStartDate) {
    dateBtn.textContent = `📅 ${format(selectedStartDate)} - ${format(selectedEndDate)}`;
  } else {
    dateBtn.textContent = `📅 ${format(selectedStartDate)}`;
  }
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
    if (pendingStartDate && pendingEndDate) {
      if (iso >= pendingStartDate && iso <= pendingEndDate) {
        el.classList.add('selected');
      }
    } else if (iso === pendingStartDate) {
      el.classList.add('selected');
    }

    el.addEventListener('click', () => {
      if (!pendingStartDate || (pendingStartDate && pendingEndDate)) {
        // Start new range
        pendingStartDate = iso;
        pendingEndDate = null;
      } else {
        // Set end date
        if (iso < pendingStartDate) {
          pendingEndDate = pendingStartDate;
          pendingStartDate = iso;
        } else {
          pendingEndDate = iso;
        }
      }

  renderCalendar();
});
    calGrid.appendChild(el);
  }
}

// ── THEME SYSTEM ─────────────────────────────

const themeToggleBtn = document.getElementById('themeToggle');

const themes = ['dark', 'red', 'light'];
const themeIcons = {
  dark: '🌙',
  red: '🔴',
  light: '☀️'
};

function applyTheme(theme) {
  document.body.classList.remove('dark', 'red');

  if (theme === 'dark') {
    document.body.classList.add('dark');
  } else if (theme === 'red') {
    document.body.classList.add('red');
  }

  themeToggleBtn.textContent = themeIcons[theme];
  localStorage.setItem('theme', theme);
}

function getNextTheme(current) {
  const index = themes.indexOf(current);
  return themes[(index + 1) % themes.length];
}

// Click cycles themes
themeToggleBtn.addEventListener('click', () => {
  const current = localStorage.getItem('theme') || 'dark';
  const next = getNextTheme(current);
  applyTheme(next);
});

// Initial load (DEFAULT = DARK)
(function () {
  const saved = localStorage.getItem('theme') || 'dark';
  applyTheme(saved);
})();
</script>
</body>
</html>