<?php
require_once __DIR__ . '/../../config/db.php';

$student_code = $_GET['student_code'] ?? '';

$stmt = $pdo->prepare(
  "SELECT id, full_name, class, profile_photo
   FROM campers
   WHERE student_code = ? AND is_active = 1"
);
$stmt->execute([$student_code]);
$student = $stmt->fetch();

if (!$student) {
  echo json_encode(['success' => false]);
  exit;
}

echo json_encode([
  'success' => true,
  'student' => $student
]);
