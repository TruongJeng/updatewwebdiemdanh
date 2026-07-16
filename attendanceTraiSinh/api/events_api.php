<?php
/**
 * API quản lý sự kiện (ts_events)
 * GET  ?action=list          → Danh sách sự kiện
 * GET  ?action=get&id=X      → Chi tiết 1 sự kiện
 * POST action=create         → Tạo mới
 * POST action=update         → Cập nhật
 * POST action=delete         → Xóa
 */
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../config/db.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
    exit;
}

// Chỉ admin, teacher, club_leader
if (!in_array($_SESSION['role'], ['admin', 'teacher', 'club_leader'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Không có quyền']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    switch ($action) {

        /* ===== DANH SÁCH ===== */
        case 'list':
            $search = trim($_GET['q'] ?? '');
            $params = [];
            $where = '';
            if ($search !== '') {
                $where = "WHERE (e.title LIKE ? OR e.description LIKE ?)";
                $like = "%$search%";
                $params = [$like, $like];
            }

            $sql = "
                SELECT e.*, u.full_name AS creator,
                    (SELECT COUNT(*) FROM attendance_sessions s WHERE s.event_id = e.id) AS session_count,
                    (SELECT COUNT(DISTINCT al.student_id) 
                     FROM attendance_logs al 
                     JOIN attendance_sessions s2 ON al.session_id = s2.id 
                     WHERE s2.event_id = e.id AND al.type = 'CHECK_IN') AS checkin_count
                FROM ts_events e
                JOIN users u ON e.created_by = u.id
                $where
                ORDER BY e.created_at DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'events' => $events]);
            break;

        /* ===== CHI TIẾT ===== */
        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT e.*, u.full_name AS creator FROM ts_events e JOIN users u ON e.created_by = u.id WHERE e.id = ?");
            $stmt->execute([$id]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$event) {
                echo json_encode(['success' => false, 'message' => 'Không tìm thấy sự kiện']);
            } else {
                echo json_encode(['success' => true, 'event' => $event]);
            }
            break;

        /* ===== TẠO MỚI ===== */
        case 'create':
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $event_date = $_POST['event_date'] ?? null;

            if ($title === '') {
                echo json_encode(['success' => false, 'message' => 'Vui lòng nhập tên sự kiện']);
                break;
            }

            $stmt = $pdo->prepare("
                INSERT INTO ts_events (title, description, event_date, created_by)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$title, $description ?: null, $event_date ?: null, $_SESSION['user_id']]);

            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Tạo sự kiện thành công']);
            break;

        /* ===== CẬP NHẬT ===== */
        case 'update':
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $event_date = $_POST['event_date'] ?? null;

            if (!$id || $title === '') {
                echo json_encode(['success' => false, 'message' => 'Thiếu thông tin']);
                break;
            }

            $stmt = $pdo->prepare("
                UPDATE ts_events SET title = ?, description = ?, event_date = ? WHERE id = ?
            ");
            $stmt->execute([$title, $description ?: null, $event_date ?: null, $id]);

            echo json_encode(['success' => true, 'message' => 'Cập nhật thành công']);
            break;

        /* ===== XÓA ===== */
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Thiếu ID']);
                break;
            }

            // Xóa logs liên quan
            $pdo->prepare("
                DELETE al FROM attendance_logs al
                JOIN attendance_sessions s ON al.session_id = s.id
                WHERE s.event_id = ?
            ")->execute([$id]);

            // Xóa sessions liên quan
            $pdo->prepare("DELETE FROM attendance_sessions WHERE event_id = ?")->execute([$id]);

            // Xóa event
            $pdo->prepare("DELETE FROM ts_events WHERE id = ?")->execute([$id]);

            echo json_encode(['success' => true, 'message' => 'Đã xóa sự kiện']);
            break;

        /* ===== ĐÓNG/MỞ SỰ KIỆN ===== */
        case 'toggle_active':
            $id = (int)($_POST['id'] ?? 0);
            $active = (int)($_POST['active'] ?? 0);
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Thiếu ID']);
                break;
            }
            $pdo->prepare("UPDATE ts_events SET is_active = ? WHERE id = ?")->execute([$active, $id]);
            
            // Nếu đóng sự kiện, đóng luôn các phiên điểm danh đang mở của sự kiện đó
            if ($active == 0) {
                $pdo->prepare("UPDATE attendance_sessions SET is_active = 0, end_time = NOW() WHERE event_id = ? AND is_active = 1")->execute([$id]);
            }
            
            echo json_encode(['success' => true, 'message' => $active ? 'Đã mở lại sự kiện' : 'Đã đóng sự kiện và các phiên liên quan']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action không hợp lệ']);
    }
} catch (Exception $e) {
    error_log('Events API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.']);
}
