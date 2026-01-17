<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../config/db.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['pin'])) {
    echo json_encode(['success'=>false,'message'=>'Thiếu PIN']);
    exit;
}

$pin = $data['pin'];

$stmt = $pdo->prepare("
    SELECT id, pin_code, type, is_active
    FROM attendance_sessions
    WHERE pin_code = ? AND is_active = 1
    LIMIT 1
");
$stmt->execute([$pin]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    echo json_encode(['success'=>false,'message'=>'PIN không hợp lệ']);
    exit;
}

/* ✅ LƯU ĐỦ SESSION */
$_SESSION['attendance_session_id'] = $session['id'];
$_SESSION['attendance_type']       = $session['type'];
$_SESSION['scanner_pin']           = $session['pin_code'];

echo json_encode([
    'success'=>true,
    'type'=>$session['type']
]);
