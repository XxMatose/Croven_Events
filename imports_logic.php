<?php
// ─── imports_logic.php ────────────────────────────────────────────────────────
// PHP logic for the Imports tab: AJAX handlers + helpers.
// Required at the TOP of festivals.php, before any HTML output.
// $pdo is assumed to already be available (included by festivals.php).
// ─────────────────────────────────────────────────────────────────────────────

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
// Helper: convert the letter-keyed JSON into the numeric-index format
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
    $last_mapped_col = max(array_keys($col_map));
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

    // Find which col index maps to which field (case-insensitive key lookup)
    $field_col = [];
    foreach ($col_map as $col_idx => $mapping) {
        $field_col[strtolower($mapping['field'])] = $col_idx;
    }

    $get_col = fn($field) => $field_col[strtolower($field)] ?? null;

    $valid_days   = $logic['valid_days'];
    $valid_stages = $logic['valid_stages'];
    $valid_prefs  = ['n','w'];

    foreach ($raw_rows as $row) {
        $row_errors = [];

        $day       = trim($row[$get_col('day')]        ?? '');
        $start     = trim($row[$get_col('start_Time')] ?? '');
        $end       = trim($row[$get_col('end_Time')]   ?? '');
        $performer = trim($row[$get_col('performer')]  ?? '');
        $stage     = trim($row[$get_col('stage')]      ?? '');

        if ($day === '')       $row_errors[] = "Row {$row_num}: Day is empty.";
        if ($start === '')     $row_errors[] = "Row {$row_num}: Start time is empty.";
        if ($end === '')       $row_errors[] = "Row {$row_num}: End time is empty.";
        if ($performer === '') $row_errors[] = "Row {$row_num}: Performer/Band is empty.";
        if ($stage === '')     $row_errors[] = "Row {$row_num}: Stage is empty.";

        if ($day !== '' && !in_array(strtolower($day), $valid_days)) {
            $row_errors[] = "Row {$row_num}: Invalid day value '{$day}'.";
        }

        if ($stage !== '' && !in_array(strtolower($stage), $valid_stages)) {
            $row_errors[] = "Row {$row_num}: Invalid stage value '{$stage}'.";
        }

        $prefs = [];
        foreach ($viewer_col_indexes as $v_idx => $viewer_name) {
            $pref_val = strtoupper(trim($row[$v_idx] ?? ''));
            if ($pref_val === '') continue;

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
