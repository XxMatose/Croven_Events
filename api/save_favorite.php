<?php
require_once 'db/db_hosted.php';
require_once 'api/auth.php';

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Parse JSON body
$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$label   = trim($data['label']   ?? '');
$path    = trim($data['path']    ?? '');
$userId  = isset($data['user_id']) ? (int)$data['user_id'] : 0;

// Validate
if ($label === '' || $path === '' || $userId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'All fields are required']);
    exit;
}

// Enforce field lengths to match schema
if (strlen($label) > 100) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Label must be 100 characters or fewer']);
    exit;
}
if (strlen($path) > 250) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'URL must be 250 characters or fewer']);
    exit;
}

// Verify user exists
$checkStmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
$checkStmt->execute([$userId]);
if (!$checkStmt->fetch()) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Invalid user selected']);
    exit;
}

// Insert into favorites
try {
    $stmt = $pdo->prepare("INSERT INTO favorites (label, path, user_id) VALUES (?, ?, ?)");
    $stmt->execute([$label, $path, $userId]);
    $newId = $pdo->lastInsertId();

    echo json_encode(['success' => true, 'id' => (int)$newId]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
