<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Ho_Chi_Minh');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../config/db.php';

if (isset($_SESSION['attendance_session_id'])) {
    $checkStmt = $pdo->prepare("
        SELECT s.is_active, e.is_active as event_active 
        FROM attendance_sessions s 
        LEFT JOIN ts_events e ON s.event_id = e.id 
        WHERE s.id = ?
    ");
    $checkStmt->execute([$_SESSION['attendance_session_id']]);
    $sessData = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    // Nếu phiên không tồn tại, phiên đã đóng, hoặc sự kiện đã đóng -> xoá session PHP
    if (!$sessData || $sessData['is_active'] == 0 || (isset($sessData['event_active']) && $sessData['event_active'] == 0)) {
        unset($_SESSION['attendance_session_id']);
        unset($_SESSION['attendance_type']);
        unset($_SESSION['scanner_pin']);
    }
}

if (!isset($_SESSION['attendance_session_id'])) {
    // Thử tự động phát hiện phiên đang hoạt động từ DB
    $activeStmt = $pdo->query("SELECT id, type, pin_code FROM attendance_sessions WHERE is_active = 1 ORDER BY start_time DESC LIMIT 1");
    $activeSession = $activeStmt->fetch(PDO::FETCH_ASSOC);
    if ($activeSession) {
        $_SESSION['attendance_session_id'] = $activeSession['id'];
        $_SESSION['attendance_type']       = $activeSession['type'];
        $_SESSION['scanner_pin']           = $activeSession['pin_code'];
    } else {
        echo json_encode(['success'=>false,'message'=>'Chưa mở phiên']);
        exit;
    }
}
//Hiện tại chỉ lấy danh sách trong phiên đang mở
$sessionId = $_SESSION['attendance_session_id'];

$stmt = $pdo->prepare("
SELECT
    s.student_code,
    s.full_name,
    s.class,

    -- TRẠNG THÁI HIỆN TẠI (LẦN QUÉT MỚI NHẤT)
    l.type        AS type,
    l.scan_time   AS scan_time,
    COALESCE(u.full_name, 'Tự điểm danh') AS scanned_by,

    -- TOÀN BỘ LỊCH SỬ
    GROUP_CONCAT(
        CONCAT(
            l2.type, '|',
            DATE_FORMAT(l2.scan_time,'%H:%i:%s %d/%m/%Y'), '|',
            asess.pin_code, '|',
            COALESCE(u2.full_name, 'Tự điểm danh')
        )
        ORDER BY l2.scan_time ASC
        SEPARATOR ';;'
    ) AS history

FROM campers s

-- LẤY LOG MỚI NHẤT
JOIN attendance_logs l
    ON l.id = (
        SELECT id
        FROM attendance_logs
        WHERE student_id = s.id
          AND session_id = ?
        ORDER BY scan_time DESC
        LIMIT 1
    )

LEFT JOIN users u ON l.scanned_by = u.id

-- LỊCH SỬ
LEFT JOIN attendance_logs l2 ON l2.student_id = s.id
LEFT JOIN attendance_sessions asess ON l2.session_id = asess.id
LEFT JOIN users u2 ON l2.scanned_by = u2.id

WHERE l.session_id = ?
GROUP BY s.id
ORDER BY l.scan_time DESC


");

$stmt->execute([$sessionId, $sessionId]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'data' => $data
]);
