<?php
// ─── admin_api.php — Admin CRUD API ──────────────────────────────────
require_once 'db_hosted.php';
require_once 'auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// ─── Parse body ───────────────────────────────────────────────────────
$body = file_get_contents('php://input');
$req  = json_decode($body, true);

if (!$req) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$action = $req['action'] ?? '';

// ─── Whitelist tables — only allow real base tables in current DB ─────
function getValidTables(PDO $pdo): array {
    $stmt = $pdo->query("
        SELECT TABLE_NAME
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_TYPE = 'BASE TABLE'
    ");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function validateTable(PDO $pdo, string $table): void {
    if (!in_array($table, getValidTables($pdo), true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Invalid table']);
        exit;
    }
}

// ─── Validate column names against actual schema ──────────────────────
function getTableColumns(PDO $pdo, string $table): array {
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// ─── Quote identifier safely ──────────────────────────────────────────
function qi(string $name): string {
    return '`' . str_replace('`', '', $name) . '`';
}

// ══════════════════════════════════════════════════════════════════════
// UPDATE
// ══════════════════════════════════════════════════════════════════════
if ($action === 'update') {
    $table  = $req['table']  ?? '';
    $pk     = $req['pk']     ?? '';
    $pkVal  = $req['pk_val'] ?? null;
    $data   = $req['data']   ?? [];

    if ($table === '' || $pk === '' || $pkVal === null || !is_array($data) || empty($data)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }

    validateTable($pdo, $table);
    $validCols = getTableColumns($pdo, $table);

    // Filter data to only valid columns; exclude PK
    $sets   = [];
    $params = [];
    foreach ($data as $col => $val) {
        if (!in_array($col, $validCols, true) || $col === $pk) continue;
        $sets[]   = qi($col) . ' = ?';
        $params[] = $val === '' ? null : $val;
    }

    if (empty($sets)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'No valid columns to update']);
        exit;
    }

    $params[] = $pkVal; // for WHERE clause

    try {
        $sql  = "UPDATE " . qi($table) . " SET " . implode(', ', $sets) . " WHERE " . qi($pk) . " = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            // Could be no actual change — still report success
            echo json_encode(['success' => true, 'note' => 'No rows changed']);
        } else {
            echo json_encode(['success' => true]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════════════════
// DELETE
// ══════════════════════════════════════════════════════════════════════
if ($action === 'delete') {
    $table = $req['table']  ?? '';
    $pk    = $req['pk']     ?? '';
    $pkVal = $req['pk_val'] ?? null;

    if ($table === '' || $pk === '' || $pkVal === null) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }

    validateTable($pdo, $table);

    // Confirm PK is actually a column in this table
    $validCols = getTableColumns($pdo, $table);
    if (!in_array($pk, $validCols, true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Invalid primary key column']);
        exit;
    }

    try {
        $sql  = "DELETE FROM " . qi($table) . " WHERE " . qi($pk) . " = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$pkVal]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Record not found']);
            exit;
        }

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════════════════
// INSERT
// ══════════════════════════════════════════════════════════════════════
if ($action === 'insert') {
    $table = $req['table'] ?? '';
    $data  = $req['data']  ?? [];

    if ($table === '' || !is_array($data) || empty($data)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }

    validateTable($pdo, $table);
    $validCols = getTableColumns($pdo, $table);

    $cols   = [];
    $params = [];
    foreach ($data as $col => $val) {
        if (!in_array($col, $validCols, true)) continue;
        $cols[]   = qi($col);
        $params[] = $val === '' ? null : $val;
    }

    if (empty($cols)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'No valid columns provided']);
        exit;
    }

    $placeholders = implode(', ', array_fill(0, count($cols), '?'));

    try {
        $sql  = "INSERT INTO " . qi($table) . " (" . implode(', ', $cols) . ") VALUES ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $newId = $pdo->lastInsertId();

        echo json_encode(['success' => true, 'id' => (int)$newId]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ─── Unknown action ───────────────────────────────────────────────────
http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Unknown action']);
