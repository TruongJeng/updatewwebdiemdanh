<?php
// Kết nối cơ sở dữ liệu (db.php chứa cấu hình PDO)
require __DIR__.'/../includes/db.php';
require __DIR__ . '/PHPMailer/PHPMailer.php';
require __DIR__ . '/PHPMailer/SMTP.php';
require __DIR__ . '/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Debug lỗi trong môi trường phát triển (tắt khi deploy)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Đáp ứng chuẩn JSON
header('Content-Type: application/json; charset=UTF-8');

// Hàm gửi email xác nhận điểm danh
function send_confirm_mail($to_email, $to_name, $event_name, $action, $time, $logo_path = null) {
    static $env = null;
    if ($env === null) {
        $env = require __DIR__ . '/../config/env.php';
    }
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $env['mail']['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $env['mail']['username'];
        $mail->Password = $env['mail']['password'];
        $mail->SMTPSecure = $env['mail']['secure'];
        $mail->Port = $env['mail']['port'];

        // Gửi email
        $mail->setFrom($env['mail']['from_email'], $env['mail']['from_name']);
        $mail->addAddress($to_email, $to_name);

        // Gửi logo nếu có
        if ($logo_path && file_exists($logo_path)) {
            $mail->AddEmbeddedImage($logo_path, 'clb_logo');
        }
        
        $mail->isHTML(true);
        $mail->Subject = "Xác nhận điểm danh: $event_name";
        $body_html = "
            <p>Chào bạn <strong>$to_name</strong>,</p>
            <p>Bạn đã <strong>$action</strong> thành công tại sự kiện <strong>$event_name</strong> vào lúc <strong>$time</strong>.</p>
            <p>Trân trọng,<br>CLB Kỹ năng Đoàn - Hội</p>";
        
        if ($logo_path && file_exists($logo_path)) {
            $body_html = '<div style="text-align:center;"><img src="cid:clb_logo" style="max-height:70px;" alt="Logo CLB Kỹ năng"></div>' . $body_html;
        }

        $mail->Body = $body_html;
        $mail->CharSet = 'UTF-8';
        $mail->send();
        return true; // Gửi thành công
    } catch (Exception $e) {
        return false; // Lỗi khi gửi email
    }
}

try {
    // Kiểm tra tham số đầu vào
    if (!isset($_GET['qr_code']) || !isset($_GET['event_id'])) {
        throw new Exception('Thiếu tham số bắt buộc: qr_code hoặc event_id.');
    }
    $qrCode = $_GET['qr_code'];
    $eventId = $_GET['event_id'];

    // Kiểm tra học sinh trong bảng `students`
    $stmt = $pdo->prepare("SELECT id, full_name, class, email, profile_photo FROM students WHERE student_code = ?");
    $stmt->execute([$qrCode]);
    $student = $stmt->fetch();

    if (!$student) {
        throw new Exception('QR Code không hợp lệ hoặc không tồn tại.');
    }

    // Kiểm tra lịch sử điểm danh
    $stmt = $pdo->prepare("SELECT action, checkin_time, checkout_time FROM attendance WHERE student_id = ? AND event_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$student['id'], $eventId]);
    $lastAttendance = $stmt->fetch();

    // Xác định hành động (check-in hoặc check-out)
    $action = 'check-in'; // Mặc định là check-in
    $time = date('d/m/Y H:i'); // Thời gian hiện tại

    if ($lastAttendance && $lastAttendance['action'] === 'check-in' && !$lastAttendance['checkout_time']) {
        // Nếu đã check-in, lần này sẽ là check-out
        $action = 'check-out';
        $stmt = $pdo->prepare("INSERT INTO attendance (event_id, student_id, action, checkout_time, ip_addr) 
                               VALUES (?, ?, ?, NOW(), ?)");
        $stmt->execute([$eventId, $student['id'], $action, $_SERVER['REMOTE_ADDR']]);
    } else {
        // Trường hợp còn lại là check-in
        $stmt = $pdo->prepare("INSERT INTO attendance (event_id, student_id, action, checkin_time, ip_addr) 
                               VALUES (?, ?, ?, NOW(), ?)");
        $stmt->execute([$eventId, $student['id'], $action, $_SERVER['REMOTE_ADDR']]);
    }

    // Tên sự kiện (có thể lấy từ database)
    $eventName = "Trại Kỹ năng Đoàn - Hội";
    $logoPath = '/path/to/logo.png'; // Thay bằng đường dẫn logo thực tế

    // Gửi email xác nhận
    $sent = send_confirm_mail($student['email'], $student['full_name'], $eventName, $action, $time, $logoPath);

    $responseMessage = ucfirst($action) . " thành công!";
    if (!$sent) {
        $responseMessage .= " (Không thể gửi email xác nhận)";
    }

    // JSON phản hồi cho client
    echo json_encode([
        'success' => true,
        'full_name' => $student['full_name'],
        'class' => $student['class'],
        'profile_photo' => $student['profile_photo'], // Đường dẫn ảnh
        'action' => ucfirst($action),
        'message' => $responseMessage
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}