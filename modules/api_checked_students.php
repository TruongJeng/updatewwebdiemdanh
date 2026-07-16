<?php
require_once __DIR__ . '/../config/session.php';
require __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

$event_id = $_GET['event_id'] ?? 0;
if (!$event_id) { http_response_code(400); exit(); }
$stmt = $pdo->prepare("SELECT student_id FROM attendance WHERE event_id = ?");
$stmt->execute([$event_id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));