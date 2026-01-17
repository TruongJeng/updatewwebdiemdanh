<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403); exit('Not login');
}
$user_id = $_SESSION['user_id'];
$event_id = $_POST['event_id'] ?? $_GET['event_id'] ?? 0;
$student_id = $_POST['student_id'] ?? 0;
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action == 'check' && $event_id && $student_id) {
        $stmt = $pdo->prepare("REPLACE INTO attendance_checking_tmp (event_id, student_id, user_id, checking_time) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$event_id, $student_id, $user_id]);
        echo 'ok';
    } elseif ($action == 'uncheck' && $event_id && $student_id) {
        $stmt = $pdo->prepare("DELETE FROM attendance_checking_tmp WHERE event_id=? AND student_id=? AND user_id=?");
        $stmt->execute([$event_id, $student_id, $user_id]);
        echo 'ok';
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $event_id) {
    $stmt = $pdo->prepare("SELECT student_id, user_id FROM attendance_checking_tmp WHERE event_id=? AND user_id<>?");
    $stmt->execute([$event_id, $user_id]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($result);
    exit();
}