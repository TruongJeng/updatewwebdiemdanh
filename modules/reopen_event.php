<?php
// reopen_event.php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json');

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Chưa đăng nhập']);
    exit;
}

// Kiểm tra quyền
if (!in_array($_SESSION['role'], ['admin', 'teacher', 'club_leader'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Không có quyền']);
    exit;
}

// Lấy dữ liệu từ yêu cầu
$data = json_decode(file_get_contents('php://input'), true);
$event_id = isset($data['event_id']) ? (int)$data['event_id'] : 0;

// Kiểm tra CSRF
$csrf = $data['csrf'] ?? $data['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF']);
    exit;
}

if (!$event_id) {
    echo json_encode(['success' => false, 'error' => 'Thiếu event_id']);
    exit;
}

// Cập nhật trạng thái sự kiện thành "mở lại"
$stmt = $pdo->prepare("UPDATE events SET is_closed = 0 WHERE id = ?");
$result = $stmt->execute([$event_id]);

// Kiểm tra kết quả và phản hồi
if ($result) {
    echo json_encode(['success' => true, 'message' => 'Điểm danh đã được mở lại.']);
} else {
    echo json_encode(['success' => false, 'error' => 'Không thể mở lại điểm danh.']);
}