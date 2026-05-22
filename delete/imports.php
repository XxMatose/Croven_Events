<?php
require_once 'db/db_hosted.php';
require_once 'api/auth.php';

$festivals = [];
$error_message = '';
$success_message = '';

// Load festival list from view
try {
    $stmt = $pdo->query("SELECT festival_ID, event_Name, event_Year FROM vw_festival_list ORDER BY event_Name, event_Year");
    $festivals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = "Failed to load festival list: " . $e->getMessage();
}

// ─────────────────────────────────────────────
// AJAX: Preview endpoint
// ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'preview') {
    header('Content-Type: application/json');

    $festival_id   = $_POST['festival_id']   ?? '';
    $festival_name = $_POST['festival_name'] ?? '';

    if (!$festival_id || !$festival_name) {
        echo json_encode(['success' => false, 'errors' => ['No festival selected.']]);
        exit;
    }

    // Find the logic
    $stmt = $pdo->prepare("SELECT * FROM import_logic WHERE festival_id = ?");
    $stmt->execute([$festival_id]);
    $logic_row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$logic_row) {
        echo json_encode(['success' => false, 'errors' => ["No import logic configured for this festival."]]);
        exit;
    }

    $logic = [
        'column_map'   => build_column_map(json_decode($logic_row['column_map'], true)),
        'valid_days'   => json_decode($logic_row['valid_days'], true),
        'valid_stages' => json_decode($logic_row['valid_stages'], true),
    ];
    if (!$logic) {
        echo json_encode(['success' => false, 'errors' => ['Could not parse logic file.']]);
        exit;
    }

    // Handle uploaded file
    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'errors' => ['No file uploaded or upload error.']]);
        exit;
    }

    $tmp = $_FILES['import_file']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['import_file']['name'], PATHINFO_EXTENSION));
    if ($ext !== 'xlsx') {
        echo json_encode(['success' => false, 'errors' => ['Only .xlsx files are accepted.']]);
        exit;
    }

    // Parse spreadsheet
    $result = parse_spreadsheet($tmp, $logic);

    echo json_encode($result);
    exit;
}

// ─────────────────────────────────────────────
// AJAX: Check existing data endpoint
// ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_existing') {
    header('Content-Type: application/json');

    $festival_id = $_POST['festival_id'] ?? '';
    if (!$festival_id) {
        echo json_encode(['success' => false, 'exists' => false]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM festival_transactions WHERE festival_ID = ?");
    $stmt->execute([$festival_id]);
    $count = (int)$stmt->fetchColumn();

    echo json_encode(['success' => true, 'exists' => $count > 0, 'count' => $count]);
    exit;
}

// ─────────────────────────────────────────────
// AJAX: Import (commit) endpoint
// ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import') {
    header('Content-Type: application/json');

    $festival_id   = $_POST['festival_id']   ?? '';
    $festival_name = $_POST['festival_name'] ?? '';
    $rows_json     = $_POST['rows']          ?? '';

    if (!$festival_id || !$festival_name || !$rows_json) {
        echo json_encode(['success' => false, 'message' => 'Missing required data.']);
        exit;
    }

    $rows = json_decode($rows_json, true);
    if (!$rows) {
        echo json_encode(['success' => false, 'message' => 'Could not decode row data.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Delete existing data for this festival
        $pdo->prepare("DELETE FROM festival_preferences WHERE festival_ID = ?")->execute([$festival_id]);
        $pdo->prepare("DELETE FROM festival_transactions WHERE festival_ID = ?")->execute([$festival_id]);

        $trans_stmt = $pdo->prepare("
            INSERT INTO festival_transactions (festival_ID, day, stage, performer, start_Time, end_Time)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $pref_stmt = $pdo->prepare("
            INSERT INTO festival_preferences (festival_ID, trans_ID, viewer, want, need)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($rows as $row) {
            $trans_stmt->execute([
                $festival_id,
                $row['day'],
                $row['stage'],
                $row['performer'],
                $row['start_Time'],
                $row['end_Time']
            ]);
            $trans_id = $pdo->lastInsertId();

            foreach ($row['preferences'] as $pref) {
                $pref_stmt->execute([
                    $festival_id,
                    $trans_id,
                    $pref['viewer'],
                    $pref['want'],
                    $pref['need']
                ]);
            }
        }

        $pdo->commit();

        $pref_count = array_sum(array_map(fn($r) => count($r['preferences']), $rows));
        echo json_encode([
            'success'      => true,
            'message'      => 'Import complete.',
            'trans_count'  => count($rows),
            'pref_count'   => $pref_count
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}


// ─────────────────────────────────────────────
// Helper: convert the letter-keyed JSON into the numberic-index format
// ─────────────────────────────────────────────
function build_column_map(array $letter_map): array {
    $col_map = [];
    foreach ($letter_map as $letter => $mapping) {
        $idx = ord(strtoupper($letter)) - ord('A');
        $col_map[$idx] = $mapping;
    }
    return $col_map;
}
// ─────────────────────────────────────────────
// Helper: Parse the uploaded spreadsheet
// ─────────────────────────────────────────────
function parse_spreadsheet($tmp_path, $logic) {
    // Use ZipArchive to read xlsx (sharedStrings + sheet1)
    $zip = new ZipArchive();
    if ($zip->open($tmp_path) !== true) {
        return ['success' => false, 'errors' => ['Could not open uploaded file.']];
    }

    // Read shared strings
    $shared_strings = [];
    $ss_xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ss_xml) {
        $ss_dom = new SimpleXMLElement($ss_xml);
        foreach ($ss_dom->si as $si) {
            $text = '';
            if (isset($si->t)) {
                $text = (string)$si->t;
            } elseif (isset($si->r)) {
                foreach ($si->r as $r) {
                    $text .= (string)$r->t;
                }
            }
            $shared_strings[] = $text;
        }
    }

    // Read sheet1
    $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    if (!$sheet_xml) {
        return ['success' => false, 'errors' => ['Could not read worksheet data.']];
    }

    $sheet_dom = new SimpleXMLElement($sheet_xml);
    $ns = $sheet_dom->getNamespaces(true);
    $default_ns = isset($ns['']) ? $ns[''] : '';

    // Parse rows
    $raw_rows = [];
    foreach ($sheet_dom->sheetData->row as $row) {
        $row_data = [];
        foreach ($row->c as $cell) {
            $col_ref = preg_replace('/[0-9]/', '', (string)$cell['r']);
            $col_idx = col_letter_to_index($col_ref);
            $type    = (string)$cell['t'];
            $val     = isset($cell->v) ? (string)$cell->v : '';

            if ($type === 's') {
                $val = $shared_strings[(int)$val] ?? '';
            }
            $row_data[$col_idx] = $val;
        }
        $raw_rows[] = $row_data;
    }

    if (empty($raw_rows)) {
        return ['success' => false, 'errors' => ['Spreadsheet appears to be empty.']];
    }

    // First row = headers
    $header_row = array_shift($raw_rows);
    $col_map    = $logic['column_map'];

    // Viewer columns = every column after E (index 4) with a non-empty header
    // No txt configuration needed — fully dynamic from spreadsheet
    $last_mapped_col = max(array_keys($col_map)); // should be 4 (Col E)
    $viewer_col_indexes = [];
    foreach ($header_row as $idx => $header) {
        $header = trim($header);
        if ($idx > $last_mapped_col && $header !== '') {
            $viewer_col_indexes[$idx] = $header;
        }
    }

    $errors    = [];
    $rows_out  = [];
    $row_num   = 2; // Excel row number (1 = header)

    $required_fields = [
        'day'        => null,
        'stage'      => null,
        'performer'  => null,
        'start_Time' => null,
        'end_Time'   => null,
    ];

    // Find which col index maps to which field (case-insensitive key lookup)
    $field_col = [];
    foreach ($col_map as $col_idx => $mapping) {
        $field_col[strtolower($mapping['field'])] = $col_idx;
    }

    // Extract transaction fields using case-insensitive keys
    $get_col = fn($field) => $field_col[strtolower($field)] ?? null;

    $valid_days   = $logic['valid_days'];
    $valid_stages = $logic['valid_stages'];
    $valid_prefs  = ['n','w'];

    foreach ($raw_rows as $row) {
        $row_errors = [];

        // Extract transaction fields
        $day       = trim($row[$get_col('day')]        ?? '');
        $start     = trim($row[$get_col('start_Time')] ?? '');
        $end       = trim($row[$get_col('end_Time')]   ?? '');
        $performer = trim($row[$get_col('performer')]  ?? '');
        $stage     = trim($row[$get_col('stage')]      ?? '');

        // Validate required fields
        if ($day === '')       $row_errors[] = "Row {$row_num}: Day is empty.";
        if ($start === '')     $row_errors[] = "Row {$row_num}: Start time is empty.";
        if ($end === '')       $row_errors[] = "Row {$row_num}: End time is empty.";
        if ($performer === '') $row_errors[] = "Row {$row_num}: Performer/Band is empty.";
        if ($stage === '')     $row_errors[] = "Row {$row_num}: Stage is empty.";

        // Validate day enum
        if ($day !== '' && !in_array(strtolower($day), $valid_days)) {
            $row_errors[] = "Row {$row_num}: Invalid day value '{$day}'.";
        }

        // Validate stage enum
        if ($stage !== '' && !in_array(strtolower($stage), $valid_stages)) {
            $row_errors[] = "Row {$row_num}: Invalid stage value '{$stage}'.";
        }

        // Validate preference values
        $prefs = [];
        foreach ($viewer_col_indexes as $v_idx => $viewer_name) {
            $pref_val = strtoupper(trim($row[$v_idx] ?? ''));
            if ($pref_val === '') continue; // blank = no preference, skip

            if (!in_array(strtolower($pref_val), $valid_prefs)) {
                $row_errors[] = "Row {$row_num}: Invalid preference value '{$pref_val}' for viewer '{$viewer_name}' (must be N or W).";
            } else {
                $prefs[] = [
                    'viewer' => $viewer_name,
                    'want'   => ($pref_val === 'W') ? 1 : 0,
                    'need'   => ($pref_val === 'N') ? 1 : 0,
                ];
            }
        }

        if (!empty($row_errors)) {
            $errors = array_merge($errors, $row_errors);
        }

        $rows_out[] = [
            'row_num'     => $row_num,
            'day'         => $day,
            'start_Time'  => $start,
            'end_Time'    => $end,
            'performer'   => $performer,
            'stage'       => $stage,
            'preferences' => $prefs,
            'has_error'   => !empty($row_errors),
            'row_errors'  => $row_errors,
        ];

        $row_num++;
    }

    $pref_count = array_sum(array_map(fn($r) => count($r['preferences']), $rows_out));

    return [
        'success'      => empty($errors),
        'errors'       => $errors,
        'rows'         => $rows_out,
        'trans_count'  => count($rows_out),
        'pref_count'   => $pref_count,
        'viewer_names' => array_values($viewer_col_indexes),
    ];
}

// ─────────────────────────────────────────────
// Helper: Convert column letters to index (A=0, B=1, AA=26, etc.)
// ─────────────────────────────────────────────
function col_letter_to_index($col) {
    $col   = strtoupper($col);
    $index = 0;
    $len   = strlen($col);
    for ($i = 0; $i < $len; $i++) {
        $index = $index * 26 + (ord($col[$i]) - ord('A') + 1);
    }
    return $index - 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Festival Import</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg:         #0d0f14;
            --surface:    #161920;
            --surface-2:  #1e2130;
            --border:     #2a2f45;
            --accent:     #f4a01c;
            --accent-dim: #7a5010;
            --danger:     #e8475f;
            --success:    #2ecc8a;
            --text:       #e8eaf2;
            --text-dim:   #7880a0;
            --mono:       'DM Mono', monospace;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            font-size: 15px;
            min-height: 100vh;
        }

        /* ── Header ── */
        .page-header {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0 40px;
            display: flex;
            align-items: center;
            gap: 20px;
            height: 64px;
        }
        .page-header h1 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 28px;
            letter-spacing: 3px;
            color: var(--accent);
        }
        .page-header span {
            color: var(--text-dim);
            font-size: 13px;
            font-family: var(--mono);
        }

        /* ── Layout ── */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 36px 40px;
        }

        /* ── Card ── */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 28px 32px;
            margin-bottom: 24px;
        }
        .card-title {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 18px;
            letter-spacing: 2px;
            color: var(--text-dim);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* ── Form Controls ── */
        label {
            display: block;
            font-size: 12px;
            font-family: var(--mono);
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        select {
            width: 100%;
            max-width: 420px;
            background: var(--surface-2);
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 10px 14px;
            font-family: 'DM Sans', sans-serif;
            font-size: 15px;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%237880a0' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            cursor: pointer;
            transition: border-color .2s;
        }
        select:focus { outline: none; border-color: var(--accent); }

        /* ── Drop zone ── */
        .drop-zone {
            border: 2px dashed var(--border);
            border-radius: 8px;
            padding: 48px 32px;
            text-align: center;
            cursor: pointer;
            transition: border-color .2s, background .2s;
            position: relative;
        }
        .drop-zone:hover,
        .drop-zone.dragover {
            border-color: var(--accent);
            background: rgba(244,160,28,.04);
        }
        .drop-zone.has-file {
            border-color: var(--success);
            background: rgba(46,204,138,.04);
        }
        .drop-zone input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }
        .drop-icon {
            font-size: 36px;
            margin-bottom: 12px;
            display: block;
        }
        .drop-zone p {
            color: var(--text-dim);
            font-size: 14px;
        }
        .drop-zone .file-name {
            color: var(--success);
            font-family: var(--mono);
            font-size: 14px;
            font-weight: 500;
            margin-top: 8px;
        }

        /* ── Buttons ── */
        .btn-row {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        .btn {
            padding: 11px 28px;
            border-radius: 6px;
            border: none;
            font-family: 'Bebas Neue', sans-serif;
            font-size: 17px;
            letter-spacing: 2px;
            cursor: pointer;
            transition: opacity .2s, transform .1s;
        }
        .btn:active { transform: scale(.97); }
        .btn:disabled { opacity: .35; cursor: not-allowed; transform: none; }

        .btn-preview {
            background: var(--surface-2);
            color: var(--accent);
            border: 1px solid var(--accent-dim);
        }
        .btn-preview:hover:not(:disabled) { background: rgba(244,160,28,.1); }

        .btn-import {
            background: var(--accent);
            color: #0d0f14;
        }
        .btn-import:hover:not(:disabled) { opacity: .88; }

        .btn-reset {
            background: transparent;
            color: var(--text-dim);
            border: 1px solid var(--border);
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            letter-spacing: 0;
        }
        .btn-reset:hover { color: var(--text); border-color: var(--text-dim); }

        /* ── Alerts ── */
        .alert {
            border-radius: 6px;
            padding: 14px 18px;
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .alert-danger  { background: rgba(232,71,95,.12);  border: 1px solid rgba(232,71,95,.3);  color: #f08090; }
        .alert-success { background: rgba(46,204,138,.12); border: 1px solid rgba(46,204,138,.3); color: var(--success); }
        .alert-info    { background: rgba(244,160,28,.10); border: 1px solid rgba(244,160,28,.25); color: var(--accent); }

        /* ── Summary bar ── */
        .summary-bar {
            display: flex;
            gap: 32px;
            padding: 16px 24px;
            background: var(--surface-2);
            border: 1px solid var(--border);
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .summary-stat { text-align: center; }
        .summary-stat .num {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 32px;
            color: var(--accent);
            line-height: 1;
        }
        .summary-stat .lbl {
            font-size: 11px;
            font-family: var(--mono);
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 4px;
        }
        .summary-stat.danger .num { color: var(--danger); }
        .summary-stat.ok .num    { color: var(--success); }

        /* ── Error list ── */
        .error-list {
            background: rgba(232,71,95,.07);
            border: 1px solid rgba(232,71,95,.2);
            border-radius: 6px;
            padding: 16px 20px;
            margin-bottom: 20px;
        }
        .error-list h4 {
            color: var(--danger);
            font-size: 13px;
            font-family: var(--mono);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        .error-list ul { list-style: none; }
        .error-list li {
            font-size: 13px;
            color: #f08090;
            padding: 3px 0;
            border-bottom: 1px solid rgba(232,71,95,.1);
        }
        .error-list li:last-child { border-bottom: none; }
        .error-list li::before { content: '✕  '; }

        /* ── Preview table ── */
        .table-wrap {
            overflow-x: auto;
            border-radius: 6px;
            border: 1px solid var(--border);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        thead tr {
            background: var(--surface-2);
        }
        thead th {
            padding: 10px 14px;
            text-align: left;
            font-family: var(--mono);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-dim);
            white-space: nowrap;
            border-bottom: 1px solid var(--border);
        }
        tbody tr {
            border-bottom: 1px solid rgba(42,47,69,.6);
            transition: background .15s;
        }
        tbody tr:hover { background: var(--surface-2); }
        tbody tr.row-error { background: rgba(232,71,95,.08); }
        tbody tr.row-error:hover { background: rgba(232,71,95,.13); }
        tbody td {
            padding: 9px 14px;
            color: var(--text);
            white-space: nowrap;
        }
        tbody td.mono { font-family: var(--mono); color: var(--text-dim); }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-family: var(--mono);
            font-weight: 500;
        }
        .badge-want { background: rgba(244,160,28,.2); color: var(--accent); }
        .badge-need { background: rgba(46,204,138,.2); color: var(--success); }
        .badge-err  { background: rgba(232,71,95,.2);  color: var(--danger);  }

        .stage-pill {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-family: var(--mono);
            background: var(--surface-2);
            border: 1px solid var(--border);
            color: var(--text-dim);
        }

        /* ── Spinner ── */
        .spinner {
            display: inline-block;
            width: 16px; height: 16px;
            border: 2px solid rgba(244,160,28,.3);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin .7s linear infinite;
            vertical-align: middle;
            margin-right: 8px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── Hidden ── */
        .hidden { display: none !important; }

        /* ── Confirm Modal ── */
        #confirm-modal {
            position: fixed;
            inset: 0;
            background: rgba(13,15,20,.80);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            backdrop-filter: blur(3px);
        }
        .modal-box {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 36px 40px;
            max-width: 480px;
            width: 90%;
        }
        .modal-box h3 {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 22px;
            letter-spacing: 2px;
            color: var(--danger);
            margin-bottom: 14px;
        }
        .modal-box p {
            font-size: 14px;
            color: var(--text-dim);
            line-height: 1.6;
            margin-bottom: 8px;
        }
        .modal-box p strong {
            color: var(--text);
        }
        .modal-divider {
            border: none;
            border-top: 1px solid var(--border);
            margin: 24px 0;
        }
        .modal-btn-row {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        /* ── Loader overlay ── */
        #loader {
            position: fixed;
            inset: 0;
            background: rgba(13,15,20,.75);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 999;
            backdrop-filter: blur(2px);
        }
        .loader-box {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 32px 48px;
            text-align: center;
        }
        .loader-box .big-spin {
            width: 40px; height: 40px;
            border: 3px solid rgba(244,160,28,.2);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin .8s linear infinite;
            margin: 0 auto 16px;
        }
        .loader-box p {
            font-family: var(--mono);
            font-size: 13px;
            color: var(--text-dim);
        }
    </style>
</head>
<body>

<div id="loader" class="hidden">
    <div class="loader-box">
        <div class="big-spin"></div>
        <p id="loader-msg">Processing...</p>
    </div>
</div>

<!-- ── Existing data confirmation modal ── -->
<div id="confirm-modal" class="hidden">
    <div class="modal-box">
        <h3>⚠ Existing Data Detected</h3>
        <p>There are already <strong id="modal-count"></strong> set times recorded for <strong id="modal-festival-name"></strong>.</p>
        <p>Proceeding will <strong style="color:var(--danger);">permanently delete</strong> all existing set times and viewer preferences for this festival before importing the new data.</p>
        <hr class="modal-divider">
        <div class="modal-btn-row">
            <button class="btn btn-reset" id="modal-cancel">Cancel</button>
            <button class="btn btn-import" id="modal-proceed">Proceed with Import</button>
        </div>
    </div>
</div>

<header class="page-header">
    <h1>Festival Import</h1>
    <span>/ import / logic</span>
</header>

<div class="container">

    <?php if ($error_message): ?>
        <div class="alert alert-danger">⚠ <?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <!-- ── Step 1: Select festival & file ── -->
    <div class="card" id="step-upload">
        <div class="card-title">01 — Configure Import</div>

        <div style="margin-bottom:24px">
            <label for="festival-select">Festival</label>
            <select id="festival-select">
                <option value="">— Select a festival —</option>
                <?php foreach ($festivals as $f): ?>
                    <option
                        value="<?= htmlspecialchars($f['festival_ID']) ?>"
                        data-name="<?= htmlspecialchars($f['event_Name']) ?>"
                        data-year="<?= htmlspecialchars($f['event_Year']) ?>"
                    >
                        <?= htmlspecialchars($f['event_Name']) ?> <?= htmlspecialchars($f['event_Year']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label>Import File (.xlsx)</label>
            <div class="drop-zone" id="drop-zone">
                <input type="file" id="file-input" accept=".xlsx">
                <span class="drop-icon">📂</span>
                <p>Drag & drop your <strong>.xlsx</strong> file here, or click to browse</p>
                <div class="file-name hidden" id="file-name-display"></div>
            </div>
        </div>

        <div class="btn-row">
            <button class="btn btn-preview" id="btn-preview" disabled>Preview Import</button>
            <button class="btn btn-reset" id="btn-reset">Reset</button>
        </div>
    </div>

    <!-- ── Step 2: Preview results ── -->
    <div id="preview-section" class="hidden">

        <!-- Summary bar -->
        <div class="summary-bar" id="summary-bar"></div>

        <!-- Error list -->
        <div id="error-block" class="error-list hidden">
            <h4>Errors Detected — Fix before importing</h4>
            <ul id="error-list"></ul>
        </div>

        <!-- Success alert + import button -->
        <div id="clean-block" class="hidden">
            <div class="alert alert-success">
                ✓ &nbsp;No errors detected. Ready to import.
            </div>
            <div class="btn-row" style="margin-top:0; margin-bottom:20px;">
                <button class="btn btn-import" id="btn-import">Import to Database</button>
            </div>
        </div>

        <!-- Import result message -->
        <div id="import-result" class="hidden"></div>

        <!-- Preview table -->
        <div class="card" style="padding: 0; overflow:hidden;">
            <div style="padding: 20px 24px 16px; border-bottom: 1px solid var(--border);">
                <div class="card-title" style="margin-bottom:0;">02 — Preview Data</div>
            </div>
            <div class="table-wrap" style="border:none; border-radius:0;">
                <table id="preview-table">
                    <thead id="preview-thead"></thead>
                    <tbody id="preview-tbody"></tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script>
const festivalSelect = document.getElementById('festival-select');
const dropZone       = document.getElementById('drop-zone');
const fileInput      = document.getElementById('file-input');
const fileNameDisp   = document.getElementById('file-name-display');
const btnPreview     = document.getElementById('btn-preview');
const btnReset       = document.getElementById('btn-reset');
const btnImport      = document.getElementById('btn-import');
const previewSection = document.getElementById('preview-section');
const loader         = document.getElementById('loader');
const loaderMsg      = document.getElementById('loader-msg');

let selectedFile     = null;
let previewRows      = null;
let previewViewers   = [];
let importing        = false;

// ── File selection ──
fileInput.addEventListener('change', () => {
    if (fileInput.files[0]) setFile(fileInput.files[0]);
});

dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
dropZone.addEventListener('dragleave', ()  => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    const f = e.dataTransfer.files[0];
    if (f && f.name.endsWith('.xlsx')) {
        setFile(f);
    } else {
        showAlert('Only .xlsx files are accepted.', 'danger');
    }
});

function setFile(f) {
    selectedFile = f;
    dropZone.classList.add('has-file');
    fileNameDisp.textContent = '📄 ' + f.name;
    fileNameDisp.classList.remove('hidden');
    checkReady();
}

festivalSelect.addEventListener('change', checkReady);

function checkReady() {
    btnPreview.disabled = !(festivalSelect.value && selectedFile);
}

// ── Reset ──
btnReset.addEventListener('click', () => {
    festivalSelect.value = '';
    selectedFile = null;
    fileInput.value = '';
    fileNameDisp.classList.add('hidden');
    fileNameDisp.textContent = '';
    dropZone.classList.remove('has-file', 'dragover');
    previewSection.classList.add('hidden');
    previewRows = null;
    previewViewers = [];
    importing = false;
    btnPreview.disabled = true;
    document.getElementById('import-result').classList.add('hidden');
});

// ── Preview ──
btnPreview.addEventListener('click', async () => {
    const festivalId   = festivalSelect.value;
    const festivalName = festivalSelect.options[festivalSelect.selectedIndex].dataset.name;

    const fd = new FormData();
    fd.append('action', 'preview');
    fd.append('festival_id', festivalId);
    fd.append('festival_name', festivalName);
    fd.append('import_file', selectedFile);

    showLoader('Parsing spreadsheet...');
    try {
        const res  = await fetch(window.location.href, { method: 'POST', body: fd });
        const data = await res.json();
        hideLoader();
        renderPreview(data);
    } catch(e) {
        hideLoader();
        showAlert('Unexpected error during preview: ' + e.message, 'danger');
    }
});

// ── Import ──
btnImport.addEventListener('click', async () => {
    if (!previewRows) return;

    const festivalId   = festivalSelect.value;
    const festivalName = festivalSelect.options[festivalSelect.selectedIndex].dataset.name;

    // Check if existing data exists for this festival before importing
    showLoader('Checking for existing data...');
    try {
        const checkFd = new FormData();
        checkFd.append('action', 'check_existing');
        checkFd.append('festival_id', festivalId);

        const checkRes  = await fetch(window.location.href, { method: 'POST', body: checkFd });
        const checkData = await checkRes.json();
        hideLoader();

        if (checkData.exists) {
            // Show confirmation modal
            document.getElementById('modal-count').textContent = checkData.count + ' set time' + (checkData.count !== 1 ? 's' : '');
            document.getElementById('modal-festival-name').textContent = festivalName;
            document.getElementById('confirm-modal').classList.remove('hidden');
        } else {
            // No existing data — import directly
            await runImport(festivalId, festivalName);
        }
    } catch(e) {
        hideLoader();
        showAlert('Unexpected error during check: ' + e.message, 'danger');
    }
});

// ── Modal buttons ──
document.getElementById('modal-cancel').addEventListener('click', () => {
    document.getElementById('confirm-modal').classList.add('hidden');
});

document.getElementById('modal-proceed').addEventListener('click', async () => {
    document.getElementById('confirm-modal').classList.add('hidden');
    const festivalId   = festivalSelect.value;
    const festivalName = festivalSelect.options[festivalSelect.selectedIndex].dataset.name;
    await runImport(festivalId, festivalName);
});

// ── Shared import logic ──
async function runImport(festivalId, festivalName) {
    if (importing) return;
    importing = true;
    btnImport.disabled = true;
    const fd = new FormData();
    fd.append('action', 'import');
    fd.append('festival_id', festivalId);
    fd.append('festival_name', festivalName);
    fd.append('rows', JSON.stringify(previewRows));

    showLoader('Importing to database...');
    try {
        const res  = await fetch(window.location.href, { method: 'POST', body: fd });
        const data = await res.json();
        hideLoader();

        const resultDiv = document.getElementById('import-result');
        resultDiv.classList.remove('hidden');

        if (data.success) {
            resultDiv.innerHTML = `
                <div class="alert alert-success">
                    ✓ &nbsp;<strong>Import complete!</strong>
                    &nbsp;${data.trans_count} set times imported,
                    ${data.pref_count} viewer preferences recorded.
                </div>`;
            btnImport.disabled = true;
            document.getElementById('clean-block').classList.add('hidden');
        } else {
            importing = false;
            btnImport.disabled = false;
            resultDiv.innerHTML = `<div class="alert alert-danger">✕ &nbsp;${escHtml(data.message)}</div>`;
        }
    } catch(e) {
        importing = false;
        btnImport.disabled = false;
        hideLoader();
        showAlert('Unexpected error during import: ' + e.message, 'danger');
    }
}

// ── Render preview ──
function renderPreview(data) {
    previewSection.classList.remove('hidden');
    document.getElementById('import-result').classList.add('hidden');

    previewRows    = data.rows    || [];
    previewViewers = data.viewer_names || [];

    const errorCount = (data.errors || []).length;
    const errorRows  = previewRows.filter(r => r.has_error).length;
    const prefCount  = previewRows.reduce((s, r) => s + r.preferences.length, 0);

    // Summary bar
    document.getElementById('summary-bar').innerHTML = `
        <div class="summary-stat"><div class="num">${previewRows.length}</div><div class="lbl">Set Times</div></div>
        <div class="summary-stat"><div class="num">${prefCount}</div><div class="lbl">Viewer Prefs</div></div>
        <div class="summary-stat"><div class="num">${previewViewers.length}</div><div class="lbl">Viewers</div></div>
        <div class="summary-stat ${errorRows > 0 ? 'danger' : 'ok'}">
            <div class="num">${errorRows}</div><div class="lbl">Errors</div>
        </div>`;

    // Errors
    const errorBlock = document.getElementById('error-block');
    const errorList  = document.getElementById('error-list');
    const cleanBlock = document.getElementById('clean-block');

    if (errorCount > 0) {
        errorBlock.classList.remove('hidden');
        cleanBlock.classList.add('hidden');
        errorList.innerHTML = (data.errors || []).map(e =>
            `<li>${escHtml(e)}</li>`).join('');
        btnImport.disabled = true;
    } else {
        errorBlock.classList.add('hidden');
        cleanBlock.classList.remove('hidden');
        btnImport.disabled = false;
    }

    // Build table
    const thead = document.getElementById('preview-thead');
    const tbody = document.getElementById('preview-tbody');

    const viewerCols = previewViewers.map(v =>
        `<th>${escHtml(v)}</th>`).join('');

    thead.innerHTML = `<tr>
        <th>#</th>
        <th>Day</th>
        <th>Start</th>
        <th>End</th>
        <th>Performer</th>
        <th>Stage</th>
        ${viewerCols}
        <th>Status</th>
    </tr>`;

    tbody.innerHTML = previewRows.map(row => {
        const viewerCells = previewViewers.map(v => {
            const pref = row.preferences.find(p => p.viewer === v);
            if (!pref) return '<td>—</td>';
            const badge = pref.want
                ? `<span class="badge badge-want">W</span>`
                : `<span class="badge badge-need">N</span>`;
            return `<td>${badge}</td>`;
        }).join('');

        const status = row.has_error
            ? `<span class="badge badge-err">Error</span>`
            : `<span style="color:var(--success);font-size:13px;">✓</span>`;

        return `<tr class="${row.has_error ? 'row-error' : ''}">
            <td class="mono">${row.row_num}</td>
            <td>${escHtml(row.day)}</td>
            <td class="mono">${escHtml(row.start_Time)}</td>
            <td class="mono">${escHtml(row.end_Time)}</td>
            <td>${escHtml(row.performer)}</td>
            <td><span class="stage-pill">${escHtml(row.stage)}</span></td>
            ${viewerCells}
            <td>${status}</td>
        </tr>`;
    }).join('');
}

// ── Utilities ──
function showLoader(msg) {
    loaderMsg.textContent = msg || 'Processing...';
    loader.classList.remove('hidden');
}
function hideLoader() {
    loader.classList.add('hidden');
}
function showAlert(msg, type) {
    const div = document.createElement('div');
    div.className = `alert alert-${type}`;
    div.textContent = msg;
    document.querySelector('.container').prepend(div);
    setTimeout(() => div.remove(), 6000);
}
function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>