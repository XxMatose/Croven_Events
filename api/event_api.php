<?php
// ─── event_api.php — Unified Event Insert / Update API ───────────────
require_once 'db/db_hosted.php';
require_once 'api/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$body = file_get_contents('php://input');
$req  = json_decode($body, true);

if (!$req) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$action = $req['action'] ?? ''; // 'insert' or 'update'

// ── Shared helpers ────────────────────────────────────────────────────

/**
 * Find an existing venue by name (case-insensitive) or create a new one.
 * Returns venue_ID.
 */
function findOrCreateVenue(PDO $pdo, string $name, string $address, string $city, string $state, string $type): int {
    $stmt = $pdo->prepare("SELECT venue_ID FROM venue WHERE LOWER(venue_Name) = LOWER(?) LIMIT 1");
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    if ($row) {
        return (int)$row['venue_ID'];
    }
    $ins = $pdo->prepare("INSERT INTO venue (venue_Name, venue_Address, venue_City, venue_State, venue_Type) VALUES (?, ?, ?, ?, ?)");
    $ins->execute([$name, $address ?: null, $city, $state, $type ?: null]);
    return (int)$pdo->lastInsertId();
}

/**
 * Find an existing performer by name (case-insensitive) or create a new one.
 * Returns performer_ID.
 */
function findOrCreatePerformer(PDO $pdo, string $name): int {
    $stmt = $pdo->prepare("SELECT performer_ID FROM performer WHERE LOWER(performer_Name) = LOWER(?) LIMIT 1");
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    if ($row) {
        return (int)$row['performer_ID'];
    }
    $ins = $pdo->prepare("INSERT INTO performer (performer_Name) VALUES (?)");
    $ins->execute([$name]);
    return (int)$pdo->lastInsertId();
}

// ══════════════════════════════════════════════════════════════════════
// INSERT (new event)
// ══════════════════════════════════════════════════════════════════════
if ($action === 'insert') {
    $eventName    = trim($req['event_name']    ?? '');
    $startDate    = trim($req['start_date']    ?? '');
    $endDate      = trim($req['end_date']      ?? '') ?: $startDate;
    $venueName    = trim($req['venue_name']    ?? '');
    $venueAddress = trim($req['venue_address'] ?? '');
    $venueCity    = trim($req['venue_city']    ?? '');
    $venueState   = trim($req['venue_state']   ?? '');
    $venueType    = trim($req['venue_type']    ?? '');
    $performers   = $req['performers']         ?? [];

    if (!$eventName || !$startDate || !$venueName || !$venueCity || !$venueState) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Please fill in all required fields.']);
        exit;
    }

    if (empty($performers)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Please add at least one performer.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Use the existing stored procedure for each performer (it handles find-or-create internally)
        $addedCount = 0;
        foreach ($performers as $p) {
            $pName = trim($p['name'] ?? '');
            if ($pName === '') continue;

            $stmt = $pdo->prepare("CALL sp_AddEventWithPerformer(
                :vName, :vAddr, :vCity, :vState, :vType,
                :eName, :eStart, :eEnd,
                :pName, :pOrder, :isHead, :isOpener, :watched
            )");
            $stmt->execute([
                ':vName'    => $venueName,
                ':vAddr'    => $venueAddress,
                ':vCity'    => $venueCity,
                ':vState'   => $venueState,
                ':vType'    => $venueType,
                ':eName'    => $eventName,
                ':eStart'   => $startDate,
                ':eEnd'     => $endDate,
                ':pName'    => $pName,
                ':pOrder'   => (int)($p['order'] ?? ($addedCount + 1)),
                ':isHead'   => (int)($p['is_headliner'] ?? 0),
                ':isOpener' => (int)($p['is_opener']    ?? 0),
                ':watched'  => (int)($p['watched']       ?? 0),
            ]);
            $stmt->closeCursor();
            $addedCount++;
        }

        if ($addedCount === 0) {
            $pdo->rollBack();
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Please add at least one performer.']);
            exit;
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'added' => $addedCount]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ══════════════════════════════════════════════════════════════════════
// UPDATE (edit existing event)
// ══════════════════════════════════════════════════════════════════════
if ($action === 'update') {
    $eventId      = isset($req['event_id']) ? (int)$req['event_id'] : 0;
    $eventName    = trim($req['event_name']    ?? '');
    $startDate    = trim($req['start_date']    ?? '');
    $endDate      = trim($req['end_date']      ?? '') ?: $startDate;
    $venueName    = trim($req['venue_name']    ?? '');
    $venueAddress = trim($req['venue_address'] ?? '');
    $venueCity    = trim($req['venue_city']    ?? '');
    $venueState   = trim($req['venue_state']   ?? '');
    $venueType    = trim($req['venue_type']    ?? '');
    $performers   = $req['performers']         ?? [];   // array of performer objects
    $removedIds   = $req['removed_ep_ids']     ?? [];   // event_performer IDs to delete

    if ($eventId <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Invalid event ID.']);
        exit;
    }
    if (!$eventName || !$startDate || !$venueName || !$venueCity || !$venueState) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Please fill in all required fields.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // ── 1. Resolve venue ──────────────────────────────────────────
        $venueId = findOrCreateVenue($pdo, $venueName, $venueAddress, $venueCity, $venueState, $venueType);

        // ── 2. Update event record ────────────────────────────────────
        $year = date('Y', strtotime($startDate));
        $evStmt = $pdo->prepare("
            UPDATE event
            SET event_Name      = ?,
                event_StartDate = ?,
                event_EndDate   = ?,
                event_Year      = ?,
                venue_ID        = ?
            WHERE event_ID = ?
        ");
        $evStmt->execute([$eventName, $startDate, $endDate, $year, $venueId, $eventId]);

        // ── 3. Remove deleted performers ──────────────────────────────
        foreach ($removedIds as $epId) {
            $epId = (int)$epId;
            if ($epId <= 0) continue;
            $pdo->prepare("DELETE FROM event_performers WHERE id = ?")->execute([$epId]);
        }

        // ── 4. Upsert remaining / new performers ──────────────────────
        foreach ($performers as $p) {
            $pName = trim($p['name'] ?? '');
            if ($pName === '') continue;

            $performerId = findOrCreatePerformer($pdo, $pName);
            $order       = (int)($p['order']        ?? 1);
            $isHead      = (int)($p['is_headliner'] ?? 0);
            $isOpener    = (int)($p['is_opener']    ?? 0);
            $watched     = (int)($p['watched']       ?? 0);
            $epId        = isset($p['ep_id']) ? (int)$p['ep_id'] : 0;

            if ($epId > 0) {
                // Existing event_performers row — update it
                $pdo->prepare("
                    UPDATE event_performers
                    SET performer_ID     = ?,
                        order_performed  = ?,
                        is_Headliner     = ?,
                        is_Opener        = ?,
                        watched          = ?
                    WHERE id = ? AND event_ID = ?
                ")->execute([$performerId, $order, $isHead, $isOpener, $watched, $epId, $eventId]);
            } else {
                // New performer row — insert
                $pdo->prepare("
                    INSERT INTO event_performers (event_ID, performer_ID, order_performed, is_Headliner, is_Opener, watched)
                    VALUES (?, ?, ?, ?, ?, ?)
                ")->execute([$eventId, $performerId, $order, $isHead, $isOpener, $watched]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ─── Unknown action ───────────────────────────────────────────────────
http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Unknown action']);
