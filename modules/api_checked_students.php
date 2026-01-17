<?php
require __DIR__ . '/../includes/db.php';
$event_id = $_GET['event_id'] ?? 0;
if (!$event_id) { http_response_code(400); exit(); }
$stmt = $pdo->prepare("SELECT student_id FROM attendance WHERE event_id = ?");
$stmt->execute([$event_id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));