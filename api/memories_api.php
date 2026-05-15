<?php
/**
 * api/memories_api.php
 * Handles GET (list) and POST (update) for the memories table.
 *
 * GET  ?event_id=123   → returns all rows from vw_full_memories for that event
 * POST { action:'update', id, title, direction, story, memory_owner }
 *                      → updates the memories table, returns { success, memory }
 */

require_once '../db/db_hosted.php';
require_once '../api/auth.php';

header('Content-Type: application/json');

// ── GET ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

    if (!$eventId) {
        echo json_encode(['error' => 'Missing event_id']);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT id, event_ID, title, direction, trade_day, story, image_path, memory_owner,
                event_Name, event_Year, event_StartDate, event_EndDate
         FROM vw_full_memories
         WHERE event_ID = ?
         ORDER BY id ASC"
    );
    $stmt->execute([$eventId]);
    $memories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['memories' => $memories]);
    exit;
}

// ── POST ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);

    $action = $body['action'] ?? '';

    if (!$body || !in_array($action, ['update', 'create'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }

    $title       = trim($body['title']         ?? '');
    $direction   = trim($body['direction']     ?? '');
    $tradeDay    = trim($body['trade_day']     ?? '') ?: null;
    $story       = trim($body['story']         ?? '');
    $memoryOwner = trim($body['memory_owner']  ?? '');
    $imagePath   = trim($body['image_path']    ?? '') ?: null;

    // ── CREATE ─────────────────────────────────────────────────────────────
    if ($action === 'create') {
        $eventId = (int)($body['event_id'] ?? 0);

        if (!$eventId) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing event_id']);
            exit;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO memories (event_ID, title, direction, trade_day, story, memory_owner, image_path)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$eventId, $title, $direction, $tradeDay, $story, $memoryOwner, $imagePath]);

        $newId = (int)$pdo->lastInsertId();

        $row = $pdo->prepare(
            "SELECT id, event_ID, title, direction, trade_day, story, image_path, memory_owner,
                    event_Name, event_Year, event_StartDate, event_EndDate
             FROM vw_full_memories WHERE id = ?"
        );
        $row->execute([$newId]);
        $memory = $row->fetch(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'memory' => $memory]);
        exit;
    }

    // ── UPDATE ─────────────────────────────────────────────────────────────
    $id = (int)($body['id'] ?? 0);

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing id']);
        exit;
    }

    $stmt = $pdo->prepare(
        "UPDATE memories
         SET title        = ?,
             direction    = ?,
             trade_day    = ?,
             story        = ?,
             memory_owner = ?,
             image_path   = ?
         WHERE id = ?"
    );
    $stmt->execute([$title, $direction, $tradeDay, $story, $memoryOwner, $imagePath, $id]);

    // Return the updated row from the view so the JS cache stays in sync
    $row = $pdo->prepare(
        "SELECT id, event_ID, title, direction, trade_day, story, image_path, memory_owner,
                event_Name, event_Year, event_StartDate, event_EndDate
         FROM vw_full_memories WHERE id = ?"
    );
    $row->execute([$id]);
    $memory = $row->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'memory' => $memory]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);