<?php
/**
 * API xóa 1 attendance log
 * POST: { log_id, csrf }
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$logId = (int)($data['log_id'] ?? 0);

if (!$logId) {
    echo json_encode(['success' => false, 'message' => 'Thiếu log_id']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM attendance_logs WHERE id = ?");
    $stmt->execute([$logId]);

    echo json_encode(['success' => true, 'message' => 'Đã xóa']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
