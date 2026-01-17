<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Ho_Chi_Minh');

session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['attendance_session_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Chưa mở phiên'
    ]);
    exit;
}

$sessionId = $_SESSION['attendance_session_id'];

try {

    /* ===== TỔNG TRẠI SINH ===== */
    $total = (int)$pdo->query("SELECT COUNT(*) FROM campers")->fetchColumn();

    /* ===== CHECK-IN ===== */
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT student_id)
        FROM attendance_logs
        WHERE type = 'CHECK_IN' AND session_id = ?
    ");
    $stmt->execute([$sessionId]);
    $checkIn = (int)$stmt->fetchColumn();

    /* ===== CHECK-OUT ===== */
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT student_id)
        FROM attendance_logs
        WHERE type = 'CHECK_OUT' AND session_id = ?
    ");
    $stmt->execute([$sessionId]);
    $checkOut = (int)$stmt->fetchColumn();

    /* ===== ĐANG TRONG TRẠI ===== */
    $inside = max(0, $checkIn - $checkOut);

    echo json_encode([
        'success'   => true,
        'total'     => $total,
        'check_in'  => $checkIn,
        'check_out' => $checkOut,
        'inside'    => $inside
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
