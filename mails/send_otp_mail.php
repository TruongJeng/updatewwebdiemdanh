<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';

/**
 * @param string $to      Email người nhận
 * @param string $otp     Mã OTP
 * @param string $name    Tên người nhận (nếu có, sẽ hiển thị ở "Xin chào")
 */
function send_otp_mail($to, $otp, $name = '') {
    $subject = "Mã OTP xác thực đổi mật khẩu";
    $display_name = $name ? htmlspecialchars($name) : htmlspecialchars($to);
    $message = '
    <html>
      <head>
        <meta charset="UTF-8">
        <title>Xác thực OTP - CLB Kỹ năng</title>
      </head>
      <body style="margin:0; padding:0; background:#f5f7fa; font-family:\'Segoe UI\', Arial, sans-serif; color:#333;">
        <div style="max-width:520px; margin:40px auto; background:#fff; border-radius:16px; box-shadow:0 4px 25px rgba(0,0,0,0.08); overflow:hidden;">
    
          <!-- Header -->
          <div style="background:linear-gradient(135deg, #1d4ed8, #3b82f6); padding:28px 0; text-align:center;">
            <img src="https://clbkynangdoanhoiltk.io.vn/hethongdiemdanh/assets/logo_CLB.png" alt="Logo CLB" style="width:80px; height:80px; border-radius:12px; margin-bottom:12px; object-fit:cover; background:#fff; padding:4px;">
            <div style="font-size:22px; color:#fff; font-weight:800; letter-spacing:0.5px;">CLB KỸ NĂNG - THPT LÝ THƯỜNG KIỆT</div>
          </div>
    
          <!-- Body -->
          <div style="padding:32px 28px;">
            <h2 style="text-align:center; color:#1e40af; font-size:22px; font-weight:800; margin-bottom:12px;">Xác thực đổi mật khẩu</h2>
            
            <p style="font-size:16px; text-align:center; margin-bottom:20px;">
              Xin chào <b style="color:#2563eb;">'.$display_name.'</b>,<br>
              Bạn vừa yêu cầu <b>đổi mật khẩu đăng nhập</b> cho tài khoản.<br>
              Vui lòng nhập mã OTP bên dưới để xác nhận yêu cầu.
            </p>
    
            <div style="background:#eff6ff; border:2px dashed #3b82f6; border-radius:12px; padding:20px; text-align:center; margin:24px 0;">
              <div style="font-size:42px; font-weight:900; letter-spacing:16px; color:#1d4ed8; text-shadow: 0 2px 4px rgba(37,99,235,0.1);">
                '.implode(" ", str_split($otp)).'
              </div>
            </div>
    
            <p style="font-size:15px; text-align:center; color:#475569; margin-bottom:14px;">
              Mã OTP có hiệu lực trong <b style="color:#2563eb;">5 phút</b>.<br>
              <span style="color:#ef4444; font-weight:600;">KHÔNG chia sẻ mã này với bất kỳ ai.</span>
            </p>
    
            <p style="font-size:14px; text-align:center; color:#94a3b8; margin-bottom:20px;">
              Nếu bạn không thực hiện yêu cầu này, vui lòng bỏ qua email này.
            </p>
          </div>
    
          <!-- Footer -->
          <div style="background:#f8fafc; border-top:1px solid #e2e8f0; text-align:center; padding:20px 10px; font-size:13px; color:#64748b;">
            <p style="margin:0;">Đây là email tự động, vui lòng không trả lời.</p>
            <p style="margin-top:6px; color:#475569;"><b>Ban chủ nhiệm CLB Kỹ năng Đoàn - Hội</b></p>
          </div>
        </div>
      </body>
    </html>

    ';

    static $env = null;
    if ($env === null) {
        $env = require __DIR__ . '/../config/env.php';
    }
    
    $mail = new PHPMailer(true);
    try {
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = $env['mail']['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $env['mail']['username'];
        $mail->Password = $env['mail']['password'];
        $mail->SMTPSecure = $env['mail']['secure'];
        $mail->Port = $env['mail']['port'];

        $mail->setFrom($env['mail']['from_email'], $env['mail']['from_name']);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;

        $mail->send();
        return true;
    } catch (Exception $e) {
        echo "Không gửi được mail. Lỗi: " . $mail->ErrorInfo;
        return false;
    }
}
?>