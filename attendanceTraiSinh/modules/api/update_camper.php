<?php
session_start();
if (!in_array($_SESSION['role'], ['admin','club_leader'])) {
    exit(json_encode(['success'=>false,'message'=>'Không có quyền']));
}

require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit(json_encode(['success'=>false,'message'=>'Sai phương thức']));
}

$old_student_code = $_POST['old_student_code'] ?? '';
$new_student_code = trim($_POST['student_code'] ?? '');

if (!$old_student_code || !$new_student_code) {
    exit(json_encode(['success'=>false,'message'=>'Thiếu mã trại sinh']));
}

// Kiểm tra mã trại sinh mới đã tồn tại chưa
if ($old_student_code !== $new_student_code) {
    $check = $pdo->prepare("SELECT 1 FROM campers WHERE student_code = ?");
    $check->execute([$new_student_code]);
    if ($check->fetch()) {
        exit(json_encode(['success'=>false,'message'=>'Mã học sinh đã tồn tại']));
    }
}

/* ===== XỬ LÝ ẢNH ===== */
$profilePhoto = trim($_POST['profile_photo'] ?? '');

/* ===== UPDATE ===== */
$sql = "
    UPDATE campers SET
        student_code = ?,
        full_name = ?,
        class = ?,
        phone = ?,
        phone_parent = ?,
        email = ?
";

$params = [
    $new_student_code,
    $_POST['full_name'],
    $_POST['class'],
    $_POST['phone'],
    $_POST['phone_parent'],
    $_POST['email']
];

if ($profilePhoto !== '') {
    $sql .= ", profile_photo = ?";
    $params[] = $profilePhoto; 
}


$sql .= " WHERE student_code = ?";
$params[] = $old_student_code;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode(['success'=>true]);
