<?php
// ─── event_api.php — Unified Event Insert / Update API ───────────────
require_once __DIR__ . '/../db/db_hosted.php';
require_once __DIR__ . '/auth.php';

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

/**
 * Replace all watched-user rows for a given event_performers record.
 * Deletes existing rows then inserts one row per user ID provided.
 */
function syncWatchedUsers(PDO $pdo, int $epId, array $userIds): void {
    $pdo->prepare("DELETE FROM event_performers_watched WHERE event_performer_ID = ?")->execute([$epId]);
    if (empty($userIds)) return;
    $ins = $pdo->prepare("INSERT INTO event_performers_watched (event_performer_ID, user_ID) VALUES (?, ?)");
    foreach ($userIds as $uid) {
        $uid = (int)$uid;
        if ($uid > 0) {
            $ins->execute([$epId, $uid]);
        }
    }
}

/**
 * Fetch watched user IDs for a given event_performers record.
 * Returns array of user IDs.
 */
function getWatchedUserIds(PDO $pdo, int $epId): array {
    $stmt = $pdo->prepare("SELECT user_ID FROM event_performers_watched WHERE event_performer_ID = ?");
    $stmt->execute([$epId]);
    return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'user_ID');
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
        $addedCount = 0;
        foreach ($performers as $p) {
            $pName       = trim($p['name'] ?? '');
            if ($pName === '') continue;
            $watchedIds  = array_map('intval', $p['watched_user_ids'] ?? []);

            // Call stored procedure (manages its own transaction internally)
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
                ':isOpener' => (int)($p['is_main_opener'] ?? 0),
                ':watched'  => 0, // legacy column — user-specific watched handled below
            ]);
            $stmt->closeCursor();

            // Resolve the event_performers record just created so we can sync watched users.
            // We look up by event name + start date + performer name to get the record_ID.
            $epLookup = $pdo->prepare("
                SELECT ep.record_ID
                FROM event_performers ep
                JOIN event e ON ep.event_ID = e.event_ID
                JOIN performer pf ON ep.performer_ID = pf.performer_ID
                WHERE e.event_Name = ?
                  AND e.event_StartDate = ?
                  AND LOWER(pf.performer_Name) = LOWER(?)
                ORDER BY ep.record_ID DESC
                LIMIT 1
            ");
            $epLookup->execute([$eventName, $startDate, $pName]);
            $epRow = $epLookup->fetch();

            if ($epRow && !empty($watchedIds)) {
                syncWatchedUsers($pdo, (int)$epRow['record_ID'], $watchedIds);
            }

            $addedCount++;
        }

        if ($addedCount === 0) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'Please add at least one performer.']);
            exit;
        }

        echo json_encode(['success' => true, 'added' => $addedCount]);

    } catch (PDOException $e) {
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

        // ── 3. Remove deleted performers (also clean up watched rows) ─
        foreach ($removedIds as $epId) {
            $epId = (int)$epId;
            if ($epId <= 0) continue;
            // watched rows cascade if FK + ON DELETE CASCADE is set; otherwise delete explicitly
            $pdo->prepare("DELETE FROM event_performers_watched WHERE event_performer_ID = ?")->execute([$epId]);
            $pdo->prepare("DELETE FROM event_performers WHERE record_ID = ?")->execute([$epId]);
        }

        // ── 4. Upsert remaining / new performers ──────────────────────
        foreach ($performers as $p) {
            $pName      = trim($p['name'] ?? '');
            if ($pName === '') continue;

            $performerId = findOrCreatePerformer($pdo, $pName);
            $order       = (int)($p['order']           ?? 1);
            $isHead      = (int)($p['is_headliner']    ?? 0);
            $isOpener    = (int)($p['is_main_opener']  ?? 0);
            $epId        = isset($p['ep_id']) ? (int)$p['ep_id'] : 0;
            $watchedIds  = array_map('intval', $p['watched_user_ids'] ?? []);

            if ($epId > 0) {
                // Existing event_performers row — update it
                $pdo->prepare("
                    UPDATE event_performers
                    SET performer_ID     = ?,
                        order_performed  = ?,
                        is_Headliner     = ?,
                        is_main_opener   = ?
                    WHERE record_ID = ? AND event_ID = ?
                ")->execute([$performerId, $order, $isHead, $isOpener, $epId, $eventId]);
            } else {
                // New performer row — insert
                $pdo->prepare("
                    INSERT INTO event_performers (event_ID, performer_ID, order_performed, is_Headliner, is_main_opener)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([$eventId, $performerId, $order, $isHead, $isOpener]);
                $epId = (int)$pdo->lastInsertId();
            }

            // Sync watched users for this performer
            syncWatchedUsers($pdo, $epId, $watchedIds);
        }

        $pdo->commit();

        // ── 5. Return fresh performer list with watched_user_ids ───────
        $epStmt = $pdo->prepare("
            SELECT ep.record_ID AS ep_id,
                   p.performer_Name AS name,
                   ep.order_performed,
                   ep.is_Headliner,
                   ep.is_main_opener
            FROM event_performers ep
            JOIN performer p ON ep.performer_ID = p.performer_ID
            WHERE ep.event_ID = ?
            ORDER BY ep.order_performed
        ");
        $epStmt->execute([$eventId]);
        $freshPerformers = $epStmt->fetchAll(PDO::FETCH_ASSOC);

        // Attach watched_user_ids to each performer
        foreach ($freshPerformers as &$fp) {
            $fp['watched_user_ids'] = getWatchedUserIds($pdo, (int)$fp['ep_id']);
        }
        unset($fp);

        echo json_encode(['success' => true, 'performers' => $freshPerformers]);

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
