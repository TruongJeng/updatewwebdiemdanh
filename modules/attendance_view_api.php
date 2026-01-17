<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
$event_id = $_GET['event_id'] ?? '';
if (!$event_id) { echo '[]'; exit(); }

// Sắp xếp lớp: 12 -> 11 -> 10 -> khác, rồi theo tên
$stmt = $pdo->prepare(
    "SELECT s.student_code, s.full_name, s.class 
     FROM attendance a 
     JOIN students s ON a.student_id = s.id 
     WHERE a.event_id = ? 
     ORDER BY 
        CASE 
            WHEN s.class REGEXP '^12' THEN 1
            WHEN s.class REGEXP '^11' THEN 2
            WHEN s.class REGEXP '^10' THEN 3
            ELSE 4
        END, 
        s.class, s.full_name"
);
$stmt->execute([$event_id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));