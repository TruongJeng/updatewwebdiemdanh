<?php
require_once __DIR__ . '/../includes/db.php';

$event_id = $_POST['event_id'] ?? '';
$student_code = trim($_POST['student_code'] ?? '');

header('Content-Type: application/json; charset=utf-8');
if (!$event_id || !$student_code) {
    echo json_encode(['success'=>0,'msg'=>'Thiếu thông tin!']);
    exit;
}

// Xác thực mã học sinh
$stmt = $pdo->prepare("SELECT id, full_name, class FROM students WHERE student_code = ?");
$stmt->execute([$student_code]);
$student = $stmt->fetch();
if (!$student) {
    echo json_encode(['success'=>0,'msg'=>'Không tìm thấy học sinh!']);
    exit;
}

// Kiểm tra đã điểm danh chưa
$stmt = $pdo->prepare("SELECT * FROM attendance WHERE event_id=? AND student_id=?");
$stmt->execute([$event_id, $student['id']]);
if ($stmt->fetch()) {
    echo json_encode(['success'=>0,'msg'=>'Học sinh đã được điểm danh!']);
    exit;
}

// Ghi điểm danh
$stmt = $pdo->prepare("INSERT INTO attendance (event_id, student_id, checkin_time) VALUES (?, ?, NOW())");
$stmt->execute([$event_id, $student['id']]);

echo json_encode([
    'success'=>1,
    'msg'=>'Điểm danh thành công!',
    'full_name'=>$student['full_name'],
    'class'=>$student['class']
]);