<?php
require_once __DIR__ . '/../db/db_hosted.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// ─── Parse JSON body ──────────────────────────────────────────────────
$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// ─── Shared validation helpers ────────────────────────────────────────
function validateFields(array $data): ?string {
    $label  = trim($data['label']   ?? '');
    $path   = trim($data['path']    ?? '');
    $userId = isset($data['user_id']) ? (int)$data['user_id'] : 0;

    if ($label === '' || $path === '' || $userId <= 0) {
        return 'All fields are required';
    }
    if (strlen($label) > 100) {
        return 'Label must be 100 characters or fewer';
    }
    if (strlen($path) > 250) {
        return 'URL must be 250 characters or fewer';
    }
    return null;
}

// ─── CREATE (POST without id) ─────────────────────────────────────────
if ($method === 'POST' && empty($data['id'])) {
    $error = validateFields($data);
    if ($error) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => $error]);
        exit;
    }

    $label  = trim($data['label']);
    $path   = trim($data['path']);
    $userId = (int)$data['user_id'];

    // Verify user exists
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $checkStmt->execute([$userId]);
    if (!$checkStmt->fetch()) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Invalid user selected']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO favorites (label, path, user_id) VALUES (?, ?, ?)");
        $stmt->execute([$label, $path, $userId]);
        $newId = $pdo->lastInsertId();

        echo json_encode(['success' => true, 'id' => (int)$newId]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit;
}

// ─── UPDATE (POST with id) ────────────────────────────────────────────
if ($method === 'POST' && !empty($data['id'])) {
    $id = (int)$data['id'];
    if ($id <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Invalid record ID']);
        exit;
    }

    $error = validateFields($data);
    if ($error) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => $error]);
        exit;
    }

    $label  = trim($data['label']);
    $path   = trim($data['path']);
    $userId = (int)$data['user_id'];

    // Verify user exists and grab name for UI update
    $checkStmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
    $checkStmt->execute([$userId]);
    $user = $checkStmt->fetch();
    if (!$user) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Invalid user selected']);
        exit;
    }

    // Verify record exists
    $existStmt = $pdo->prepare("SELECT id FROM favorites WHERE id = ?");
    $existStmt->execute([$id]);
    if (!$existStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Record not found']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE favorites SET label = ?, path = ?, user_id = ? WHERE id = ?");
        $stmt->execute([$label, $path, $userId, $id]);

        echo json_encode([
            'success'   => true,
            'id'        => $id,
            'user_name' => $user['name'],
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit;
}

// ─── DELETE ───────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    if ($id <= 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Invalid record ID']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Record not found']);
            exit;
        }

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit;
}

// ─── Method not allowed ───────────────────────────────────────────────
http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
