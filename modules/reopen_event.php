<?php
// Kết nối cơ sở dữ liệu
require __DIR__ . '/../includes/db.php';

// Lấy dữ liệu từ yêu cầu
$data = json_decode(file_get_contents('php://input'), true);
$event_id = $data['event_id'];

// Cập nhật trạng thái sự kiện thành "mở lại"
$stmt = $pdo->prepare("UPDATE events SET is_closed = 0 WHERE id = ?");
$result = $stmt->execute([$event_id]);

// Kiểm tra kết quả và phản hồi
if ($result) {
    echo json_encode(['success' => true, 'message' => 'Điểm danh đã được mở lại.']);
} else {
    echo json_encode(['success' => false, 'error' => 'Không thể mở lại điểm danh.']);
}