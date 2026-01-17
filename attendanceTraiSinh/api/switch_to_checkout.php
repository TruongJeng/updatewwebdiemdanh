<?php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','club_leader'])) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Không có quyền']);
    exit;
}

if (!isset($_SESSION['attendance_session_id'])) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Chưa có phiên']);
    exit;
}

$sessionId = $_SESSION['attendance_session_id'];

$stmt = $pdo->prepare("
    UPDATE attendance_sessions
    SET mode = 'CHECK_OUT'
    WHERE id = ?
");
$stmt->execute([$sessionId]);

// cập nhật luôn session để scan dùng ngay
$_SESSION['attendance_type'] = 'CHECK_OUT';

echo json_encode(['success'=>true]);
