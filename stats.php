<?php
require_once 'db.php';

// ─── Fetch all raw data ──────────────────────────────────────────────
$stmt = $pdo->query("SELECT * FROM vw_full_event ORDER BY event_StartDate ASC");
$rows = $stmt->fetchAll();

// ─── Build unified event map ─────────────────────────────────────────
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
            'name'         => $row['performer_Name'],
            'is_Headliner' => (bool)$row['is_Headliner'],
            'watched'      => (int)$row['watched'] === 1,
        ];
    }
}

// ─── Aggregate: Performers ────────────────────────────────────────────
// performer_name => [ total_seen, headliner_count, venues[], events[], years[] ]
$performerStats = [];
foreach ($events as $ev) {
    foreach ($ev['performers'] as $p) {
        $name = $p['name'];
        if (!isset($performerStats[$name])) {
            $performerStats[$name] = [
                'name'           => $name,
                'total'          => 0,
                'headliner_count'=> 0,
                'watched_count'  => 0,
                'appearances'    => [],
            ];
        }
        $performerStats[$name]['total']++;
        if ($p['is_Headliner']) $performerStats[$name]['headliner_count']++;
        if ($p['watched'])      $performerStats[$name]['watched_count']++;
        $performerStats[$name]['appearances'][] = [
            'event_Name'  => $ev['event_Name'],
            'event_Year'  => $ev['event_Year'],
            'start_date'  => $ev['event_StartDate'],
            'venue_Name'  => $ev['venue_Name'],
            'venue_City'  => $ev['venue_City'],
            'venue_State' => $ev['venue_State'],
            'is_Headliner'=> $p['is_Headliner'],
            'watched'     => $p['watched'],
        ];
    }
}
uasort($performerStats, fn($a, $b) => $b['total'] <=> $a['total']);

// ─── Aggregate: Venues ───────────────────────────────────────────────
$venueStats = [];
foreach ($events as $ev) {
    $vname = $ev['venue_Name'] ?? 'Unknown Venue';
    if (!isset($venueStats[$vname])) {
        $venueStats[$vname] = [
            'name'       => $vname,
            'city'       => $ev['venue_City'],
            'state'      => $ev['venue_State'],
            'total'      => 0,
            'appearances'=> [],
        ];
    }
    $venueStats[$vname]['total']++;
    $venueStats[$vname]['appearances'][] = [
        'event_Name' => $ev['event_Name'],
        'start_date' => $ev['event_StartDate'],
        'end_date'   => $ev['event_EndDate'],
        'year'       => $ev['event_Year'],
        'performers' => $ev['performers'],
    ];
}
uasort($venueStats, fn($a, $b) => $b['total'] <=> $a['total']);

// ─── Aggregate: Years ────────────────────────────────────────────────
$yearStats = [];
foreach ($events as $ev) {
    $yr = $ev['event_Year'] ?? 'Unknown';
    if (!isset($yearStats[$yr])) {
        $yearStats[$yr] = ['year' => $yr, 'total' => 0, 'events' => []];
    }
    $yearStats[$yr]['total']++;
    $yearStats[$yr]['events'][] = [
        'event_Name'  => $ev['event_Name'],
        'start_date'  => $ev['event_StartDate'],
        'end_date'    => $ev['event_EndDate'],
        'venue_Name'  => $ev['venue_Name'],
        'venue_City'  => $ev['venue_City'],
        'venue_State' => $ev['venue_State'],
        'performers'  => $ev['performers'],
    ];
}
// Sort events within each year by start date
foreach ($yearStats as &$y) {
    usort($y['events'], fn($a, $b) => strcmp($a['start_date'] ?? '', $b['start_date'] ?? ''));
}
unset($y);
krsort($yearStats);

// ─── Summary stats ───────────────────────────────────────────────────
$totalEvents     = count($events);
$totalPerformers = count($performerStats);
$totalVenues     = count($venueStats);
$totalYears      = count($yearStats);

$eventsJson    = json_encode(array_values($events));
$performerJson = json_encode(array_values($performerStats));
$venueJson     = json_encode(array_values($venueStats));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Stats – Croven Events</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    /* ── Page layout ─────────────────────────────────────────────── */
    .stats-wrap {
      max-width: 1100px;
      margin: 0 auto;
      padding: 0 16px 60px;
    }

    /* ── Summary tiles ───────────────────────────────────────────── */
    .summary-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 14px;
      margin: 20px 0 28px;
    }
    .summary-tile {
      background: var(--card-bg, rgba(255,255,255,0.05));
      border: 1px solid var(--card-border, rgba(255,255,255,0.1));
      border-radius: 14px;
      padding: 18px 20px 16px;
      text-align: center;
    }
    .summary-tile .tile-number {
      font-size: 2.2rem;
      font-weight: 800;
      line-height: 1;
      margin-bottom: 6px;
    }
    .summary-tile .tile-label {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      opacity: 0.5;
    }

    /* ── Mode tabs ───────────────────────────────────────────────── */
    .stats-tabs {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-bottom: 20px;
    }
    .stats-tab {
      padding: 8px 18px;
      border-radius: 20px;
      border: 1px solid var(--card-border, rgba(255,255,255,0.12));
      cursor: pointer;
      font-size: 0.85rem;
      font-weight: 600;
      opacity: 0.55;
      transition: opacity 0.15s, background 0.15s;
      user-select: none;
      background: transparent;
      color: inherit;
    }
    .stats-tab.active {
      opacity: 1;
      background: var(--accent, rgba(255,255,255,0.12));
      border-color: transparent;
    }
    .stats-tab:hover:not(.active) { opacity: 0.85; }

    /* ── Search bar ──────────────────────────────────────────────── */
    .stats-search-wrap {
      display: flex;
      gap: 10px;
      margin-bottom: 22px;
      align-items: center;
    }
    .stats-search-inner {
      position: relative;
      flex: 1;
    }
    .stats-search-inner .search-icon {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      opacity: 0.4;
      font-size: 1rem;
      pointer-events: none;
    }
    .stats-search-inner input {
      width: 100%;
      box-sizing: border-box;
      padding: 10px 14px 10px 36px;
      border-radius: 10px;
      border: 1px solid var(--card-border, rgba(255,255,255,0.12));
      background: var(--card-bg, rgba(255,255,255,0.04));
      color: inherit;
      font-size: 0.95rem;
      outline: none;
    }
    .stats-search-inner input::placeholder { opacity: 0.35; }
    .stats-clear-btn {
      padding: 9px 16px;
      border-radius: 10px;
      border: 1px solid var(--card-border, rgba(255,255,255,0.12));
      background: transparent;
      color: inherit;
      font-size: 0.85rem;
      cursor: pointer;
      opacity: 0.6;
      transition: opacity 0.15s;
    }
    .stats-clear-btn:hover { opacity: 1; }

    /* ── Results area ────────────────────────────────────────────── */
    .stats-results { }
    .result-count {
      font-size: 0.8rem;
      opacity: 0.45;
      margin-bottom: 14px;
      letter-spacing: 0.03em;
    }

    /* ── Performer / Venue cards ─────────────────────────────────── */
    .stats-card {
      background: var(--card-bg, rgba(255,255,255,0.04));
      border: 1px solid var(--card-border, rgba(255,255,255,0.1));
      border-radius: 14px;
      margin-bottom: 14px;
      overflow: hidden;
    }
    .stats-card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 14px 18px;
      cursor: pointer;
      gap: 12px;
      user-select: none;
    }
    .stats-card-header:hover { background: rgba(255,255,255,0.03); }
    .stats-card-title {
      font-weight: 700;
      font-size: 1rem;
      flex: 1;
    }
    .stats-card-sub {
      font-size: 0.78rem;
      opacity: 0.45;
      margin-top: 2px;
    }
    .stats-card-badges {
      display: flex;
      gap: 8px;
      align-items: center;
      flex-shrink: 0;
    }
    .badge-count {
      background: var(--accent-bg, rgba(255,255,255,0.1));
      border-radius: 20px;
      padding: 3px 11px;
      font-size: 0.8rem;
      font-weight: 700;
    }
    .badge-hl {
      background: rgba(255, 193, 7, 0.18);
      color: #ffc107;
      border-radius: 20px;
      padding: 3px 10px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    .chevron {
      opacity: 0.35;
      font-size: 0.8rem;
      transition: transform 0.2s;
      flex-shrink: 0;
    }
    .stats-card.open .chevron { transform: rotate(180deg); }

    /* ── Expandable detail ───────────────────────────────────────── */
    .stats-card-detail {
      display: none;
      border-top: 1px solid var(--card-border, rgba(255,255,255,0.08));
      padding: 14px 18px 16px;
    }
    .stats-card.open .stats-card-detail { display: block; }

    /* ── Appearance table ────────────────────────────────────────── */
    .appearance-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.85rem;
    }
    .appearance-table th {
      text-align: left;
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      opacity: 0.4;
      padding: 0 8px 8px 0;
      font-weight: 600;
      border-bottom: 1px solid var(--card-border, rgba(255,255,255,0.08));
    }
    .appearance-table td {
      padding: 8px 8px 8px 0;
      vertical-align: top;
      border-bottom: 1px solid var(--card-border, rgba(255,255,255,0.05));
    }
    .appearance-table tr:last-child td { border-bottom: none; }
    .td-date { opacity: 0.55; white-space: nowrap; }
    .td-event { font-weight: 600; }
    .td-venue { opacity: 0.7; }
    .td-badge { white-space: nowrap; }
    .dot-hl { color: #ffc107; font-size: 0.7rem; margin-left: 4px; }
    .dot-watched {
      display: inline-block;
      width: 8px; height: 8px;
      border-radius: 50%;
      background: #4caf50;
      margin-right: 4px;
      vertical-align: middle;
    }
    .dot-nowatch {
      display: inline-block;
      width: 8px; height: 8px;
      border-radius: 50%;
      background: rgba(255,255,255,0.2);
      margin-right: 4px;
      vertical-align: middle;
    }

    /* ── Performer list inside venue detail ──────────────────────── */
    .ev-performers {
      font-size: 0.78rem;
      opacity: 0.55;
    }

    /* ── Year view ───────────────────────────────────────────────── */
    .year-block {
      background: var(--card-bg, rgba(255,255,255,0.04));
      border: 1px solid var(--card-border, rgba(255,255,255,0.1));
      border-radius: 14px;
      margin-bottom: 14px;
      overflow: hidden;
    }
    .year-block-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 14px 18px;
      cursor: pointer;
      user-select: none;
    }
    .year-block-header:hover { background: rgba(255,255,255,0.03); }
    .year-block-title { font-weight: 700; font-size: 1.1rem; }
    .year-block-detail {
      display: none;
      border-top: 1px solid var(--card-border, rgba(255,255,255,0.08));
      padding: 12px 18px 14px;
    }
    .year-block.open .year-block-detail { display: block; }
    .year-block.open .chevron { transform: rotate(180deg); }
    .year-event-item {
      padding: 5px 0;
      font-size: 0.88rem;
      border-bottom: 1px solid var(--card-border, rgba(255,255,255,0.05));
      opacity: 0.8;
    }
    .year-event-item:last-child { border-bottom: none; }

    .summary-tile-list {
      text-align: left;
    }
    .tile-list-row {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 5px 0;
      border-bottom: 1px solid var(--card-border, rgba(255,255,255,0.06));
      font-size: 0.85rem;
    }
    .tile-list-row:last-child { border-bottom: none; }
    .tile-list-rank {
      font-size: 0.7rem;
      font-weight: 700;
      opacity: 0.35;
      width: 14px;
      flex-shrink: 0;
      text-align: right;
    }
    .tile-list-name {
      flex: 1;
      font-weight: 600;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .tile-list-count {
      font-size: 0.78rem;
      font-weight: 700;
      opacity: 0.55;
      flex-shrink: 0;
    }
    .no-stats-results {
      text-align: center;
      opacity: 0.35;
      padding: 40px 0;
      font-size: 0.95rem;
    }

    /* ── Section hidden ──────────────────────────────────────────── */
    .stats-section { display: none; }
    .stats-section.active { display: block; }
  </style>
</head>
<body>

<?php
  $currentPage = 'stats';
  $pageTitle   = 'Stats';
  require 'nav.php';
?>

<div class="stats-wrap">

  <!-- Summary Tiles -->
  <?php
    $top5Performers = array_slice(array_values($performerStats), 0, 5);
    $top5Venues     = array_slice(array_values($venueStats), 0, 5);
  ?>
  <div class="summary-grid">
    <div class="summary-tile summary-tile-list">
      <div class="tile-label" style="margin-bottom:10px;">Overview</div>
      <div class="tile-list-row">
        <span class="tile-list-name" style="font-weight:400;">Total Events</span>
        <span class="tile-list-count" style="opacity:1; font-size:0.9rem;"><?= $totalEvents ?></span>
      </div>
      <div class="tile-list-row">
        <span class="tile-list-name" style="font-weight:400;">Unique Performers</span>
        <span class="tile-list-count" style="opacity:1; font-size:0.9rem;"><?= $totalPerformers ?></span>
      </div>
      <div class="tile-list-row">
        <span class="tile-list-name" style="font-weight:400;">Unique Venues</span>
        <span class="tile-list-count" style="opacity:1; font-size:0.9rem;"><?= $totalVenues ?></span>
      </div>
    </div>
    <div class="summary-tile summary-tile-list">
      <div class="tile-label" style="margin-bottom:10px;">Top Performers</div>
      <?php foreach ($top5Performers as $i => $tp): ?>
        <div class="tile-list-row">
          <span class="tile-list-rank"><?= $i + 1 ?></span>
          <span class="tile-list-name"><?= htmlspecialchars($tp['name']) ?></span>
          <span class="tile-list-count"><?= $tp['total'] ?>×</span>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="summary-tile summary-tile-list">
      <div class="tile-label" style="margin-bottom:10px;">Top Venues</div>
      <?php foreach ($top5Venues as $i => $tv): ?>
        <div class="tile-list-row">
          <span class="tile-list-rank"><?= $i + 1 ?></span>
          <span class="tile-list-name"><?= htmlspecialchars($tv['name']) ?></span>
          <span class="tile-list-count"><?= $tv['total'] ?>×</span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Mode Tabs -->
  <div class="stats-tabs">
    <button class="stats-tab active" data-mode="performer">By Performer</button>
    <button class="stats-tab" data-mode="venue">By Venue</button>
    <button class="stats-tab" data-mode="year">By Year</button>
  </div>

  <!-- Search -->
  <div class="stats-search-wrap">
    <div class="stats-search-inner">
      <span class="search-icon">&#9906;</span>
      <input type="text" id="statsSearch" placeholder="Search…" autocomplete="off">
    </div>
    <button class="stats-clear-btn" id="statsClear">Clear</button>
  </div>

  <div class="result-count" id="resultCount"></div>

  <!-- ── Performer Section ───────────────────────────────────────────── -->
  <div class="stats-section active" id="section-performer">
    <?php foreach ($performerStats as $p): ?>
    <div class="stats-card"
         data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>">
      <div class="stats-card-header" onclick="toggleCard(this)">
        <div>
          <div class="stats-card-title"><?= htmlspecialchars($p['name']) ?></div>
          <div class="stats-card-sub">
            <?php
              $venueList = array_unique(array_column($p['appearances'], 'venue_Name'));
              $yearList  = array_unique(array_filter(array_column($p['appearances'], 'event_Year')));
              sort($yearList);
            ?>
            <?= count($venueList) ?> venue<?= count($venueList) !== 1 ? 's' : '' ?> ·
            <?= implode(', ', $yearList) ?>
          </div>
        </div>
        <div class="stats-card-badges">
          <span class="badge-count"><?= $p['total'] ?>×</span>
          <?php if ($p['headliner_count'] > 0): ?>
            <span class="badge-hl">★ <?= $p['headliner_count'] ?>× headliner</span>
          <?php endif; ?>
        </div>
        <span class="chevron">▼</span>
      </div>
      <div class="stats-card-detail">
        <table class="appearance-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Event / Tour</th>
              <th>Venue</th>
              <th>Role</th>
            </tr>
          </thead>
          <tbody>
            <?php
              // Sort appearances by date ascending
              usort($p['appearances'], fn($a, $b) => strcmp($a['start_date'] ?? '', $b['start_date'] ?? ''));
              foreach ($p['appearances'] as $a):
                $dateStr = $a['start_date'] ? date('M d, Y', strtotime($a['start_date'])) : '—';
                $venue   = array_filter([$a['venue_Name'], $a['venue_City'], $a['venue_State']]);
            ?>
            <tr>
              <td class="td-date"><?= htmlspecialchars($dateStr) ?></td>
              <td class="td-event"><?= htmlspecialchars($a['event_Name']) ?></td>
              <td class="td-venue"><?= htmlspecialchars(implode(', ', $venue)) ?></td>
              <td class="td-badge">
                <?php if ($a['is_Headliner']): ?>
                  <span class="badge-hl">★ Headliner</span>
                <?php else: ?>
                  <span style="opacity:0.4; font-size:0.78rem;">Support</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endforeach; ?>
    <div class="no-stats-results" id="noPerformer" style="display:none;">No performers match your search.</div>
  </div>

  <!-- ── Venue Section ───────────────────────────────────────────────── -->
  <div class="stats-section" id="section-venue">
    <?php foreach ($venueStats as $v): ?>
    <div class="stats-card"
         data-name="<?= htmlspecialchars(strtolower($v['name'] . ' ' . ($v['city'] ?? '') . ' ' . ($v['state'] ?? ''))) ?>">
      <div class="stats-card-header" onclick="toggleCard(this)">
        <div>
          <div class="stats-card-title"><?= htmlspecialchars($v['name']) ?></div>
          <div class="stats-card-sub">
            <?= htmlspecialchars(implode(', ', array_filter([$v['city'], $v['state']]))) ?>
          </div>
        </div>
        <div class="stats-card-badges">
          <span class="badge-count"><?= $v['total'] ?> event<?= $v['total'] !== 1 ? 's' : '' ?></span>
        </div>
        <span class="chevron">▼</span>
      </div>
      <div class="stats-card-detail">
        <table class="appearance-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Event / Tour</th>
              <th>Performers</th>
            </tr>
          </thead>
          <tbody>
            <?php
              usort($v['appearances'], fn($a, $b) => strcmp($a['start_date'] ?? '', $b['start_date'] ?? ''));
              foreach ($v['appearances'] as $a):
                $dateStr    = $a['start_date'] ? date('M d, Y', strtotime($a['start_date'])) : '—';
                $endStr     = $a['end_date'] && $a['end_date'] !== $a['start_date']
                              ? ' – ' . date('M d, Y', strtotime($a['end_date'])) : '';
                $headliners = array_values(array_filter($a['performers'], fn($p) => $p['is_Headliner']));
                $support    = array_values(array_filter($a['performers'], fn($p) => !$p['is_Headliner']));
            ?>
            <tr>
              <td class="td-date" style="white-space:nowrap;"><?= htmlspecialchars($dateStr . $endStr) ?></td>
              <td class="td-event"><?= htmlspecialchars($a['event_Name']) ?></td>
              <td>
                <?php if (!empty($headliners)): ?>
                  <div style="margin-bottom:2px;">
                    <?php foreach ($headliners as $p): ?>
                      <span style="font-weight:600;"><?= htmlspecialchars($p['name']) ?></span><span class="dot-hl" title="Headliner">★</span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($support)): ?>
                  <div class="ev-performers"><?= htmlspecialchars(implode(', ', array_column($support, 'name'))) ?></div>
                <?php endif; ?>
                <?php if (empty($a['performers'])): ?>
                  <span style="opacity:0.35;font-size:0.78rem;">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endforeach; ?>
    <div class="no-stats-results" id="noVenue" style="display:none;">No venues match your search.</div>
  </div>

  <!-- ── Year Section ────────────────────────────────────────────────── -->
  <div class="stats-section" id="section-year">
    <?php foreach ($yearStats as $yr => $y): ?>
    <div class="year-block" data-name="<?= htmlspecialchars((string)$yr) ?>">
      <div class="year-block-header" onclick="toggleCard(this)">
        <div class="year-block-title"><?= htmlspecialchars((string)$yr) ?></div>
        <div class="stats-card-badges">
          <span class="badge-count"><?= $y['total'] ?> event<?= $y['total'] !== 1 ? 's' : '' ?></span>
        </div>
        <span class="chevron">▼</span>
      </div>
      <div class="year-block-detail">
        <table class="appearance-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Event / Tour</th>
              <th>Venue</th>
              <th>Performers</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($y['events'] as $ev):
              $dateStr = $ev['start_date'] ? date('M d, Y', strtotime($ev['start_date'])) : '—';
              $endStr  = $ev['end_date'] && $ev['end_date'] !== $ev['start_date']
                         ? ' – ' . date('M d, Y', strtotime($ev['end_date'])) : '';
              $venue   = implode(', ', array_filter([$ev['venue_Name'], $ev['venue_City'], $ev['venue_State']]));
              $headliners = array_values(array_filter($ev['performers'], fn($p) => $p['is_Headliner']));
              $support    = array_values(array_filter($ev['performers'], fn($p) => !$p['is_Headliner']));
            ?>
            <tr>
              <td class="td-date" style="white-space:nowrap;"><?= htmlspecialchars($dateStr . $endStr) ?></td>
              <td class="td-event"><?= htmlspecialchars($ev['event_Name']) ?></td>
              <td class="td-venue"><?= htmlspecialchars($venue ?: '—') ?></td>
              <td>
                <?php if (!empty($headliners)): ?>
                  <div style="margin-bottom:2px;">
                    <?php foreach ($headliners as $p): ?>
                      <span style="font-weight:600;"><?= htmlspecialchars($p['name']) ?></span><span class="dot-hl" title="Headliner">★</span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($support)): ?>
                  <div class="ev-performers"><?= htmlspecialchars(implode(', ', array_column($support, 'name'))) ?></div>
                <?php endif; ?>
                <?php if (empty($ev['performers'])): ?>
                  <span style="opacity:0.35;font-size:0.78rem;">—</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endforeach; ?>
    <div class="no-stats-results" id="noYear" style="display:none;">No years match your search.</div>
  </div>

</div><!-- /stats-wrap -->

<script>
let activeMode = 'performer';

// ── Toggle expand/collapse ─────────────────────────────────────────
function toggleCard(headerEl) {
  headerEl.closest('.stats-card, .year-block').classList.toggle('open');
}

// ── Mode tabs ──────────────────────────────────────────────────────
document.querySelectorAll('.stats-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.stats-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    activeMode = tab.dataset.mode;
    document.querySelectorAll('.stats-section').forEach(s => s.classList.remove('active'));
    document.getElementById('section-' + activeMode).classList.add('active');
    document.getElementById('statsSearch').value = '';
    runSearch();
  });
});

// ── Search ─────────────────────────────────────────────────────────
const searchInput = document.getElementById('statsSearch');
const clearBtn    = document.getElementById('statsClear');
const resultCount = document.getElementById('resultCount');

searchInput.addEventListener('input', runSearch);
clearBtn.addEventListener('click', () => {
  searchInput.value = '';
  runSearch();
});

function runSearch() {
  const q = searchInput.value.trim().toLowerCase();
  const section = document.getElementById('section-' + activeMode);
  const cards   = section.querySelectorAll('.stats-card, .year-block');
  const noEl    = section.querySelector('.no-stats-results');

  let visible = 0;
  cards.forEach(card => {
    const name  = card.dataset.name || '';
    const match = !q || name.includes(q);
    card.style.display = match ? '' : 'none';
    if (match) visible++;
  });

  if (noEl) noEl.style.display = visible === 0 ? 'block' : 'none';

  const label = activeMode === 'performer' ? 'performer' :
                activeMode === 'venue'     ? 'venue'     : 'year';
  resultCount.textContent = q
    ? `${visible} ${label}${visible !== 1 ? 's' : ''} matching "${q}"`
    : `${visible} ${label}${visible !== 1 ? 's' : ''}`;
}

// Initial count
runSearch();
</script>

</body>
</html>