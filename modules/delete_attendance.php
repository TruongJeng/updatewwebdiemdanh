<?php
// delete_attendance.php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

// Kiểm tra admin - tuỳ theo app bạn có thể kiểm tra role
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'error'=>'Unauthenticated']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$attendance_id = isset($input['attendance_id']) ? (int)$input['attendance_id'] : 0;
$event_id = isset($input['event_id']) ? (int)$input['event_id'] : 0;
$csrf = $input['csrf'] ?? '';

if (empty($_SESSION['csrf_token']) || $csrf !== $_SESSION['csrf_token']) {
    echo json_encode(['success'=>false,'error'=>'Invalid CSRF']);
    exit();
}
if (!$attendance_id || !$event_id) {
    echo json_encode(['success'=>false,'error'=>'Missing params']);
    exit();
}

// Kiểm tra attendance tồn tại và thuộc event
$stmt = $pdo->prepare("SELECT id FROM attendance WHERE id=? AND event_id=?");
$stmt->execute([$attendance_id, $event_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success'=>false,'error'=>'Not found']);
    exit();
}

// Xóa
try {
    $stmt = $pdo->prepare("DELETE FROM attendance WHERE id=?");
    $stmt->execute([$attendance_id]);
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}