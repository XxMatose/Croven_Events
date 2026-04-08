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
            // ✅ Normalize watched to TRUE/FALSE cleanly
            'watched'         => (int)$row['watched'] === 1,
        ];
    }
}

// ─── SORT performers by order_performed ─────────────────────────────
foreach ($events as &$event) {
    usort($event['performers'], function ($a, $b) {
        return ($a['order_performed'] ?? 9999) <=> ($b['order_performed'] ?? 9999);
    });
}
unset($event);

$eventsJson = json_encode(array_values($events));
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Schedule – Croven Events</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php
  $currentPage = 'schedule';
  $pageTitle   = 'Schedule';
  require 'nav.php';
?>

<!-- Sub-header -->
<div class="page-subheader">
  <span class="record-count" id="visibleCount"><?= count($events) ?> events</span>
</div>

<!-- Search -->
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

<p class="no-results" id="noResults">No events match your search.</p>

<!-- Cards -->
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

      <!-- Performer List -->
      <div class="performer-list">
        <span class="performer-list-label">Performers</span>

        <?php if (empty($event['performers'])): ?>
          <span class="no-performers">No performers listed</span>
        <?php else: ?>
          <?php foreach ($event['performers'] as $p): ?>
            <div class="performer-row 
              <?= $p['is_Headliner'] ? 'headliner' : '' ?> 
              <?= $p['watched'] ? 'watched' : 'not-watched' ?>">

              <div class="performer-left">
                <span class="performer-name">
                  <?= htmlspecialchars($p['name']) ?>
                </span>
              </div>

              <div class="badge-row">
                <?php if ($p['is_Headliner']): ?>
                  <span class="badge badge-headliner">Headliner</span>
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

<script>
const allEvents   = <?= $eventsJson ?>;
const cards       = () => document.querySelectorAll('#cardGrid .card');
const countEl     = document.getElementById('visibleCount');
const noResults   = document.getElementById('noResults');
const searchInput = document.getElementById('searchInput');
const clearBtn    = document.getElementById('clearBtn');
const datePickerBtn = document.getElementById('datePickerBtn');

let activeMode  = 'event';
let rangeStart  = null;  // confirmed ISO start
let rangeEnd    = null;  // confirmed ISO end (null = single day)

// ── Filter tabs ────────────────────────────────────────────────────
document.querySelectorAll('.filter-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    activeMode = tab.dataset.mode;
    datePickerBtn.style.display = activeMode === 'date' ? 'inline-block' : 'none';
    if (activeMode !== 'date') { rangeStart = rangeEnd = null; }
    runFilter();
  });
});

// Hide date picker button unless on Date tab
datePickerBtn.style.display = 'none';

// ── Search input ───────────────────────────────────────────────────
searchInput.addEventListener('input', runFilter);

// ── Clear button ───────────────────────────────────────────────────
clearBtn.addEventListener('click', () => {
  searchInput.value = '';
  rangeStart = rangeEnd = null;
  datePickerBtn.textContent = '📅 Pick date';
  runFilter();
});

// ── Main filter ────────────────────────────────────────────────────
function runFilter() {
  const q = searchInput.value.trim().toLowerCase();
  let visible = 0;

  cards().forEach(card => {
    let show = false;

    if (activeMode === 'date' && rangeStart) {
      // An event is included if its date range overlaps the selected range.
      // Event must start on or before the selection end,
      // and end on or after the selection start.
      const evStart = card.dataset.startdate;
      const evEnd   = card.dataset.enddate || evStart;
      const selEnd  = rangeEnd || rangeStart;
      show = evStart <= selEnd && evEnd >= rangeStart;
    } else if (!q) {
      show = true;
    } else if (activeMode === 'event') {
      show = card.dataset.event.includes(q);
    } else if (activeMode === 'venue') {
      show = card.dataset.venue.includes(q);
    } else if (activeMode === 'performer') {
      show = card.dataset.performers.includes(q);
    }

    card.classList.toggle('hidden', !show);
    if (show) visible++;
  });

  countEl.textContent = visible + ' event' + (visible !== 1 ? 's' : '');
  noResults.style.display = visible === 0 ? 'block' : 'none';
}

// ── Build calendar modal ───────────────────────────────────────────
document.body.insertAdjacentHTML('beforeend', `
  <div class="modal-overlay" id="calModal">
    <div class="calendar-modal">
      <div id="calHint" class="cal-hint">Click a start date</div>
      <div class="cal-header">
        <button class="cal-nav" id="calPrev">&#8249;</button>
        <span id="calMonthLabel"></span>
        <button class="cal-nav" id="calNext">&#8250;</button>
      </div>
      <div class="cal-grid" id="calGrid"></div>
      <div class="cal-footer">
        <button class="cal-clear" id="calClear">Clear</button>
        <button class="cal-confirm" id="calConfirm">Confirm</button>
      </div>
    </div>
  </div>
`);

// Build a set of every date that belongs to at least one event (for highlighting)
const eventDateSet = new Set();
allEvents.forEach(ev => {
  if (!ev.event_StartDate) return;
  let d   = new Date(ev.event_StartDate + 'T00:00:00');
  const e = new Date((ev.event_EndDate || ev.event_StartDate) + 'T00:00:00');
  while (d <= e) {
    eventDateSet.add(d.toISOString().slice(0, 10));
    d.setDate(d.getDate() + 1);
  }
});

const today    = new Date();
const todayISO = today.toISOString().slice(0, 10);
let calYear    = today.getFullYear();
let calMonth   = today.getMonth();

// Temp state while the modal is open
let tempStart  = null;
let tempEnd    = null;
let hoverISO   = null;

function isoDate(y, m, d) {
  return `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
}

function fmtDisplay(iso) {
  if (!iso) return '';
  const [y, m, d] = iso.split('-');
  return `${d}/${m}/${y}`;
}

function renderCal() {
  // Work out the effective range to highlight (start always <= end)
  let hiStart = tempStart;
  let hiEnd   = tempEnd;

  // If we have a start but no confirmed end yet, use hover as preview end
  if (tempStart && !tempEnd && hoverISO) {
    if (hoverISO >= tempStart) {
      hiStart = tempStart;
      hiEnd   = hoverISO;
    } else {
      hiStart = hoverISO;
      hiEnd   = tempStart;
    }
  }

  // Update hint text
  const hint = document.getElementById('calHint');
  if (!tempStart) {
    hint.textContent = 'Click a start date';
  } else if (!tempEnd) {
    hint.textContent = `Start: ${fmtDisplay(tempStart)} — now pick an end date`;
  } else {
    hint.textContent = `${fmtDisplay(tempStart)} → ${fmtDisplay(tempEnd)}`;
  }

  // Month label
  document.getElementById('calMonthLabel').textContent =
    new Date(calYear, calMonth, 1).toLocaleString('default', { month: 'long', year: 'numeric' });

  const grid = document.getElementById('calGrid');
  grid.innerHTML = '';

  // Day-of-week headers
  ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'].forEach(label => {
    const el = document.createElement('div');
    el.className = 'cal-day-label';
    el.textContent = label;
    grid.appendChild(el);
  });

  const firstWeekday  = new Date(calYear, calMonth, 1).getDay();
  const daysThisMonth = new Date(calYear, calMonth + 1, 0).getDate();

  // Empty leading cells
  for (let i = 0; i < firstWeekday; i++) {
    const el = document.createElement('div');
    el.className = 'cal-day empty';
    grid.appendChild(el);
  }

  // Day cells
  for (let day = 1; day <= daysThisMonth; day++) {
    const iso = isoDate(calYear, calMonth, day);
    const el  = document.createElement('div');
    el.className = 'cal-day';
    el.textContent = day;

    if (eventDateSet.has(iso)) el.classList.add('has-event');
    if (iso === todayISO)       el.classList.add('today');

    if (hiStart && hiEnd) {
      if (iso === hiStart && iso === hiEnd) {
        el.classList.add('range-start', 'range-end');
      } else if (iso === hiStart) {
        el.classList.add('range-start');
      } else if (iso === hiEnd) {
        el.classList.add('range-end');
      } else if (iso > hiStart && iso < hiEnd) {
        el.classList.add('in-range');
      }
    } else if (hiStart && iso === hiStart) {
      // Single start selected, nothing hovered yet
      el.classList.add('range-start', 'range-end');
    }

    el.dataset.iso = iso;

    el.addEventListener('click', () => {
      if (!tempStart || (tempStart && tempEnd)) {
        // No start yet, or starting fresh after a complete selection
        tempStart = iso;
        tempEnd   = null;
      } else {
        // We have a start, now set the end
        if (iso < tempStart) {
          // Clicked before start — swap
          tempEnd   = tempStart;
          tempStart = iso;
        } else {
          tempEnd = iso;
        }
      }
      renderCal();
    });

    grid.appendChild(el);
  }
}

// Open modal
datePickerBtn.addEventListener('click', () => {
  // Pre-populate with any existing confirmed range
  tempStart = rangeStart;
  tempEnd   = rangeEnd;
  hoverISO  = null;
  renderCal();
  document.getElementById('calModal').classList.add('open');
});

// Month navigation
document.getElementById('calPrev').addEventListener('click', () => {
  calMonth--;
  if (calMonth < 0) { calMonth = 11; calYear--; }
  renderCal();
});
document.getElementById('calNext').addEventListener('click', () => {
  calMonth++;
  if (calMonth > 11) { calMonth = 0; calYear++; }
  renderCal();
});

// Clear button inside modal
document.getElementById('calClear').addEventListener('click', () => {
  tempStart = tempEnd = rangeStart = rangeEnd = null;
  datePickerBtn.textContent = '📅 Pick date';
  document.getElementById('calModal').classList.remove('open');
  runFilter();
});

// Confirm button
document.getElementById('calConfirm').addEventListener('click', () => {
  rangeStart = tempStart;
  rangeEnd   = tempEnd;
  if (rangeStart && rangeEnd) {
    datePickerBtn.textContent = `📅 ${fmtDisplay(rangeStart)} → ${fmtDisplay(rangeEnd)}`;
  } else if (rangeStart) {
    datePickerBtn.textContent = `📅 ${fmtDisplay(rangeStart)}`;
  }
  document.getElementById('calModal').classList.remove('open');
  runFilter();
});

// Close on overlay click
document.getElementById('calModal').addEventListener('click', e => {
  if (e.target === document.getElementById('calModal')) {
    document.getElementById('calModal').classList.remove('open');
  }
});

// Initial render
runFilter();
</script>

</body>
</html>