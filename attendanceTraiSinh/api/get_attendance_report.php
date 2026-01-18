<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Ho_Chi_Minh');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/session.php'; // nếu cần phân quyền
require_once __DIR__ . '/../config/db.php';          // $pdo

$stmt = $pdo->prepare("
SELECT
    s.student_code,
    s.full_name,
    s.class,

    /* LẦN QUÉT GẦN NHẤT */
    l.type        AS last_type,
    l.scan_time   AS last_scan_time,
    u.full_name   AS scanned_by,

    /* TRẠNG THÁI HIỂN THỊ */
    CASE
        WHEN l.type = 'IN'  THEN 'Đã check-in'
        WHEN l.type = 'OUT' THEN 'Đã check-out'
        ELSE 'Chưa tham gia'
    END AS status_text,

    /* TOÀN BỘ LỊCH SỬ (nếu cần) */
    GROUP_CONCAT(
        CONCAT(
            l2.type, '|',
            DATE_FORMAT(l2.scan_time,'%H:%i:%s %d/%m/%Y'), '|',
            IFNULL(asess.pin_code,''), '|',
            u2.full_name
        )
        ORDER BY l2.scan_time ASC
        SEPARATOR ';;'
    ) AS history

FROM campers s

/* LẤY LOG GẦN NHẤT (KHÔNG LỌC SESSION) */
LEFT JOIN attendance_logs l
    ON l.id = (
        SELECT id
        FROM attendance_logs
        WHERE student_id = s.id
        ORDER BY scan_time DESC
        LIMIT 1
    )

LEFT JOIN users u ON l.scanned_by = u.id

/* LỊCH SỬ */
LEFT JOIN attendance_logs l2 ON l2.student_id = s.id
LEFT JOIN attendance_sessions asess ON l2.session_id = asess.id
LEFT JOIN users u2 ON l2.scanned_by = u2.id

GROUP BY s.id
ORDER BY l.scan_time DESC
");

$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'total'   => count($data),
    'data'    => $data
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);