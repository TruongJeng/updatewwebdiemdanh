<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../config/db.php';

/* CHECK PHIÊN */
if (!isset($_SESSION['attendance_session_id'])) {
    echo json_encode(['success'=>false,'message'=>'Chưa mở phiên']);
    exit;
}

$q = trim($_GET['q'] ?? '');
if ($q === '' || strlen($q) < 2) {
    echo json_encode(['success'=>true,'data'=>[]]);
    exit;
}

$sessionId = $_SESSION['attendance_session_id'];

/*
 Lấy trại sinh + trạng thái gần nhất trong PHIÊN HIỆN TẠI
*/
$stmt = $pdo->prepare("
    SELECT
        c.student_code,
        c.full_name,
        c.class,
        (
            SELECT l.type
            FROM attendance_logs l
            WHERE l.student_id = c.id
              AND l.session_id = ?
            ORDER BY l.scan_time DESC
            LIMIT 1
        ) AS type
    FROM campers c
    WHERE
        c.student_code LIKE ?
        OR c.full_name LIKE ?
        OR c.class LIKE ?
    ORDER BY c.full_name ASC
    LIMIT 30
");

$like = "%$q%";
$stmt->execute([$sessionId, $like, $like, $like]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'data' => $data
]);
