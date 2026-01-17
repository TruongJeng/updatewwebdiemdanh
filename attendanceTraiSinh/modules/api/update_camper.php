<?php
session_start();
if (!in_array($_SESSION['role'], ['admin','club_leader'])) {
    exit(json_encode(['success'=>false,'message'=>'Không có quyền']));
}

require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit(json_encode(['success'=>false,'message'=>'Sai phương thức']));
}

$student_code = $_POST['student_code'] ?? '';
if (!$student_code) {
    exit(json_encode(['success'=>false,'message'=>'Thiếu mã trại sinh']));
}

/* ===== XỬ LÝ ẢNH ===== */
$profilePhoto = trim($_POST['profile_photo'] ?? '');

/* ===== UPDATE ===== */
$sql = "
    UPDATE campers SET
        full_name = ?,
        class = ?,
        phone = ?,
        phone_parent = ?,
        email = ?
";

$params = [
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
$params[] = $student_code;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode(['success'=>true]);
