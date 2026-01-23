<?php
ob_clean();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';

/* ===== 1. READ JSON INPUT ===== */
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$studentCode = trim($data['student_code'] ?? '');

/* ===== 2. VALIDATE ===== */
if ($studentCode === '' || !preg_match('/^\d+$/', $studentCode)) {
    echo json_encode([
        'success' => false,
        'message' => 'QR không hợp lệ'
    ]);
    exit;
}

/* ===== 3. QUERY ===== */
$stmt = $pdo->prepare("
    SELECT student_code, full_name, class, profile_photo
    FROM campers
    WHERE student_code = ?
      AND is_active = 1
    LIMIT 1
");
$stmt->execute([$studentCode]);

$c = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$c) {
    echo json_encode([
        'success' => false,
        'message' => 'Không tìm thấy trại sinh'
    ]);
    exit;
}

/* ===== 4. RESPONSE ===== */
echo json_encode([
    'success' => true,
    'student' => [
        'code'   => $c['student_code'],
        'name'   => $c['full_name'],
        'class'  => $c['class'],
        'avatar' => $c['profile_photo']
            ? '/hethongdiemdanh/uploads/' . $c['profile_photo']
            : '/hethongdiemdanh/assets/default.png'
    ]
]);
