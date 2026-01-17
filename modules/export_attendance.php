<?php
require_once __DIR__ . '/../config/session.php';
require __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit(); }
$event_id = $_GET['event_id'] ?? 0;
if (!$event_id) { die("Thiếu mã sự kiện!"); }

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=attendance_event_'.$event_id.'.csv');
$output = fopen('php://output', 'w');
fputcsv($output, array('Mã số', 'Họ', 'Tên', 'Lớp', 'Số điện thoại', 'Email', 'Địa chỉ', 'Ghi chú', 'Thời gian điểm danh'));

$sql = "SELECT s.student_code, s.ho, s.ten, s.class, s.phone, s.email, s.address, s.note, a.checkin_time
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE a.event_id = ?
        ORDER BY s.class, s.student_code";
$stmt = $pdo->prepare($sql);
$stmt->execute([$event_id]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $row['student_code'],
        $row['ho'],
        $row['ten'],
        $row['class'],
        $row['phone'],
        $row['email'],
        $row['address'],
        $row['note'],
        $row['checkin_time'],
    ]);
}
fclose($output);
exit();
?>