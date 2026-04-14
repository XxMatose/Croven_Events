<?php
require_once 'db.php';

// ─── Query users for Favorites dropdown ─────────────────────────────
$usersStmt = $pdo->query("SELECT id, name FROM users ORDER BY name ASC");
$favUsers = $usersStmt->fetchAll();

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
            'venue_City'      => $row['venue_City'],
            'venue_State'     => $row['venue_State'],
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
    <span class="filter-tab" data-mode="city">City</span>
    <span class="filter-tab" data-mode="state">State</span>
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
  <!-- URL Builder -->
  <div class="url-builder-wrap">
    <span class="url-builder-label">URL</span>
    <input type="text" id="urlBuilderInput" class="url-builder-input" readonly placeholder="Select a category and enter a search term…">
    <button class="url-builder-copy" id="urlCopyBtn" title="Copy URL">&#10697;</button>
    <button class="url-builder-fav" id="urlFavBtn" title="Save as Favorite">&#9733;</button>
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
      $start = $event['event_StartDate'] ? date('M d Y', strtotime($event['event_StartDate'])) : '—';
      $end   = $event['event_EndDate']   ? date('M d Y', strtotime($event['event_EndDate']))   : null;
      $performers = array_map(fn($p) => $p['name'], $event['performers']);
    ?>

    <div class="card"
         data-event="<?= htmlspecialchars(strtolower($event['event_Name'])) ?>"
         data-venue="<?= htmlspecialchars(strtolower($event['venue_Name'] ?? '')) ?>"
         data-city="<?= htmlspecialchars(strtolower($event['venue_City'] ?? '')) ?>"
         data-state="<?= htmlspecialchars(strtolower($event['venue_State'] ?? '')) ?>"
         data-performers="<?= htmlspecialchars(strtolower(implode('|', $performers))) ?>"
         data-startdate="<?= htmlspecialchars($event['event_StartDate'] ?? '') ?>"
         data-enddate="<?= htmlspecialchars($event['event_EndDate'] ?? '') ?>">

      <div class="card-header">
        <span class="event-name"><?= htmlspecialchars($event['event_Name']) ?></span>
        <div class="event-meta">
          <!-- <span><?= htmlspecialchars($event['event_Year']) ?></span> -->
          <span><?= $start ?><?= $end && $end !== $start ? ' – ' . $end : '' ?></span>
        </div>
      </div>

      <?php if (!empty($event['venue_Name'])): ?>
        <div class="venue-row">
          <span class="venue-icon">&#9679;</span>
          <?= htmlspecialchars($event['venue_Name']) ?>
        </div>
        <?php
          $cityState = array_filter([$event['venue_City'] ?? '', $event['venue_State'] ?? '']);
        ?>
        <?php if (!empty($cityState)): ?>
          <div class="venue-location">
            <?= htmlspecialchars(implode(', ', $cityState)) ?>
          </div>
        <?php endif; ?>
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

<style>
/* ── URL Builder ───────────────────────────────────────────────────── */
.url-builder-wrap {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 8px 16px 0;
  padding: 8px 12px;
  background: var(--url-builder-bg, rgba(255,255,255,0.04));
  border: 1px solid var(--url-builder-border, rgba(255,255,255,0.09));
  border-radius: 10px;
}
.url-builder-label {
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  opacity: 0.4;
  flex-shrink: 0;
}
.url-builder-input {
  flex: 1;
  background: none;
  border: none;
  outline: none;
  font-size: 0.82rem;
  font-family: monospace;
  color: var(--url-builder-text, inherit);
  opacity: 0.75;
  cursor: text;
  min-width: 0;
}
.url-builder-input::placeholder {
  opacity: 0.35;
  font-family: inherit;
  font-size: 0.8rem;
}
.url-builder-copy {
  background: none;
  border: 1px solid var(--url-builder-border, rgba(255,255,255,0.12));
  border-radius: 7px;
  padding: 3px 9px;
  font-size: 1rem;
  cursor: pointer;
  color: inherit;
  opacity: 0.5;
  transition: opacity 0.15s, background 0.15s;
  flex-shrink: 0;
}
.url-builder-copy:hover  { opacity: 1; background: rgba(255,255,255,0.08); }
.url-builder-copy.copied { opacity: 1; color: #4caf50; border-color: #4caf50; }
</style>

<!-- ── Favorites Modal ──────────────────────────────────────────────── -->
<div class="fav-modal-wrap" id="favModal">
  <div class="fav-overlay" id="favOverlay"></div>
  <div class="fav-dialog">
    <div class="fav-dialog-header">
      <span class="fav-dialog-title">&#9733; Save Favorite</span>
      <button class="fav-dialog-close" id="favClose" title="Close">&times;</button>
    </div>
    <form id="favForm" class="fav-dialog-body">
      <label class="fav-label" for="favLabel">Label</label>
      <input class="fav-input" type="text" id="favLabel" placeholder="e.g. Summer Shows in Texas" required>

      <label class="fav-label" for="favUrl">URL</label>
      <input class="fav-input fav-url-readonly" type="text" id="favUrl" readonly>

      <label class="fav-label" for="favUser">User</label>
      <select class="fav-input fav-select" id="favUser" required>
        <option value="">— Select a user —</option>
        <?php foreach ($favUsers as $u): ?>
          <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <div class="fav-feedback" id="favFeedback"></div>

      <div class="fav-dialog-footer">
        <button type="button" class="fav-cancel" onclick="document.getElementById('favModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="fav-submit">Save Favorite</button>
      </div>
    </form>
  </div>
</div>

<style>
/* ── Star button ───────────────────────────────────────────────────── */
.url-builder-fav {
  background: none;
  border: 1px solid var(--url-builder-border, rgba(255,255,255,0.12));
  border-radius: 7px;
  padding: 3px 9px;
  font-size: 1.05rem;
  cursor: pointer;
  color: inherit;
  opacity: 0.5;
  transition: opacity 0.15s, background 0.15s, color 0.15s;
  flex-shrink: 0;
}
.url-builder-fav:hover { opacity: 1; color: #f5c518; background: rgba(245,197,24,0.08); border-color: rgba(245,197,24,0.35); }
@keyframes fav-shake {
  0%,100% { transform: translateX(0); }
  25%      { transform: translateX(-4px); }
  75%      { transform: translateX(4px); }
}
.fav-shake { animation: fav-shake 0.35s ease; }

/* ── Modal wrapper ─────────────────────────────────────────────────── */
.fav-modal-wrap {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 1000;
  align-items: center;
  justify-content: center;
}
.fav-modal-wrap.open { display: flex; }

.fav-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0.55);
  backdrop-filter: blur(3px);
}

/* ── Dialog box ────────────────────────────────────────────────────── */
.fav-dialog {
  position: relative;
  z-index: 1;
  background: var(--card-bg, #1e1e2e);
  border: 1px solid rgba(255,255,255,0.12);
  border-radius: 14px;
  width: min(420px, 92vw);
  box-shadow: 0 20px 60px rgba(0,0,0,0.5);
  overflow: hidden;
}
.fav-dialog-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px 14px;
  border-bottom: 1px solid rgba(255,255,255,0.08);
}
.fav-dialog-title {
  font-size: 1rem;
  font-weight: 700;
  color: #f5c518;
  letter-spacing: 0.02em;
}
.fav-dialog-close {
  background: none;
  border: none;
  font-size: 1.4rem;
  cursor: pointer;
  opacity: 0.4;
  color: inherit;
  line-height: 1;
  transition: opacity 0.15s;
}
.fav-dialog-close:hover { opacity: 1; }

/* ── Form body ─────────────────────────────────────────────────────── */
.fav-dialog-body {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 20px;
}
.fav-label {
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  opacity: 0.5;
  margin-bottom: 2px;
}
.fav-input {
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 8px;
  padding: 9px 12px;
  font-size: 0.88rem;
  color: inherit;
  outline: none;
  width: 100%;
  box-sizing: border-box;
  transition: border-color 0.15s;
  margin-bottom: 10px;
}
.fav-input:focus { border-color: rgba(245,197,24,0.5); }
.fav-url-readonly {
  font-family: monospace;
  font-size: 0.78rem;
  opacity: 0.65;
  cursor: default;
}
.fav-select { appearance: auto; cursor: pointer; }

/* ── Feedback ──────────────────────────────────────────────────────── */
.fav-feedback { font-size: 0.82rem; min-height: 1.1em; text-align: center; margin-bottom: 4px; }
.fav-feedback.error   { color: #f87171; }
.fav-feedback.success { color: #4ade80; }

/* ── Footer buttons ────────────────────────────────────────────────── */
.fav-dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 6px;
}
.fav-cancel {
  background: none;
  border: 1px solid rgba(255,255,255,0.15);
  border-radius: 8px;
  padding: 8px 18px;
  font-size: 0.88rem;
  cursor: pointer;
  color: inherit;
  opacity: 0.6;
  transition: opacity 0.15s;
}
.fav-cancel:hover { opacity: 1; }
.fav-submit {
  background: #f5c518;
  border: none;
  border-radius: 8px;
  padding: 8px 20px;
  font-size: 0.88rem;
  font-weight: 700;
  cursor: pointer;
  color: #111;
  transition: opacity 0.15s;
}
.fav-submit:hover    { opacity: 0.88; }
.fav-submit:disabled { opacity: 0.4; cursor: not-allowed; }
body.red .fav-submit { background: #ff2b2b; color: #fff; }

/* ── Dropdown option background ────────────────────────────────── */
.fav-select option { background: var(--card-bg, #1e1e2e); color: inherit; }
body.dark .fav-select option { background: #1e1e1e; }
body.red  .fav-select option { background: #141414; color: #ff2b2b; }
</style>

<script>
const allEvents   = <?= $eventsJson ?>;
const cards       = () => document.querySelectorAll('#cardGrid .card');
const countEl     = document.getElementById('visibleCount');
const noResults   = document.getElementById('noResults');
const searchInput = document.getElementById('searchInput');
const clearBtn    = document.getElementById('clearBtn');
const datePickerBtn = document.getElementById('datePickerBtn');
const urlBuilderInput = document.getElementById('urlBuilderInput');
const urlCopyBtn      = document.getElementById('urlCopyBtn');

let activeMode  = 'event';
let rangeStart  = null;  // confirmed ISO start
let rangeEnd    = null;  // confirmed ISO end (null = single day)

// ── URL Builder ────────────────────────────────────────────────────
function buildUrl() {
  const base   = window.location.pathname.split('/').pop() || 'schedule.php';
  const params = new URLSearchParams();

  params.set('category', activeMode);

  if (activeMode === 'date') {
    if (rangeStart) params.set('start', rangeStart);
    if (rangeEnd)   params.set('end',   rangeEnd);
  } else {
    const q = searchInput.value.trim();
    if (q) params.set('q', q);
  }

  const hasFilter = activeMode === 'date' ? rangeStart : searchInput.value.trim();
  urlBuilderInput.value = hasFilter ? `${base}?${params.toString()}` : '';
}

// Copy button
urlCopyBtn.addEventListener('click', () => {
  const val = urlBuilderInput.value;
  if (!val) return;
  navigator.clipboard.writeText(val).then(() => {
    urlCopyBtn.classList.add('copied');
    urlCopyBtn.textContent = '✓';
    setTimeout(() => {
      urlCopyBtn.classList.remove('copied');
      urlCopyBtn.innerHTML = '&#10697;';
    }, 1500);
  });
});

// ── Read URL parameters ────────────────────────────────────────────
(function applyUrlParams() {
  const params   = new URLSearchParams(window.location.search);
  const category = (params.get('category') || '').toLowerCase().trim();
  const q        = (params.get('q')        || '').trim();
  const start    = (params.get('start')    || '').trim();
  const end      = (params.get('end')      || '').trim();

  const validModes = ['event', 'venue', 'city', 'state', 'performer', 'date'];

  // Apply category tab
  if (category && validModes.includes(category)) {
    activeMode = category;
    document.querySelectorAll('.filter-tab').forEach(t => {
      t.classList.toggle('active', t.dataset.mode === category);
    });
  }

  // Apply search text
  if (q) {
    searchInput.value = q;
  }

  // Apply date range (only meaningful when category=date)
  if (activeMode === 'date') {
    datePickerBtn.style.display = 'inline-block';
    if (start) {
      rangeStart = start;
      rangeEnd   = end || null;
      if (rangeStart && rangeEnd) {
        datePickerBtn.textContent = `📅 ${fmtDisplay(rangeStart)} - ${fmtDisplay(rangeEnd)}`;
      } else if (rangeStart) {
        datePickerBtn.textContent = `📅 ${fmtDisplay(rangeStart)}`;
      }
    }
  }
})();

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

// Hide date picker button unless on Date tab (only if not already shown by URL param)
if (activeMode !== 'date') {
  datePickerBtn.style.display = 'none';
}

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
    } else if (activeMode === 'city') {
      show = card.dataset.city.includes(q);
    } else if (activeMode === 'state') {
      show = card.dataset.state.includes(q);
    } else if (activeMode === 'performer') {
      show = card.dataset.performers.includes(q);
    }

    card.classList.toggle('hidden', !show);
    if (show) visible++;
  });

  countEl.textContent = visible + ' event' + (visible !== 1 ? 's' : '');
  noResults.style.display = visible === 0 ? 'block' : 'none';
  buildUrl();
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
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  return `${months[parseInt(m, 10) - 1]} ${parseInt(d, 10)} ${y}`;
}

function renderCal() {
  let hiStart = tempStart;
  let hiEnd   = tempEnd;

  if (tempStart && !tempEnd && hoverISO) {
    if (hoverISO >= tempStart) {
      hiStart = tempStart;
      hiEnd   = hoverISO;
    } else {
      hiStart = hoverISO;
      hiEnd   = tempStart;
    }
  }

  const hint = document.getElementById('calHint');
  if (!tempStart) {
    hint.textContent = 'Click a start date';
  } else if (!tempEnd) {
    hint.textContent = `Start: ${fmtDisplay(tempStart)} — now pick an end date`;
  } else {
    hint.textContent = `${fmtDisplay(tempStart)} - ${fmtDisplay(tempEnd)}`;
  }

  document.getElementById('calMonthLabel').textContent =
    new Date(calYear, calMonth, 1).toLocaleString('default', { month: 'long', year: 'numeric' });

  const grid = document.getElementById('calGrid');
  grid.innerHTML = '';

  ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'].forEach(label => {
    const el = document.createElement('div');
    el.className = 'cal-day-label';
    el.textContent = label;
    grid.appendChild(el);
  });

  const firstWeekday  = new Date(calYear, calMonth, 1).getDay();
  const daysThisMonth = new Date(calYear, calMonth + 1, 0).getDate();

  for (let i = 0; i < firstWeekday; i++) {
    const el = document.createElement('div');
    el.className = 'cal-day empty';
    grid.appendChild(el);
  }

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
      el.classList.add('range-start', 'range-end');
    }

    el.dataset.iso = iso;

    el.addEventListener('click', () => {
      if (!tempStart || (tempStart && tempEnd)) {
        tempStart = iso;
        tempEnd   = null;
      } else {
        if (iso < tempStart) {
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
    datePickerBtn.textContent = `📅 ${fmtDisplay(rangeStart)} - ${fmtDisplay(rangeEnd)}`;
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

// ── Favorites Modal ────────────────────────────────────────────────
const favBtn        = document.getElementById('urlFavBtn');
const favModal      = document.getElementById('favModal');
const favOverlay    = document.getElementById('favOverlay');
const favForm       = document.getElementById('favForm');
const favLabelInput = document.getElementById('favLabel');
const favUrlInput   = document.getElementById('favUrl');
const favUserSelect = document.getElementById('favUser');
const favClose      = document.getElementById('favClose');
const favFeedback   = document.getElementById('favFeedback');

favBtn.addEventListener('click', () => {
  const currentUrl = urlBuilderInput.value.trim();
  if (!currentUrl) {
    favBtn.classList.add('fav-shake');
    setTimeout(() => favBtn.classList.remove('fav-shake'), 500);
    return;
  }
  favUrlInput.value   = currentUrl;
  favLabelInput.value = '';
  favFeedback.textContent = '';
  favFeedback.className   = 'fav-feedback';
  favModal.classList.add('open');
});

function closeFavModal() {
  favModal.classList.remove('open');
}
favClose.addEventListener('click', closeFavModal);
favOverlay.addEventListener('click', closeFavModal);

favForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const label  = favLabelInput.value.trim();
  const path   = favUrlInput.value.trim();
  const userId = favUserSelect.value;

  if (!label || !path || !userId) {
    favFeedback.textContent = 'Please fill in all fields.';
    favFeedback.className   = 'fav-feedback error';
    return;
  }

  const submitBtn = favForm.querySelector('.fav-submit');
  submitBtn.disabled = true;
  submitBtn.textContent = 'Saving…';

  try {
    const res  = await fetch('save_favorite.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ label, path, user_id: parseInt(userId) })
    });
    const data = await res.json();

    if (data.success) {
      favFeedback.textContent = '★ Favorite saved!';
      favFeedback.className   = 'fav-feedback success';
      setTimeout(closeFavModal, 1200);
    } else {
      favFeedback.textContent = data.error || 'Failed to save.';
      favFeedback.className   = 'fav-feedback error';
    }
  } catch (err) {
    favFeedback.textContent = 'Network error. Please try again.';
    favFeedback.className   = 'fav-feedback error';
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Save Favorite';
  }
});
</script>

</body>
</html>