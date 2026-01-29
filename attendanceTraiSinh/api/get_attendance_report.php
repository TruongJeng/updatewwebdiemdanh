<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Ho_Chi_Minh');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','club_leader','staff'])) {
    echo json_encode(['success'=>false,'message'=>'Không có quyền']);
    exit;
}

$sql = "
SELECT
    c.student_code,
    c.full_name,
    c.class,

    /* ===== ĐỘI (CÓ / KHÔNG) ===== */
    tc.name AS team_name,

    /* ===== LẦN QUÉT GẦN NHẤT ===== */
    last_log.type        AS last_type,
    last_log.scan_time  AS last_scan_time,
    u.full_name         AS scanned_by,

    /* ===== TOÀN BỘ LỊCH SỬ ===== */
    GROUP_CONCAT(
        CONCAT(
            al.type, '|',
            DATE_FORMAT(al.scan_time,'%H:%i %d/%m/%Y'), '|',
            s.pin_code, '|',
            u2.full_name
        )
        ORDER BY al.scan_time ASC
        SEPARATOR ';;'
    ) AS history

FROM campers c

/* ===== TEAM ===== */
LEFT JOIN team_cam_member tcm ON c.student_code = tcm.student_code
LEFT JOIN team_campers tc ON tcm.team_id = tc.id

/* ===== LẦN QUÉT GẦN NHẤT ===== */
LEFT JOIN attendance_logs last_log
    ON last_log.id = (
        SELECT id
        FROM attendance_logs
        WHERE student_id = c.id
        ORDER BY scan_time DESC
        LIMIT 1
    )

LEFT JOIN users u ON last_log.scanned_by = u.id

/* ===== LỊCH SỬ ===== */
LEFT JOIN attendance_logs al ON al.student_id = c.id
LEFT JOIN attendance_sessions s ON al.session_id = s.id
LEFT JOIN users u2 ON al.scanned_by = u2.id

GROUP BY c.id
ORDER BY last_log.scan_time DESC
";

$stmt = $pdo->query($sql);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'total'   => count($data),
    'data'    => $data
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
