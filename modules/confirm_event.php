<?php
// confirm_event.php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'error'=>'Unauthenticated']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$event_id = isset($input['event_id']) ? (int)$input['event_id'] : 0;
$csrf = $input['csrf'] ?? '';

if (empty($_SESSION['csrf_token']) || $csrf !== $_SESSION['csrf_token']) {
    echo json_encode(['success'=>false,'error'=>'Invalid CSRF']);
    exit();
}
if (!$event_id) {
    echo json_encode(['success'=>false,'error'=>'Missing event_id']);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE events SET is_closed=1 WHERE id=?");
    $stmt->execute([$event_id]);
    echo json_encode(['success'=>true]);
} catch (Exception $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}