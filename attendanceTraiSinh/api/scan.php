<?php
ob_clean(); // ⭐ QUAN TRỌNG
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../config/db.php';

/* ===== 1. CHECK SESSION ===== */
if (
    !isset($_SESSION['attendance_session_id']) ||
    !isset($_SESSION['scanner_pin']) ||
    !isset($_SESSION['user_id'])
) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Chưa mở phiên']);
    exit;
}
$sessionId = $_SESSION['attendance_session_id'];
$userId    = $_SESSION['user_id'];

/* ===== 2. LẤY PHIÊN ===== */
$stmt = $pdo->prepare("
    SELECT pin_code, type, is_active
    FROM attendance_sessions
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$sessionId]);
$sess = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sess) {
    http_response_code(403);
    echo json_encode(['success'=>false,'code'=>'SESSION_NOT_FOUND']);
    exit;
}

/* ===== 3. PIN ĐỔI → ĐÁ SCANNER ===== */
if ($_SESSION['scanner_pin'] !== $sess['pin_code']) {
    unset($_SESSION['attendance_session_id'], $_SESSION['attendance_type'], $_SESSION['scanner_pin']);
    http_response_code(409);
    echo json_encode(['success'=>false,'code'=>'PIN_CHANGED']);
    exit;
}

/* ===== 4. SESSION ĐÓNG ===== */
if ((int)$sess['is_active'] !== 1) {
    unset($_SESSION['attendance_session_id'], $_SESSION['attendance_type'], $_SESSION['scanner_pin']);
    http_response_code(403);
    echo json_encode(['success'=>false,'code'=>'SESSION_CLOSED']);
    exit;
}

$type = $sess['type']; // CHECK_IN / CHECK_OUT

/* ===== 5. NHẬN QR ===== */
$studentCode = trim(file_get_contents('php://input'));
if ($studentCode === '' || !ctype_digit($studentCode)) {
    echo json_encode(['success'=>false,'message'=>'QR không hợp lệ']);
    exit;
}

/* ===== 6. LẤY TRẠI SINH ===== */
$stmt = $pdo->prepare("
    SELECT id, student_code, full_name, class, profile_photo
    FROM campers
    WHERE student_code = ?
    LIMIT 1
");
$stmt->execute([$studentCode]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo json_encode(['success'=>false,'message'=>'Không tìm thấy trại sinh']);
    exit;
}

$studentId = $student['id'];

/* ===== 7. LOG GẦN NHẤT ===== */
$stmt = $pdo->prepare("
    SELECT type
    FROM attendance_logs
    WHERE student_id = ? AND session_id = ?
    ORDER BY scan_time DESC
    LIMIT 1
");
$stmt->execute([$studentId, $sessionId]);
$lastLog = $stmt->fetch(PDO::FETCH_ASSOC);

/* ===== 8. NGHIỆP VỤ ===== */
if ($type === 'CHECK_IN' && $lastLog && $lastLog['type'] === 'CHECK_IN') {
    echo json_encode(['success'=>false,'message'=>'Đã CHECK-IN']);
    exit;
}

if ($type === 'CHECK_OUT') {
    if (!$lastLog) {
        echo json_encode(['success'=>false,'message'=>'Chưa CHECK-IN']);
        exit;
    }
    if ($lastLog['type'] === 'CHECK_OUT') {
        echo json_encode(['success'=>false,'message'=>'Đã CHECK-OUT']);
        exit;
    }
}

/* ===== 9. GHI LOG ===== */
if ($type === 'CHECK_IN') {

    // chỉ INSERT nếu chưa có log
    $stmt = $pdo->prepare("
        INSERT INTO attendance_logs
        (student_id, session_id, type, scan_time, scanned_by)
        VALUES (?, ?, 'CHECK_IN', NOW(), ?)
    ");
    $stmt->execute([$studentId, $sessionId, $userId]);

} else { // CHECK_OUT

    // UPDATE log cũ
    $stmt = $pdo->prepare("
        UPDATE attendance_logs
        SET type = 'CHECK_OUT',
            scan_time = NOW(),
            scanned_by = ?
        WHERE student_id = ? AND session_id = ?
    ");
    $stmt->execute([$userId, $studentId, $sessionId]);
}


/* ===== 10. RESPONSE ===== */
echo json_encode([
    'success'=>true,
    'student'=>[
        'student_code'=>$student['student_code'],
        'name'=>$student['full_name'],
        'class'=>$student['class'],
        'avatar' => $student['profile_photo'] ?: null
    ],
    'type'=>$type,
    'time'=>date('H:i:s')
]);
