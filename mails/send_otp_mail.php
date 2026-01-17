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
      <body style="margin:0; padding:0; background:#f5f7fa; font-family:'Segoe UI', Arial, sans-serif; color:#333;">
        <div style="max-width:520px; margin:40px auto; background:#fff; border-radius:16px; box-shadow:0 4px 25px rgba(0,0,0,0.08); overflow:hidden;">
    
          <!-- Header -->
          <div style="background:linear-gradient(135deg, #007bff, #00b4d8); padding:28px 0; text-align:center;">
            <img src="https://i.postimg.cc/QMDf0WK9/Thi-t-k-ch-a-c-t-n-6.png" alt="Logo CLB" style="width:80px; margin-bottom:8px;">
            <div style="font-size:22px; color:#fff; font-weight:600;">CLB KỸ NĂNG - THPT LÝ THƯỜNG KIỆT</div>
          </div>
    
          <!-- Body -->
          <div style="padding:32px 28px;">
            <h2 style="text-align:center; color:#e74c3c; font-size:22px; margin-bottom:12px;">Xác thực đổi mật khẩu</h2>
            
            <p style="font-size:16px; text-align:center; margin-bottom:20px;">
              Xin chào <b style="color:#007bff;">'.$display_name.'</b>,<br>
              Bạn vừa yêu cầu <b>đổi mật khẩu đăng nhập</b> cho tài khoản.<br>
              Vui lòng nhập mã OTP bên dưới để xác nhận yêu cầu.
            </p>
    
            <div style="background:#fef6f6; border:2px dashed #e74c3c; border-radius:10px; padding:20px; text-align:center; margin:20px 0;">
              <div style="font-size:40px; font-weight:700; letter-spacing:14px; color:#e74c3c;">
                '.implode(" ", str_split($otp)).'
              </div>
            </div>
    
            <p style="font-size:15px; text-align:center; color:#555; margin-bottom:14px;">
              Mã OTP có hiệu lực trong <b style="color:#007bff;">5 phút</b>.<br>
              <span style="color:#e74c3c; font-weight:600;">KHÔNG chia sẻ mã này với bất kỳ ai.</span>
            </p>
    
            <p style="font-size:14px; text-align:center; color:#888; margin-bottom:20px;">
              Nếu bạn không thực hiện yêu cầu này, vui lòng bỏ qua email này.
            </p>
          </div>
    
          <!-- Footer -->
          <div style="background:#f8f9fa; border-top:1px solid #eee; text-align:center; padding:16px 10px; font-size:13px; color:#777;">
            <p style="margin:0;">Đây là email tự động, vui lòng không trả lời.</p>
            <p style="margin-top:6px; color:#555;"><b>Ban chủ nhiệm CLB Kỹ năng</b></p>
          </div>
        </div>
      </body>
    </html>

    ';

    $mail = new PHPMailer(true);
    try {
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = 'mail.clbkynangdoanhoiltk.io.vn';
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@clbkynangdoanhoiltk.io.vn';
        $mail->Password = 'Giang15052006@'; // ĐỔI thành mật khẩu thật
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;

        $mail->setFrom('no-reply@clbkynangdoanhoiltk.io.vn', 'CLB Kỹ năng Đoàn Hội Trường THPT Lý Thường Kiệt');
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