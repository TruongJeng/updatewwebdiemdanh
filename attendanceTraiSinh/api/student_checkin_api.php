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

    // 3. Kiểm tra xem sự kiện có phiên điểm danh nào ĐANG MỞ hay không
    $stmt = $pdo->prepare("SELECT id, type, lat, lng, radius FROM attendance_sessions WHERE event_id = ? AND is_active = 1 ORDER BY start_time DESC LIMIT 1");
    $stmt->execute([$eventId]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        echo json_encode(['success' => false, 'message' => 'Hiện tại chưa có phiên điểm danh nào được mở cho sự kiện này. Bạn hãy đợi BTC mở phiên nhé.']);
        exit;
    }

    $sessionId = $session['id'];
    $sessionType = $session['type']; // 'CHECK_IN' or 'CHECK_OUT'

    // 3.5 Geofencing Check
    if (!empty($session['lat']) && !empty($session['lng']) && !empty($session['radius'])) {
        if (empty($lat) || empty($lng)) {
            echo json_encode(['success' => false, 'message' => 'Bạn chưa cấp quyền vị trí GPS. Vui lòng cho phép truy cập vị trí để điểm danh.']);
            exit;
        }

        // Haversine formula
        $earth_radius = 6371000; // in meters
        $dLat = deg2rad($lat - $session['lat']);
        $dLon = deg2rad($lng - $session['lng']);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($session['lat'])) * cos(deg2rad($lat)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * asin(sqrt($a));
        $distance = round($earth_radius * $c);

        if ($distance > $session['radius']) {
            echo json_encode([
                'success' => false, 
                'message' => "Bạn đang ở quá xa vị trí điểm danh (Cách {$distance}m). Yêu cầu phải đứng trong bán kính {$session['radius']}m."
            ]);
            exit;
        }
    }

    // 4. Kiểm tra đã check-in/out chưa (trong event này)
    $stmt = $pdo->prepare("
        SELECT al.id FROM attendance_logs al
        JOIN attendance_sessions s ON al.session_id = s.id
        WHERE al.student_id = ? AND s.event_id = ? AND al.type = ?
        LIMIT 1
    ");
    $stmt->execute([$camper['id'], $eventId, $sessionType]);

    if ($stmt->fetch()) {
        $typeName = ($sessionType === 'CHECK_IN') ? 'vào' : 'ra';
        echo json_encode([
            'success' => false,
            'message' => 'Bạn đã điểm danh ' . $typeName . ' cho sự kiện này rồi!',
            'already_checked' => true
        ]);
        exit;
    }

    // 5. Ghi log (kèm GPS + IP)
    $stmt = $pdo->prepare("
        INSERT INTO attendance_logs (student_id, session_id, type, scan_time, scanned_by, lat, lng, gps_time, gps_source, ip_addr) 
        VALUES (?, ?, ?, NOW(), NULL, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$camper['id'], $sessionId, $sessionType, $lat, $lng, $gps_time, $gps_source, $ip_addr]);

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

        $currentDate = date('d/m/Y');
        $body_html = <<<HTML
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác nhận Điểm danh Thành công</title>
    <style>
        @media screen and (max-width: 600px) {
            .outer-wrapper {
                padding: 15px 10px !important;
            }

            .main-container {
                width: 100% !important;
                border-radius: 12px !important;
            }

            .header-cell {
                padding: 25px 15px 15px 15px !important;
            }

            .content-cell {
                padding: 0 15px 20px 15px !important;
            }

            .title-h2 {
                font-size: 18px !important;
            }

            .body-text,
            .body-text p {
                font-size: 14px !important;
            }

            .alert-box {
                padding: 15px !important;
            }

            .note-cell {
                padding: 0 15px 20px 15px !important;
            }

            .footer-cell {
                padding: 20px 15px !important;
                font-size: 11px !important;
            }
        }
    </style>
</head>

<body
    style="margin:0;padding:0;background-color:#f0fdf4;font-family:Arial, sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">

    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color:#f0fdf4;">
        <tr>
            <td align="center" class="outer-wrapper" style="padding:30px 10px;">
                <!-- MAIN CONTAINER -->
                <table width="100%" border="0" cellspacing="0" cellpadding="0" class="main-container"
                    style="max-width:600px;background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 10px 15px -3px rgba(0,0,0,0.1);border-top: 8px solid #22c55e;">
                    <!-- HEADER -->
                    <tr>
                        <td align="center" class="header-cell" style="padding:30px 20px 20px 20px;">
                            <img src="https://res.cloudinary.com/df4ux0inj/image/upload/v1769186523/logo_CLB_1_tpyenv.png"
                                alt="Logo" width="80" style="display:block;margin-bottom:12px;">
                            <h3
                                style="margin:4px 0;color:#166534;font-size:12px;letter-spacing:1px;text-transform:uppercase;font-weight:normal;">
                                Đoàn trường THPT Lý Thường Kiệt
                            </h3>
                            <h3
                                style="margin:4px 0;color:#166534;font-size:12px;letter-spacing:1px;text-transform:uppercase;font-weight:normal;">
                                CLB Kỹ năng Đoàn - Hội Trường THPT Lý Thường Kiệt
                            </h3>
                            <h2 class="title-h2"
                                style="margin:10px 0;color:#15803d;font-size:22px;font-weight:bold;line-height:1.3;text-transform:uppercase;">
                                {$event['title']}
                            </h2>
                        </td>
                    </tr>

                    <!-- BODY CONTENT -->
                    <tr>
                        <td class="content-cell body-text"
                            style="padding:0 35px 20px 35px;line-height:1.7;color:#334155;font-size:15px;">
                            <p style="font-size:16px;color:#1e293b;">Kính chào <b>{$fullName}</b>,</p>

                            <div class="alert-box"
                                style="text-align:center;margin:25px 0;padding:22px;background-color:#dcfce7;border-radius:12px;border:2px solid #22c55e;">
                                <h3
                                    style="margin:0;color:#15803d;font-size:16px;line-height:1.5;text-transform:uppercase;">
                                    XÁC NHẬN ĐIỂM DANH THÀNH CÔNG!
                                </h3>
                                <p style="margin:10px 0 0 0;font-size:14px;color:#166534;">
                                    Mã số thẻ: <b>{$camper['student_code']}</b>
                                </p>
                            </div>

                            <p>
                                Ban Chủ nhiệm trân trọng thông báo: bạn đã điểm danh thành công cho sự kiện <b
                                    style="color:#15803d;">{$event['title']}</b> vào ngày <b>{$currentDate}</b>.
                            </p>
                            <p>
                                Bạn có thể sử dụng mã số thẻ này để điểm danh nhanh vào lần sau!
                            </p>
                            <p>
                                Chúc bạn có một trải nghiệm thật năng lượng, bổ ích và tạo ra những kỷ niệm tuyệt vời
                                cùng CLB!
                            </p>
                        </td>
                    </tr>

                    <!-- NOTE -->
                    <tr>
                        <td class="note-cell" style="padding:0 35px 30px 35px;border-top:1px solid #e2e8f0;">
                            <p style="font-weight:bold;color:#1e293b;margin:25px 0 10px 0;">📌 LƯU Ý KHI THAM GIA:</p>
                            <ul style="padding-left:20px;margin:0;color:#475569;font-size:14px;line-height:1.6;">
                                <li style="margin-bottom:5px;">Tuân thủ tuyệt đối sự điều phối của Ban Chủ nhiệm / Ban
                                    Tổ chức trong suốt quá trình diễn ra sự kiện.</li>
                                <li style="margin-bottom:5px;">Tham gia đầy đủ và tích cực các hoạt động để có những
                                    trải nghiệm trọn vẹn nhất.</li>
                                <li>Giữ gìn vệ sinh chung tại khu vực sinh hoạt.</li>
                            </ul>
                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td align="center" class="footer-cell"
                            style="padding:30px;background-color:#fcfcfc;color:#64748b;font-size:12px;line-height:1.6;border-top:1px solid #e2e8f0;">
                            <p style="margin-bottom:10px;">Trân trọng./.</p>
                            <p style="margin:0;font-weight:bold;color:#334155;text-transform:uppercase;">
                                Đoàn trường THPT Lý Thường Kiệt
                            </p>
                            <p style="margin:0;font-weight:bold;color:#475569;text-transform:uppercase;">
                                CLB Kỹ năng Đoàn - Hội Trường THPT Lý Thường Kiệt
                            </p>
                            <p style="margin:4px 0;color:#15803d;font-weight:bold;">
                                Ban Chủ nhiệm CLB
                            </p>
                            <div style="margin-top:15px;padding-top:15px;border-top:1px solid #e2e8f0;">
                                <p style="margin:0;">📞 +84 352 006 062 (Đỗ Huy Hoàng | Nhân sự) | 📧
                                    clbkynangdoan.ltk@gmail.com</p>
                                <p style="margin:2px 0;">Văn phòng Đoàn trường THPT Lý Thường Kiệt</p>
                                <p style="margin:0;">609 Thống Nhất, Phường La Gi, tỉnh Lâm Đồng</p>
                            </div>
                            <div
                                style="margin-top:15px;padding-top:15px;border-top:1px dashed #cbd5e1;font-size:11px;color:#94a3b8;font-style:italic;">
                                Đây là email tự động từ hệ thống quản lý của Ban Chủ nhiệm CLB nên vui lòng không reply.
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</body>

</html>
HTML;

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
    error_log('Student Check-in API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.']);
}
