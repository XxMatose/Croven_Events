<?php
// ─── onedrive_ajax.php ───────────────────────────────────────────────
// Handles two actions via POST:
//   action=list_media  → returns JSON array of photos/videos for an event
//   action=upload      → uploads a file to the event's OneDrive folder
//
// Include this at a URL your JS can reach, e.g. /api/onedrive_ajax.php

session_start();

define('DEV_MODE', true); // TODO: remove before production

require_once dirname(__DIR__) . '/db/db_hosted.php';   // was missing /db/
require_once dirname(__DIR__) . '/onedrive_helper.php'; // was __DIR__ (wrong folder)

header('Content-Type: application/json');

// ── Auth gate ─────────────────────────────────────────────────────────
// TODO: remove DEV_MODE bypass before production
if (!defined('DEV_MODE') && empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// ── Route ──────────────────────────────────────────────────────────────
$action   = $_POST['action'] ?? $_GET['action'] ?? '';
$eventId  = (int) ($_POST['event_id'] ?? $_GET['event_id'] ?? 0);

if (!$eventId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing event_id']);
    exit;
}

// ── Fetch folder ID from DB ────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT memory_path FROM event WHERE event_id = ?');
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event || empty($event['memory_path'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Event not found or no OneDrive folder linked']);
    exit;
}

$folderId = $event['memory_path'];
$od       = new OneDriveHelper();

// ── Actions ────────────────────────────────────────────────────────────

if ($action === 'list_media') {
    try {
        $media = $od->listMedia($folderId);
        echo json_encode(['success' => true, 'media' => $media]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'upload') {
    if (empty($_FILES['file'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No file received']);
        exit;
    }

    $file     = $_FILES['file'];
    $allowed  = ['image/jpeg','image/png','image/gif','image/webp','video/mp4','video/quicktime','video/x-msvideo','video/mpeg'];

    if (!in_array($file['type'], $allowed, true)) {
        http_response_code(415);
        echo json_encode(['error' => 'File type not allowed']);
        exit;
    }

    // 500 MB hard cap (adjust to your PHP upload_max_filesize)
    if ($file['size'] > 500 * 1024 * 1024) {
        http_response_code(413);
        echo json_encode(['error' => 'File too large (max 500 MB)']);
        exit;
    }

    try {
        $result = $od->uploadFile($file['tmp_name'], $file['name'], $folderId);
        echo json_encode(['success' => true, 'item' => $result]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Unknown action']);