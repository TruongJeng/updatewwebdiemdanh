<?php
/**
 * API cấp token quét mã QR tự điểm danh (Thay đổi mỗi 15s)
 * GET: ?event_id=X
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/session.php'; // Chỉ cho phép admin/btc

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teacher', 'club_leader', 'staff'])) {
    echo json_encode(['success' => false, 'message' => 'Không có quyền']);
    exit;
}

$eventId = (int)($_GET['event_id'] ?? 0);
if (!$eventId) {
    echo json_encode(['success' => false, 'message' => 'Thiếu event_id']);
    exit;
}

$time = time();
$payload = $eventId . '|' . $time;
$env = require __DIR__ . '/../../config/env.php';
$secret = $env['qr_secret'] ?? 'clbkynang_qr_secret_2026'; // Khóa bí mật
$hash = hash_hmac('sha256', $payload, $secret);
$token = base64_encode($payload . '|' . $hash);

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
// Xây dựng đường dẫn tuyệt đối dựa trên cấu trúc thư mục hiện tại
$baseUrl = $protocol . '://' . $host . '/hethongdiemdanh/attendanceTraiSinh/views';
$url = $baseUrl . "/student_checkin.php?event_id={$eventId}&t={$token}";

echo json_encode([
    'success' => true,
    'token' => $token,
    'url' => $url,
    'time' => $time
]);
