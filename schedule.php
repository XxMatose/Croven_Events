<?php
require_once 'db/db_hosted.php';
require_once 'auth.php';

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
            'ep_id'           => $row['ep_id'] ?? null,   // event_performers PK
            'name'            => $row['performer_Name'],
            'is_Headliner'    => $row['is_Headliner'],
            'is_Opener'       => $row['is_Opener'] ?? 0,
            'order_performed' => $row['order_performed'],
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

// ─── Fetch all venues + performers for the edit modal dropdowns ──────
$allVenues = [];
try {
    $vStmt = $pdo->query("SELECT venue_ID, venue_Name, venue_Address, venue_City, venue_State, venue_Type FROM venue ORDER BY venue_Name");
    $allVenues = $vStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$allPerformers = [];
try {
    $pStmt = $pdo->query("SELECT performer_ID, performer_Name FROM performer ORDER BY performer_Name");
    $allPerformers = $pStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$eventsJson    = json_encode(array_values($events));
$venuesJson    = json_encode(array_values($allVenues),     JSON_HEX_TAG);
$performersJson= json_encode(array_values($allPerformers), JSON_HEX_TAG);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Schedule – Croven Events</title>
  <link rel="stylesheet" href="css/styles.css">
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
         data-event-id="<?= (int)$event['event_ID'] ?>"
         data-event="<?= htmlspecialchars(strtolower($event['event_Name'])) ?>"
         data-venue="<?= htmlspecialchars(strtolower($event['venue_Name'] ?? '')) ?>"
         data-city="<?= htmlspecialchars(strtolower($event['venue_City'] ?? '')) ?>"
         data-state="<?= htmlspecialchars(strtolower($event['venue_State'] ?? '')) ?>"
         data-performers="<?= htmlspecialchars(strtolower(implode('|', $performers))) ?>"
         data-startdate="<?= htmlspecialchars($event['event_StartDate'] ?? '') ?>"
         data-enddate="<?= htmlspecialchars($event['event_EndDate'] ?? '') ?>">

      <div class="card-header">
        <div class="card-header-top">
          <span class="event-name"><?= htmlspecialchars($event['event_Name']) ?></span>
          <button class="card-edit-btn" data-event-id="<?= (int)$event['event_ID'] ?>" title="Edit event" onclick="openEditModal(<?= (int)$event['event_ID'] ?>)">✏️</button>
        </div>
        <div class="event-meta">
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
/* ── Card header layout ────────────────────────────────────────────── */
.card-header {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.card-header-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 8px;
}
.card-edit-btn {
  background: none;
  border: 1px solid var(--border-strong);
  border-radius: 7px;
  padding: 3px 7px;
  font-size: 0.8rem;
  cursor: pointer;
  opacity: 0.5;
  transition: opacity 0.15s, background 0.15s;
  color: inherit;
  line-height: 1;
  flex-shrink: 0;
}
.card-edit-btn:hover { opacity: 1; background: var(--input-bg); }

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

/* ══════════════════════════════════════════════════════════════════
   EDIT EVENT MODAL
══════════════════════════════════════════════════════════════════ */
.edit-modal-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.6);
  backdrop-filter: blur(4px);
  z-index: 900;
  align-items: center;
  justify-content: center;
  padding: 20px;
}
.edit-modal-overlay.open { display: flex; }

.edit-modal {
  background: var(--card-bg);
  border: 1px solid var(--border-strong);
  border-radius: 16px;
  width: 100%;
  max-width: 600px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 24px 64px rgba(0,0,0,0.45);
  overflow: hidden;
}

/* Header */
.edit-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 18px 22px 16px;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
}
.edit-modal-title {
  font-size: 1rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 8px;
}
.edit-modal-close {
  background: none;
  border: none;
  font-size: 1.2rem;
  cursor: pointer;
  color: inherit;
  opacity: 0.4;
  line-height: 1;
  padding: 4px 8px;
  border-radius: 6px;
  transition: opacity 0.15s;
}
.edit-modal-close:hover { opacity: 1; }

/* Scrollable body */
.edit-modal-body {
  overflow-y: auto;
  flex: 1;
  padding: 20px 22px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}

/* Section cards inside modal */
.em-section {
  background: var(--input-bg);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.em-section-title {
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  opacity: 0.4;
  margin-bottom: -4px;
}

/* Grid */
.em-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.em-full    { grid-column: 1 / -1; }
@media (max-width: 500px) { .em-grid-2 { grid-template-columns: 1fr; } }

/* Field */
.em-field { display: flex; flex-direction: column; gap: 4px; }
.em-label {
  font-size: 0.7rem;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  opacity: 0.45;
}
.em-label .req { color: var(--accent); margin-left: 2px; }
.em-input {
  padding: 8px 10px;
  border: 1px solid var(--border-strong);
  border-radius: 8px;
  font-size: 0.875rem;
  background: var(--card-bg);
  color: var(--text);
  font-family: inherit;
  outline: none;
  width: 100%;
  transition: border-color 0.15s;
}
.em-input:focus { border-color: var(--accent); }
.em-input::placeholder { opacity: 0.3; }

/* Venue dropdown */
.em-dd-wrap { position: relative; }
.em-dd {
  display: none;
  position: absolute;
  top: 100%;
  left: 0; right: 0;
  background: var(--card-bg);
  border: 1px solid var(--border-strong);
  border-radius: 8px;
  margin-top: 3px;
  max-height: 180px;
  overflow-y: auto;
  z-index: 9999;
  list-style: none;
  padding: 4px;
  box-shadow: 0 4px 20px rgba(0,0,0,0.25);
}
.em-dd.open { display: block; }
.em-dd li {
  padding: 8px 10px;
  border-radius: 6px;
  font-size: 0.82rem;
  cursor: pointer;
  color: var(--text);
  transition: background 0.1s;
}
.em-dd li:hover { background: var(--input-bg); }
.em-dd li .dd-sub { font-size: 0.72rem; opacity: 0.5; margin-top: 1px; }
.em-dd li.dd-new  { opacity: 0.55; font-style: italic; }

/* Performers inside modal */
.em-p-header {
  display: grid;
  grid-template-columns: 1fr 52px auto auto auto auto;
  gap: 6px;
  padding: 0 10px 4px;
  font-size: 0.65rem;
  font-weight: 700;
  letter-spacing: 0.07em;
  text-transform: uppercase;
  opacity: 0.4;
}
.em-p-header span { text-align: center; }
.em-p-header span:first-child { text-align: left; }
@media (max-width: 480px) { .em-p-header { display: none; } }

.em-p-list { display: flex; flex-direction: column; gap: 6px; }

.em-p-row {
  display: grid;
  grid-template-columns: 1fr 52px auto auto auto auto;
  gap: 6px;
  align-items: center;
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: 9px;
  padding: 8px 10px;
  animation: fadeIn 0.18s ease;
}
@media (max-width: 480px) {
  .em-p-row { grid-template-columns: 1fr 40px auto auto auto auto; gap: 4px; }
}

.em-p-name-wrap { position: relative; width: 100%; }
.em-p-name-input {
  width: 100%;
  padding: 6px 8px;
  border: 1px solid var(--border-strong);
  border-radius: 6px;
  font-size: 0.82rem;
  background: var(--input-bg);
  color: var(--text);
  font-family: inherit;
  outline: none;
  transition: border-color 0.15s;
}
.em-p-name-input:focus { border-color: var(--accent); }
.em-p-name-input::placeholder { opacity: 0.3; }

.em-p-order {
  width: 100%;
  padding: 6px 4px;
  border: 1px solid var(--border-strong);
  border-radius: 6px;
  font-size: 0.82rem;
  background: var(--input-bg);
  color: var(--text);
  font-family: inherit;
  outline: none;
  text-align: center;
}

/* Toggle badges */
.em-toggle {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  cursor: pointer;
  user-select: none;
}
.em-toggle input[type="checkbox"] { display: none; }
.em-toggle-label {
  font-size: 0.6rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  text-transform: uppercase;
  opacity: 0.4;
}
.em-toggle-box {
  width: 30px;
  height: 24px;
  border-radius: 6px;
  border: 1px solid var(--border-strong);
  background: var(--input-bg);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
  transition: background 0.15s, border-color 0.15s;
}
.em-toggle.is-headliner input:checked ~ .em-toggle-box {
  background: var(--headliner-bg);
  border-color: var(--headliner-text);
}
.em-toggle.is-opener input:checked ~ .em-toggle-box {
  background: var(--highlight);
  border-color: #9a6000;
}
.em-toggle.is-watched input:checked ~ .em-toggle-box {
  background: var(--watched-bg);
  border-color: var(--watched-text);
}

.em-p-remove {
  background: none;
  border: 1px solid var(--border-strong);
  color: var(--muted);
  width: 26px;
  height: 26px;
  border-radius: 5px;
  cursor: pointer;
  font-size: 0.9rem;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.15s, color 0.15s, border-color 0.15s;
  flex-shrink: 0;
}
.em-p-remove:hover { background: #fff0f0; border-color: #c0392b; color: #c0392b; }
body.dark .em-p-remove:hover { background: #2a1010; border-color: #ff6b6b; color: #ff6b6b; }
body.red  .em-p-remove:hover { background: #2a0000; border-color: #ff4d4d; color: #ff4d4d; }

.em-add-performer-btn {
  width: 100%;
  padding: 8px;
  border: 1px dashed var(--border-strong);
  border-radius: 8px;
  background: none;
  color: var(--muted);
  font-size: 0.82rem;
  font-family: inherit;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  transition: border-color 0.15s, color 0.15s, background 0.15s;
}
.em-add-performer-btn:hover {
  border-color: var(--accent);
  color: var(--text);
  background: var(--input-bg);
}

/* Footer */
.edit-modal-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  padding: 14px 22px;
  border-top: 1px solid var(--border);
  flex-shrink: 0;
}
.em-feedback {
  flex: 1;
  font-size: 0.8rem;
  min-height: 1em;
}
.em-feedback.error   { color: #f87171; }
.em-feedback.success { color: #4ade80; }
.em-btn-cancel {
  background: none;
  border: 1px solid var(--border-strong);
  border-radius: 8px;
  padding: 8px 18px;
  font-size: 0.875rem;
  cursor: pointer;
  color: inherit;
  opacity: 0.6;
  transition: opacity 0.15s;
}
.em-btn-cancel:hover { opacity: 1; }
.em-btn-save {
  background: var(--accent);
  border: none;
  border-radius: 8px;
  padding: 8px 22px;
  font-size: 0.875rem;
  font-weight: 700;
  cursor: pointer;
  color: #fff;
  transition: opacity 0.15s;
}
body.red .em-btn-save { background: #ff2b2b; }
.em-btn-save:hover    { opacity: 0.88; }
.em-btn-save:disabled { opacity: 0.4; cursor: not-allowed; }
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

<!-- ══ Edit Event Modal ══════════════════════════════════════════════ -->
<div class="edit-modal-overlay" id="editModalOverlay">
  <div class="edit-modal" id="editModal">

    <div class="edit-modal-header">
      <div class="edit-modal-title">✏️ Edit Event</div>
      <button class="edit-modal-close" id="editModalClose">&times;</button>
    </div>

    <div class="edit-modal-body">

      <!-- Event section -->
      <div class="em-section">
        <div class="em-section-title">Event</div>
        <div class="em-grid-2">
          <div class="em-field em-full">
            <label class="em-label">Event Name <span class="req">*</span></label>
            <input type="text" class="em-input" id="emEventName" placeholder="e.g. Lollapalooza 2024">
          </div>
          <div class="em-field">
            <label class="em-label">Start Date <span class="req">*</span></label>
            <input type="date" class="em-input" id="emStartDate">
          </div>
          <div class="em-field">
            <label class="em-label">End Date</label>
            <input type="date" class="em-input" id="emEndDate">
          </div>
        </div>
      </div>

      <!-- Venue section -->
      <div class="em-section">
        <div class="em-section-title">Venue</div>
        <div class="em-grid-2">
          <div class="em-field em-full em-dd-wrap">
            <label class="em-label">Venue Name <span class="req">*</span></label>
            <input type="text" class="em-input" id="emVenueName" placeholder="Search or type a venue…" autocomplete="off">
            <ul class="em-dd" id="emVenueDd" role="listbox"></ul>
          </div>
          <div class="em-field em-full">
            <label class="em-label">Address</label>
            <input type="text" class="em-input" id="emVenueAddress" placeholder="e.g. 337 E Randolph St">
          </div>
          <div class="em-field">
            <label class="em-label">City <span class="req">*</span></label>
            <input type="text" class="em-input" id="emVenueCity" placeholder="e.g. Chicago">
          </div>
          <div class="em-field">
            <label class="em-label">State <span class="req">*</span></label>
            <input type="text" class="em-input" id="emVenueState" placeholder="e.g. IL">
          </div>
          <div class="em-field em-full">
            <label class="em-label">Venue Type</label>
            <input type="text" class="em-input" id="emVenueType" placeholder="e.g. Outdoor Festival, Arena">
          </div>
        </div>
      </div>

      <!-- Performers section -->
      <div class="em-section">
        <div class="em-section-title">Performers</div>
        <div class="em-p-header">
          <span>Name</span>
          <span>Order</span>
          <span>Head</span>
          <span>Open</span>
          <span>Watch</span>
          <span></span>
        </div>
        <div class="em-p-list" id="emPerformerList"></div>
        <button type="button" class="em-add-performer-btn" id="emAddPerformerBtn">+ Add Performer</button>
      </div>

    </div>

    <div class="edit-modal-footer">
      <span class="em-feedback" id="emFeedback"></span>
      <button class="em-btn-cancel" id="emBtnCancel">Cancel</button>
      <button class="em-btn-save"   id="emBtnSave">Save Changes</button>
    </div>

  </div>
</div>

<style>
/* ── Fav modal styles ──────────────────────────────────────────────── */
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
.fav-dialog-title { font-size: 1rem; font-weight: 700; color: #f5c518; letter-spacing: 0.02em; }
.fav-dialog-close { background: none; border: none; font-size: 1.4rem; cursor: pointer; opacity: 0.4; color: inherit; line-height: 1; transition: opacity 0.15s; }
.fav-dialog-close:hover { opacity: 1; }
.fav-dialog-body { display: flex; flex-direction: column; gap: 6px; padding: 20px; }
.fav-label { font-size: 0.72rem; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase; opacity: 0.5; margin-bottom: 2px; }
.fav-input { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 9px 12px; font-size: 0.88rem; color: inherit; outline: none; width: 100%; box-sizing: border-box; transition: border-color 0.15s; margin-bottom: 10px; }
.fav-input:focus { border-color: rgba(245,197,24,0.5); }
.fav-url-readonly { font-family: monospace; font-size: 0.78rem; opacity: 0.65; cursor: default; }
.fav-select { appearance: auto; cursor: pointer; }
.fav-feedback { font-size: 0.82rem; min-height: 1.1em; text-align: center; margin-bottom: 4px; }
.fav-feedback.error   { color: #f87171; }
.fav-feedback.success { color: #4ade80; }
.fav-dialog-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 6px; }
.fav-cancel { background: none; border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; padding: 8px 18px; font-size: 0.88rem; cursor: pointer; color: inherit; opacity: 0.6; transition: opacity 0.15s; }
.fav-cancel:hover { opacity: 1; }
.fav-submit { background: #f5c518; border: none; border-radius: 8px; padding: 8px 20px; font-size: 0.88rem; font-weight: 700; cursor: pointer; color: #111; transition: opacity 0.15s; }
.fav-submit:hover    { opacity: 0.88; }
.fav-submit:disabled { opacity: 0.4; cursor: not-allowed; }
body.red .fav-submit { background: #ff2b2b; color: #fff; }
.fav-select option { background: var(--card-bg, #1e1e2e); color: inherit; }
body.dark .fav-select option { background: #1e1e1e; }
body.red  .fav-select option { background: #141414; color: #ff2b2b; }
</style>

<script>
const allEvents    = <?= $eventsJson ?>;
const VENUES_LIST  = <?= $venuesJson ?>;
const PERF_LIST    = <?= $performersJson ?>;

const cards       = () => document.querySelectorAll('#cardGrid .card');
const countEl     = document.getElementById('visibleCount');
const noResults   = document.getElementById('noResults');
const searchInput = document.getElementById('searchInput');
const clearBtn    = document.getElementById('clearBtn');
const datePickerBtn = document.getElementById('datePickerBtn');
const urlBuilderInput = document.getElementById('urlBuilderInput');
const urlCopyBtn      = document.getElementById('urlCopyBtn');

let activeMode  = 'event';
let rangeStart  = null;
let rangeEnd    = null;

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

  if (category && validModes.includes(category)) {
    activeMode = category;
    document.querySelectorAll('.filter-tab').forEach(t => {
      t.classList.toggle('active', t.dataset.mode === category);
    });
  }
  if (q) { searchInput.value = q; }
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

if (activeMode !== 'date') { datePickerBtn.style.display = 'none'; }
searchInput.addEventListener('input', runFilter);
clearBtn.addEventListener('click', () => {
  searchInput.value = '';
  rangeStart = rangeEnd = null;
  datePickerBtn.textContent = '📅 Pick date';
  runFilter();
});

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

// ── Calendar modal ─────────────────────────────────────────────────
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
  let hiStart = tempStart, hiEnd = tempEnd;
  if (tempStart && !tempEnd && hoverISO) {
    if (hoverISO >= tempStart) { hiStart = tempStart; hiEnd = hoverISO; }
    else { hiStart = hoverISO; hiEnd = tempStart; }
  }
  const hint = document.getElementById('calHint');
  if (!tempStart) hint.textContent = 'Click a start date';
  else if (!tempEnd) hint.textContent = `Start: ${fmtDisplay(tempStart)} — now pick an end date`;
  else hint.textContent = `${fmtDisplay(tempStart)} - ${fmtDisplay(tempEnd)}`;

  document.getElementById('calMonthLabel').textContent =
    new Date(calYear, calMonth, 1).toLocaleString('default', { month: 'long', year: 'numeric' });

  const grid = document.getElementById('calGrid');
  grid.innerHTML = '';
  ['Su','Mo','Tu','We','Th','Fr','Sa'].forEach(label => {
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
    if (iso === todayISO) el.classList.add('today');
    if (hiStart && hiEnd) {
      if (iso === hiStart && iso === hiEnd) el.classList.add('range-start', 'range-end');
      else if (iso === hiStart) el.classList.add('range-start');
      else if (iso === hiEnd)   el.classList.add('range-end');
      else if (iso > hiStart && iso < hiEnd) el.classList.add('in-range');
    } else if (hiStart && iso === hiStart) {
      el.classList.add('range-start', 'range-end');
    }
    el.dataset.iso = iso;
    el.addEventListener('click', () => {
      if (!tempStart || (tempStart && tempEnd)) { tempStart = iso; tempEnd = null; }
      else { if (iso < tempStart) { tempEnd = tempStart; tempStart = iso; } else { tempEnd = iso; } }
      renderCal();
    });
    grid.appendChild(el);
  }
}
datePickerBtn.addEventListener('click', () => {
  tempStart = rangeStart; tempEnd = rangeEnd; hoverISO = null;
  renderCal();
  document.getElementById('calModal').classList.add('open');
});
document.getElementById('calPrev').addEventListener('click', () => {
  calMonth--; if (calMonth < 0) { calMonth = 11; calYear--; } renderCal();
});
document.getElementById('calNext').addEventListener('click', () => {
  calMonth++; if (calMonth > 11) { calMonth = 0; calYear++; } renderCal();
});
document.getElementById('calClear').addEventListener('click', () => {
  tempStart = tempEnd = rangeStart = rangeEnd = null;
  datePickerBtn.textContent = '📅 Pick date';
  document.getElementById('calModal').classList.remove('open');
  runFilter();
});
document.getElementById('calConfirm').addEventListener('click', () => {
  rangeStart = tempStart; rangeEnd = tempEnd;
  if (rangeStart && rangeEnd) datePickerBtn.textContent = `📅 ${fmtDisplay(rangeStart)} - ${fmtDisplay(rangeEnd)}`;
  else if (rangeStart) datePickerBtn.textContent = `📅 ${fmtDisplay(rangeStart)}`;
  document.getElementById('calModal').classList.remove('open');
  runFilter();
});
document.getElementById('calModal').addEventListener('click', e => {
  if (e.target === document.getElementById('calModal')) document.getElementById('calModal').classList.remove('open');
});

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
  favUrlInput.value = currentUrl;
  favLabelInput.value = '';
  favFeedback.textContent = '';
  favFeedback.className = 'fav-feedback';
  favModal.classList.add('open');
});
function closeFavModal() { favModal.classList.remove('open'); }
favClose.addEventListener('click', closeFavModal);
favOverlay.addEventListener('click', closeFavModal);
favForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  const label = favLabelInput.value.trim(), path = favUrlInput.value.trim(), userId = favUserSelect.value;
  if (!label || !path || !userId) { favFeedback.textContent = 'Please fill in all fields.'; favFeedback.className = 'fav-feedback error'; return; }
  const submitBtn = favForm.querySelector('.fav-submit');
  submitBtn.disabled = true; submitBtn.textContent = 'Saving…';
  try {
    const res  = await fetch('save_favorite.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ label, path, user_id: parseInt(userId) }) });
    const data = await res.json();
    if (data.success) { favFeedback.textContent = '★ Favorite saved!'; favFeedback.className = 'fav-feedback success'; setTimeout(closeFavModal, 1200); }
    else { favFeedback.textContent = data.error || 'Failed to save.'; favFeedback.className = 'fav-feedback error'; }
  } catch (err) { favFeedback.textContent = 'Network error. Please try again.'; favFeedback.className = 'fav-feedback error'; }
  finally { submitBtn.disabled = false; submitBtn.textContent = 'Save Favorite'; }
});

// ══════════════════════════════════════════════════════════════════
// EDIT EVENT MODAL
// ══════════════════════════════════════════════════════════════════

let editingEventId  = null;
let emRowCount      = 0;
let removedEpIds    = [];  // event_performers IDs staged for deletion

function openEditModal(eventId) {
  const ev = allEvents.find(e => e.event_ID === eventId);
  if (!ev) return;

  editingEventId = eventId;
  removedEpIds   = [];
  emRowCount     = 0;

  // Populate event fields
  document.getElementById('emEventName').value = ev.event_Name  || '';
  document.getElementById('emStartDate').value  = ev.event_StartDate || '';
  document.getElementById('emEndDate').value    = ev.event_EndDate   || '';

  // Populate venue fields
  document.getElementById('emVenueName').value    = ev.venue_Name  || '';
  document.getElementById('emVenueAddress').value = '';  // not in view; leave blank
  document.getElementById('emVenueCity').value    = ev.venue_City  || '';
  document.getElementById('emVenueState').value   = ev.venue_State || '';
  document.getElementById('emVenueType').value    = '';  // not in view; leave blank

  // Try to enrich venue from VENUES_LIST
  const matchedVenue = VENUES_LIST.find(v => v.venue_Name.toLowerCase() === (ev.venue_Name || '').toLowerCase());
  if (matchedVenue) {
    document.getElementById('emVenueAddress').value = matchedVenue.venue_Address || '';
    document.getElementById('emVenueType').value    = matchedVenue.venue_Type    || '';
  }

  // Populate performers
  const list = document.getElementById('emPerformerList');
  list.innerHTML = '';
  (ev.performers || []).forEach(p => addEmPerformerRow({
    ep_id:        p.ep_id,
    name:         p.name,
    order:        p.order_performed,
    is_headliner: p.is_Headliner,
    is_opener:    p.is_Opener,
    watched:      p.watched,
  }));

  // Clear feedback
  setEmFeedback('', '');

  document.getElementById('editModalOverlay').classList.add('open');
  document.getElementById('emEventName').focus();
}

function closeEditModal() {
  document.getElementById('editModalOverlay').classList.remove('open');
  editingEventId = null;
}

document.getElementById('editModalClose').addEventListener('click', closeEditModal);
document.getElementById('emBtnCancel').addEventListener('click', closeEditModal);
document.getElementById('editModalOverlay').addEventListener('click', e => {
  if (e.target === document.getElementById('editModalOverlay')) closeEditModal();
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && document.getElementById('editModalOverlay').classList.contains('open')) closeEditModal();
});

// ── Add performer row inside edit modal ───────────────────────────
function addEmPerformerRow(opts = {}) {
  const i    = emRowCount++;
  const list = document.getElementById('emPerformerList');
  const row  = document.createElement('div');
  row.className   = 'em-p-row';
  row.id          = 'em-prow-' + i;
  row.dataset.epId = opts.ep_id || '';

  row.innerHTML = `
    <div class="em-p-name-wrap">
      <input type="text" class="em-p-name-input" placeholder="Performer name"
             value="${escHtml(opts.name || '')}" autocomplete="off">
      <ul class="em-dd em-p-dd" role="listbox"></ul>
    </div>
    <input type="number" class="em-p-order" placeholder="#" min="1"
           value="${opts.order !== undefined ? opts.order : list.children.length + 1}">
    <label class="em-toggle is-headliner" title="Headliner">
      <input type="checkbox" class="em-p-head" ${opts.is_headliner ? 'checked' : ''}>
      <span class="em-toggle-label">Head</span>
      <span class="em-toggle-box">🎤</span>
    </label>
    <label class="em-toggle is-opener" title="Opener">
      <input type="checkbox" class="em-p-opener" ${opts.is_opener ? 'checked' : ''}>
      <span class="em-toggle-label">Open</span>
      <span class="em-toggle-box">🎸</span>
    </label>
    <label class="em-toggle is-watched" title="Watched">
      <input type="checkbox" class="em-p-watched" ${opts.watched ? 'checked' : ''}>
      <span class="em-toggle-label">Watch</span>
      <span class="em-toggle-box">👁️</span>
    </label>
    <button type="button" class="em-p-remove" title="Remove">×</button>
  `;

  // Remove: if existing ep_id, stage for deletion
  row.querySelector('.em-p-remove').addEventListener('click', () => {
    const epId = parseInt(row.dataset.epId);
    if (epId > 0) removedEpIds.push(epId);
    row.remove();
  });

  // Attach performer combobox
  attachEmPerformerCombobox(
    row.querySelector('.em-p-name-input'),
    row.querySelector('.em-p-dd')
  );

  list.appendChild(row);
}

document.getElementById('emAddPerformerBtn').addEventListener('click', () => addEmPerformerRow());

// ── Performer combobox inside edit modal ──────────────────────────
function attachEmPerformerCombobox(input, dd) {
  function renderList(q) {
    dd.innerHTML = '';
    const lower   = q.toLowerCase().trim();
    const matches = lower
      ? PERF_LIST.filter(p => p.performer_Name.toLowerCase().includes(lower))
      : PERF_LIST;
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

// ── Venue combobox inside edit modal ──────────────────────────────
(function () {
  const input = document.getElementById('emVenueName');
  const dd    = document.getElementById('emVenueDd');

  function fillVenue(v) {
    input.value = v.venue_Name || '';
    document.getElementById('emVenueAddress').value = v.venue_Address || '';
    document.getElementById('emVenueCity').value    = v.venue_City    || '';
    document.getElementById('emVenueState').value   = v.venue_State   || '';
    document.getElementById('emVenueType').value    = v.venue_Type    || '';
  }

  function renderList(q) {
    dd.innerHTML = '';
    const lower   = q.toLowerCase().trim();
    const matches = lower
      ? VENUES_LIST.filter(v => v.venue_Name.toLowerCase().includes(lower))
      : VENUES_LIST;
    matches.forEach(v => {
      const li = document.createElement('li');
      li.innerHTML = `<div>${escHtml(v.venue_Name)}</div>
        <div class="dd-sub">${escHtml([v.venue_City, v.venue_State].filter(Boolean).join(', '))}</div>`;
      li.addEventListener('mousedown', e => {
        e.preventDefault();
        fillVenue(v);
        dd.classList.remove('open');
      });
      dd.appendChild(li);
    });
    if (lower && !matches.find(v => v.venue_Name.toLowerCase() === lower)) {
      const li = document.createElement('li');
      li.className = 'dd-new';
      li.textContent = `+ New venue: "${q}"`;
      dd.appendChild(li);
    }
    dd.classList.toggle('open', dd.children.length > 0);
  }

  input.addEventListener('focus', () => renderList(input.value));
  input.addEventListener('input', () => renderList(input.value));
  input.addEventListener('blur',  () => setTimeout(() => dd.classList.remove('open'), 150));
})();

// ── Save edit ─────────────────────────────────────────────────────
document.getElementById('emBtnSave').addEventListener('click', async () => {
  const eventName  = document.getElementById('emEventName').value.trim();
  const startDate  = document.getElementById('emStartDate').value;
  const endDate    = document.getElementById('emEndDate').value;
  const venueName  = document.getElementById('emVenueName').value.trim();
  const venueCity  = document.getElementById('emVenueCity').value.trim();
  const venueState = document.getElementById('emVenueState').value.trim();

  if (!eventName || !startDate || !venueName || !venueCity || !venueState) {
    setEmFeedback('Please fill in all required fields.', 'error');
    return;
  }

  // Collect performers
  const performers = [];
  document.querySelectorAll('#emPerformerList .em-p-row').forEach((row, idx) => {
    const name = row.querySelector('.em-p-name-input').value.trim();
    if (!name) return;
    performers.push({
      ep_id:        parseInt(row.dataset.epId) || 0,
      name,
      order:        parseInt(row.querySelector('.em-p-order').value) || (idx + 1),
      is_headliner: row.querySelector('.em-p-head').checked    ? 1 : 0,
      is_opener:    row.querySelector('.em-p-opener').checked  ? 1 : 0,
      watched:      row.querySelector('.em-p-watched').checked ? 1 : 0,
    });
  });

  const saveBtn = document.getElementById('emBtnSave');
  saveBtn.disabled = true;
  saveBtn.textContent = 'Saving…';

  try {
    const res  = await fetch('event_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action:         'update',
        event_id:       editingEventId,
        event_name:     eventName,
        start_date:     startDate,
        end_date:       endDate,
        venue_name:     venueName,
        venue_address:  document.getElementById('emVenueAddress').value.trim(),
        venue_city:     venueCity,
        venue_state:    venueState,
        venue_type:     document.getElementById('emVenueType').value.trim(),
        performers,
        removed_ep_ids: removedEpIds,
      })
    });
    const data = await res.json();

    if (data.success) {
      setEmFeedback('Saved!', 'success');
      updateCardInPlace(editingEventId, { eventName, startDate, endDate, venueName, venueCity, venueState, performers });
      setTimeout(closeEditModal, 900);
    } else {
      setEmFeedback(data.error || 'Save failed.', 'error');
    }
  } catch (err) {
    setEmFeedback('Network error. Please try again.', 'error');
  } finally {
    saveBtn.disabled = false;
    saveBtn.textContent = 'Save Changes';
  }
});

// ── Update card in place ──────────────────────────────────────────
function updateCardInPlace(eventId, data) {
  const card = document.querySelector(`.card[data-event-id="${eventId}"]`);
  if (!card) return;

  const { eventName, startDate, endDate, venueName, venueCity, venueState, performers } = data;

  // Event name
  const nameEl = card.querySelector('.event-name');
  if (nameEl) nameEl.textContent = eventName;

  // Date
  const metaEl = card.querySelector('.event-meta span');
  if (metaEl) {
    const s = startDate ? fmtDate(startDate) : '—';
    const e = endDate   ? fmtDate(endDate)   : null;
    metaEl.textContent = s + (e && e !== s ? ' – ' + e : '');
  }

  // Venue
  const venueRow = card.querySelector('.venue-row');
  if (venueRow) venueRow.childNodes[venueRow.childNodes.length - 1].textContent = ' ' + venueName;
  const venueLoc = card.querySelector('.venue-location');
  if (venueLoc) {
    const parts = [venueCity, venueState].filter(Boolean);
    venueLoc.textContent = parts.join(', ');
  }

  // Performers
  const perfList = card.querySelector('.performer-list');
  if (perfList) {
    // Remove all existing performer rows
    perfList.querySelectorAll('.performer-row').forEach(r => r.remove());
    const labelEl = perfList.querySelector('.performer-list-label');

    if (performers.length === 0) {
      const noEl = document.createElement('span');
      noEl.className = 'no-performers';
      noEl.textContent = 'No performers listed';
      perfList.appendChild(noEl);
    } else {
      perfList.querySelector('.no-performers')?.remove();
      performers.forEach(p => {
        const row = document.createElement('div');
        row.className = 'performer-row' +
          (p.is_headliner ? ' headliner' : '') +
          (p.watched ? ' watched' : ' not-watched');

        row.innerHTML = `
          <div class="performer-left">
            <span class="performer-name">${escHtml(p.name)}</span>
          </div>
          <div class="badge-row">
            ${p.is_headliner ? '<span class="badge badge-headliner">Headliner</span>' : ''}
          </div>
        `;
        perfList.appendChild(row);
      });
    }
  }

  // Update data attributes for filtering
  card.dataset.event      = eventName.toLowerCase();
  card.dataset.venue      = venueName.toLowerCase();
  card.dataset.city       = venueCity.toLowerCase();
  card.dataset.state      = venueState.toLowerCase();
  card.dataset.performers = performers.map(p => p.name.toLowerCase()).join('|');
  card.dataset.startdate  = startDate;
  card.dataset.enddate    = endDate;
}

function fmtDate(iso) {
  if (!iso) return '';
  const [y, m, d] = iso.split('-');
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  return `${months[parseInt(m, 10) - 1]} ${String(d).replace(/^0/, '')} ${y}`;
}

function setEmFeedback(msg, type) {
  const el = document.getElementById('emFeedback');
  el.textContent = msg;
  el.className = 'em-feedback' + (type ? ' ' + type : '');
}

function escHtml(s) {
  return String(s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

</body>
</html>