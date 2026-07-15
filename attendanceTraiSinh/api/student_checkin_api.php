<?php
/**
 * API xử lý học sinh tự điểm danh
 * POST: { student_code, event_id, lat, lng, gps_time, gps_source }
 */
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../../PHPMailer/src/Exception.php';
$env = require __DIR__ . '/../../config/env.php';

$data = json_decode(file_get_contents('php://input'), true);
$studentCode = trim($data['student_code'] ?? '');
$fullName = trim($data['full_name'] ?? '');
$className = trim($data['class'] ?? '');
$email = trim($data['email'] ?? '');
$eventId = (int)($data['event_id'] ?? 0);

// GPS data
$lat = isset($data['lat']) && $data['lat'] !== '' ? (float)$data['lat'] : null;
$lng = isset($data['lng']) && $data['lng'] !== '' ? (float)$data['lng'] : null;
$gps_time = $data['gps_time'] ?? null;
$gps_source = trim($data['gps_source'] ?? '');

// IP address
function get_client_ip() {
    foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) $ip = explode(',', $ip)[0];
            return trim($ip);
        }
    }
    return 'UNKNOWN';
}
$ip_addr = get_client_ip();

if (!$fullName || !$className || !$email || !$eventId) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin (Họ tên, Lớp, Email) hoặc sự kiện']);
    exit;
}

try {
    // 1. Kiểm tra sự kiện
    $stmt = $pdo->prepare("SELECT id, title, is_active, created_by FROM ts_events WHERE id = ?");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Sự kiện không tồn tại']);
        exit;
    }

    if (!$event['is_active']) {
        echo json_encode(['success' => false, 'message' => 'Sự kiện đã đóng']);
        exit;
    }

    // 2. Tìm hoặc tạo trại sinh
    $camper = null;
    if ($studentCode !== '') {
        $stmt = $pdo->prepare("SELECT id, student_code, full_name, class, profile_photo FROM campers WHERE student_code = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$studentCode]);
        $camper = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$camper) {
        $stmt = $pdo->prepare("SELECT id, student_code, full_name, class, profile_photo FROM campers WHERE full_name = ? AND class = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$fullName, $className]);
        $camper = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($camper) {
        // Cập nhật email
        $pdo->prepare("UPDATE campers SET email = ? WHERE id = ?")->execute([$email, $camper['id']]);
    } else {
        // HỌC SINH KHÔNG CÓ TRONG DANH SÁCH
        // 1. Gửi mail cảnh báo về cho CLB
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $env['mail']['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $env['mail']['username'];
            $mail->Password = $env['mail']['password'];
            $mail->SMTPSecure = $env['mail']['secure'];
            $mail->Port = $env['mail']['port'];
            $mail->setFrom($env['mail']['from_email'], $env['mail']['from_name']);
            
            // Gửi về email của CLB để CLB xử lý
            $mail->addAddress('clbkynangdoan.ltk@gmail.com', 'Hệ thống điểm danh CLB');
            
            $mail->isHTML(true);
            $mail->Subject = "[CẢNH BÁO] Học viên không có trong danh sách cố gắng điểm danh";
            
            // Nhúng Logo
            $logo_path = __DIR__ . '/../../assets/Logo_CLB.png';
            if (file_exists($logo_path)) {
                $mail->AddEmbeddedImage($logo_path, 'clb_logo');
                $logo_html = '<div style="text-align:center;margin-bottom:20px;"><img src="cid:clb_logo" style="max-height:80px;" alt="Logo CLB"></div>';
            } else {
                $logo_html = '';
            }

            $mail->Body = "
            <div style='font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); background-color: #ffffff;'>
                <div style='background-color: #ef4444; color: white; padding: 24px; text-align: center;'>
                    <h2 style='margin: 0; font-size: 24px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;'>Cảnh Báo Điểm Danh</h2>
                </div>
                <div style='padding: 32px;'>
                    $logo_html
                    <p style='color: #374151; font-size: 16px; line-height: 1.6; margin-bottom: 24px;'>
                        Kính gửi <strong>Ban Chủ Nhiệm CLB</strong>,<br><br>
                        Hệ thống vừa ghi nhận một lượt quét mã điểm danh từ một học viên <strong>KHÔNG CÓ trong cơ sở dữ liệu</strong>. Chi tiết hệ thống ghi nhận như sau:
                    </p>
                    
                    <div style='background-color: #f9fafb; border: 1px solid #f3f4f6; border-radius: 8px; padding: 24px; margin-bottom: 24px;'>
                        <table style='width: 100%; border-collapse: collapse; font-size: 15px;'>
                            <tr>
                                <td style='padding: 10px 0; border-bottom: 1px dashed #d1d5db; color: #6b7280; width: 130px;'>Họ và tên nhập:</td>
                                <td style='padding: 10px 0; border-bottom: 1px dashed #d1d5db; color: #111827; font-weight: 700;'>" . htmlspecialchars($fullName) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px 0; border-bottom: 1px dashed #d1d5db; color: #6b7280;'>Lớp nhập:</td>
                                <td style='padding: 10px 0; border-bottom: 1px dashed #d1d5db; color: #111827; font-weight: 700;'>" . htmlspecialchars($className) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px 0; border-bottom: 1px dashed #d1d5db; color: #6b7280;'>Email liên hệ:</td>
                                <td style='padding: 10px 0; border-bottom: 1px dashed #d1d5db; color: #111827; font-weight: 700;'>" . htmlspecialchars($email) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px 0; border-bottom: 1px dashed #d1d5db; color: #6b7280;'>Mã thẻ nhập:</td>
                                <td style='padding: 10px 0; border-bottom: 1px dashed #d1d5db; color: #ef4444; font-weight: 700;'>" . ($studentCode ? htmlspecialchars($studentCode) : '<span style="color:#9ca3af;font-weight:normal;font-style:italic;">Đã bỏ trống</span>') . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px 0; border-bottom: 1px dashed #d1d5db; color: #6b7280;'>Sự kiện:</td>
                                <td style='padding: 10px 0; border-bottom: 1px dashed #d1d5db; color: #111827; font-weight: 700;'>" . htmlspecialchars($event['title']) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 10px 0; color: #6b7280;'>Thời gian quét:</td>
                                <td style='padding: 10px 0; color: #111827; font-weight: 700;'>" . date('H:i:s - d/m/Y') . "</td>
                            </tr>
                        </table>
                    </div>
                    
                    <div style='border-left: 4px solid #ef4444; padding-left: 16px; margin-top: 10px;'>
                        <p style='color: #ef4444; font-size: 15px; font-weight: 800; margin-bottom: 8px; margin-top: 0;'>Hành động đề xuất:</p>
                        <p style='color: #4b5563; font-size: 14px; line-height: 1.6; margin-top: 0; margin-bottom: 0;'>
                            Vui lòng đối chiếu thông tin trên với danh sách thực tế. Nếu đây là học viên hợp lệ, xin hãy chủ động thêm vào hệ thống quản lý trên web để bạn ấy có thể điểm danh bình thường.
                        </p>
                    </div>
                </div>
                <div style='background-color: #f3f4f6; padding: 16px; text-align: center; color: #9ca3af; font-size: 13px; border-top: 1px solid #e5e7eb;'>
                    Email được gửi tự động từ <strong>Hệ Thống Điểm Danh Trại Sinh</strong>.<br>Vui lòng không trả lời thư này.
                </div>
            </div>";
            $mail->CharSet = 'UTF-8';
            $mail->send();
        } catch (Exception $e) {}

        // 2. Báo lỗi ra màn hình cho học sinh
        echo json_encode(['success' => false, 'message' => 'Hiện tại bạn không có trong danh sách, vui lòng báo cho Ban chủ nhiệm để xử lý.']);
        exit;
    }

    // 3. Tìm hoặc tạo phiên CHECK_IN cho event
    $stmt = $pdo->prepare("SELECT id FROM attendance_sessions WHERE event_id = ? AND type = 'CHECK_IN' AND is_active = 1 ORDER BY start_time DESC LIMIT 1");
    $stmt->execute([$eventId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        // Tạo phiên tự động, sử dụng ID của người tạo sự kiện
        $pin = strval(rand(100000, 999999));
        $stmt = $pdo->prepare("INSERT INTO attendance_sessions (event_id, pin_code, type, created_by, is_active) VALUES (?, ?, 'CHECK_IN', ?, 1)");
        $stmt->execute([$eventId, $pin, $event['created_by']]);
        $sessionId = $pdo->lastInsertId();
    } else {
        $sessionId = $session['id'];
    }

    // 4. Kiểm tra đã check-in chưa (trong event này)
    $stmt = $pdo->prepare("
        SELECT al.id FROM attendance_logs al
        JOIN attendance_sessions s ON al.session_id = s.id
        WHERE al.student_id = ? AND s.event_id = ? AND al.type = 'CHECK_IN'
        LIMIT 1
    ");
    $stmt->execute([$camper['id'], $eventId]);

    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'Bạn đã điểm danh cho sự kiện này rồi!',
            'already_checked' => true
        ]);
        exit;
    }

    // 5. Ghi log (kèm GPS + IP)
    $stmt = $pdo->prepare("
        INSERT INTO attendance_logs (student_id, session_id, type, scan_time, scanned_by, lat, lng, gps_time, gps_source, ip_addr) 
        VALUES (?, ?, 'CHECK_IN', NOW(), NULL, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$camper['id'], $sessionId, $lat, $lng, $gps_time, $gps_source, $ip_addr]);

    // 6. Gửi Email thông báo thành công cho học sinh
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $env['mail']['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $env['mail']['username'];
        $mail->Password = $env['mail']['password'];
        $mail->SMTPSecure = $env['mail']['secure'];
        $mail->Port = $env['mail']['port'];
        $mail->setFrom($env['mail']['from_email'], $env['mail']['from_name']);
        $mail->addAddress($email, $fullName);

        $logo_path = __DIR__ . '/../../assets/Logo_CLB.png';
        if (file_exists($logo_path)) {
            $mail->AddEmbeddedImage($logo_path, 'clb_logo');
            $body_html = '<div style="text-align:center;margin-bottom:18px;"><img src="cid:clb_logo" style="max-height:70px;"></div>';
        } else {
            $body_html = '';
        }

        $body_html .= "<div style='font-family:Arial,sans-serif;'>
            <h2 style='color:#3178c6;'>XÁC NHẬN ĐIỂM DANH THÀNH CÔNG</h2>
            <p>Kính chào <b>$fullName</b>,</p>
            <p>Ban Chủ nhiệm trân trọng thông báo: bạn đã điểm danh thành công cho sự kiện <b style='color:#6f42c1;'>{$event['title']}</b> vào ngày <b>" . date('d/m/Y') . "</b>.</p>
            <p>Mã số thẻ của bạn là: <b>{$camper['student_code']}</b>. Bạn có thể sử dụng mã này để điểm danh nhanh vào lần sau!</p>
            <hr><small>Trân trọng,<br><b>Ban Chủ nhiệm CLB</b></small>
            </div>";

        $mail->isHTML(true);
        $mail->Subject = "[TB] XÁC NHẬN ĐIỂM DANH SỰ KIỆN " . mb_strtoupper($event['title'], 'UTF-8');
        $mail->Body = $body_html;
        $mail->CharSet = 'UTF-8';
        $mail->send();
    } catch (Exception $e) {}

    echo json_encode([
        'success' => true,
        'message' => 'Điểm danh thành công!',
        'student' => [
            'name' => $camper['full_name'],
            'class' => $camper['class'],
            'code' => $camper['student_code'],
            'avatar' => $camper['profile_photo']
                ? '/hethongdiemdanh/uploads/' . $camper['profile_photo']
                : '/hethongdiemdanh/assets/default.png'
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Lỗi server: ' . $e->getMessage()]);
}
