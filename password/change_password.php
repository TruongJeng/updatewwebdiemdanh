<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once '../includes/db.php';
require_once __DIR__ . '/../mails/send_otp_mail.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); exit();
}

$error = '';
$msg = '';
$show_otp_section = true;
$show_back_btn = false;
$otp_cooldown = 60;

// Lấy thông tin user (lấy cả tên để gửi vào mail)
$stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$user_email = $user ? $user['email'] : "";
$user_name = $user ? $user['full_name'] : "";

// Nếu không có email
if (empty($user_email)) {
    $error = "Tài khoản của bạn chưa có email. Vui lòng liên hệ quản trị viên để được cập nhật email.";
    $show_otp_section = false;
    $show_back_btn = true;
} else {
    // XỬ LÝ GỬI LẠI OTP (NẾU CÓ)
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['resend_otp']) && $_POST['resend_otp'] == '1') {
        $last_otp_time = $_SESSION['last_otp_time'] ?? 0;
        if (time() - $last_otp_time < $otp_cooldown) {
            $error = "Vui lòng đợi ".($otp_cooldown - (time()-$last_otp_time))." giây nữa để gửi lại OTP.";
        } else {
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION['otp_code'] = $otp;
            $_SESSION['otp_expire'] = time() + 300; // 5 phút
            $_SESSION['otp_sent'] = 1;
            $_SESSION['last_otp_time'] = time();
            if (send_otp_mail($user_email, $otp, $user_name)) {
                $msg = "Mã OTP mới đã được gửi lại email của bạn!";
            } else {
                $error = "Không gửi được email OTP. Vui lòng thử lại hoặc liên hệ quản trị viên.";
            }
        }
    }
    // GỬI OTP LẦN ĐẦU KHI VÀO TRANG
    elseif (!isset($_SESSION['otp_sent'])) {
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['otp_code'] = $otp;
        $_SESSION['otp_expire'] = time() + 300; // 5 phút
        $_SESSION['otp_sent'] = 1;
        $_SESSION['last_otp_time'] = time();
        send_otp_mail($user_email, $otp, $user_name);
    }
}

// XỬ LÝ ĐỔI MẬT KHẨU (CHỈ KHI KHÔNG PHẢI resend_otp)
if (
    $show_otp_section &&
    $_SERVER['REQUEST_METHOD'] == 'POST' &&
    (!isset($_POST['resend_otp']) || $_POST['resend_otp'] != '1')
) {
    $otp_user = $_POST['otp'] ?? '';
    $newpass = $_POST['newpass'] ?? '';
    $renewpass = $_POST['renewpass'] ?? '';
    if (!$otp_user) {
        $error = "Vui lòng nhập mã OTP!";
    } elseif (!isset($_SESSION['otp_code'], $_SESSION['otp_expire']) || time() > $_SESSION['otp_expire']) {
        $error = "Mã OTP đã hết hạn, vui lòng nhấn Gửi lại mã OTP.";
        unset($_SESSION['otp_sent'], $_SESSION['otp_code'], $_SESSION['otp_expire'], $_SESSION['last_otp_time']);
    } elseif ($otp_user != $_SESSION['otp_code']) {
        $error = "Mã OTP không đúng!";
    } elseif (!$newpass || !$renewpass) {
        $error = "Vui lòng nhập đầy đủ thông tin!";
    } elseif ($newpass !== $renewpass) {
        $error = "Mật khẩu nhập lại không khớp!";
    } elseif (strlen($newpass) < 5) {
        $error = "Mật khẩu phải từ 5 ký tự!";
    } else {
        $hash = password_hash($newpass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash=?, first_login=0 WHERE id=?");
        $stmt->execute([$hash, $_SESSION['user_id']]);
        $_SESSION['first_login'] = 0;
        unset($_SESSION['otp_sent'], $_SESSION['otp_code'], $_SESSION['otp_expire'], $_SESSION['last_otp_time']);
        $msg = "Đổi mật khẩu thành công! Bạn sẽ được chuyển về trang chính.";
        header("refresh:2;url=/logout.php");
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>CLB Kỹ năng Đoàn - Hội Trường THPT Lý Thường Kiệt</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/assets/logo_CLB.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f8fafc; }
        .otp-card {
            max-width: 410px;
            margin:60px auto 0 auto;
            border-radius: 14px;
            box-shadow:0 4px 24px #e7e7e7;
            border: none;
        }
        .otp-logo {
            width:62px; margin:0 auto 12px auto; display:block;
        }
        .otp-form-title {
            font-weight:700; color:#e74c3c; margin-bottom:8px; text-align:center; font-size:1.45rem;
        }
        .otp-info {
            color:#3178c6; font-size:1.02rem; background:#eaf2fb; border-radius:7px;
            padding:8px 12px; margin-bottom:18px; text-align:center;
        }
        .resend-link {
            font-size: 0.98rem;
            color: #0d6efd;
            padding: 0;
            background: none;
            border: none;
            text-decoration: underline;
            cursor: pointer;
            margin-top: 6px;
            margin-left: 0;
            display: inline;
        }
        .resend-link[disabled] {
            color: #999;
            text-decoration: none;
            pointer-events: none;
            cursor: not-allowed;
        }
        .countdown {
            font-size: 0.97rem; color: #888; margin-left:8px;
        }
    </style>
</head>
<body>
<?php
$pageTitle = "ĐỔI MẬT KHẨU";
$full_name = $_SESSION['full_name'] ?? '';
include '../includes/header.php';
?>
    <div class="container">
        <div class="card otp-card">
            <div class="card-body">
                <img src="/assets/logo_CLB.png" class="otp-logo" alt="CLB Kỹ năng">
                <div class="otp-form-title">Đổi mật khẩu</div>
                <?php if($show_otp_section): ?>
                <div class="otp-info" id="otpInfo">
                    Mã OTP đã gửi về email của bạn.<br>
                    <b>Vui lòng kiểm tra cả hộp thư rác nếu không thấy!</b>
                </div>
                <?php endif; ?>
                <?php if(!empty($error)): ?>
                    <div class="alert alert-danger"><?=htmlspecialchars($error)?></div>
                <?php endif; ?>
                <?php if(!empty($msg)): ?>
                    <div class="alert alert-success"><?=htmlspecialchars($msg)?></div>
                <?php endif; ?>

                <?php if($show_otp_section): ?>
                <form method="post" autocomplete="off" id="otpForm">
                    <input type="hidden" name="resend_otp" id="resend_otp_field" value="">
                    <div class="mb-3">
                        <label class="form-label">Mã OTP</label>
                        <input type="text" name="otp" class="form-control" maxlength="6" pattern="[0-9]{6}" autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mật khẩu mới</label>
                        <input type="password" name="newpass" class="form-control" required minlength="5">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nhập lại mật khẩu mới</label>
                        <input type="password" name="renewpass" class="form-control" required minlength="5">
                        <div class="d-flex align-items-center mt-2">
                            <button type="button" id="resendBtn" value="1" class="resend-link" style="padding-left:0;" onclick="resendOTP()">Gửi lại mã OTP</button>
                            <span class="countdown" id="countdown"></span>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Đổi mật khẩu</button>
                </form>
                <?php endif; ?>

                <?php if($show_back_btn): ?>
                    <a href="/index.php" class="btn btn-secondary w-100 mt-2">Quay lại</a>
                <?php endif; ?>
            </div>
        </div>
        <?php include '../includes/footer.php'; ?>
    </div>
<script>
    // Ẩn thông báo OTP gửi về email sau 5s
    document.addEventListener("DOMContentLoaded", function() {
        var otpInfo = document.getElementById('otpInfo');
        if (otpInfo) {
            setTimeout(function() {
                otpInfo.style.display = 'none';
            }, 5000);
        }
    });

    // Xử lý đếm ngược gửi lại OTP
    <?php if($show_otp_section): ?>
    let cooldown = <?= isset($_SESSION['last_otp_time']) ? max(0, $otp_cooldown - (time() - $_SESSION['last_otp_time'])) : 0 ?>;
    const resendBtn = document.getElementById('resendBtn');
    const countdown = document.getElementById('countdown');
    function updateCountdown() {
        if (cooldown > 0) {
            resendBtn.disabled = true;
            countdown.textContent = `(Gửi lại sau ${cooldown}s)`;
            cooldown--;
            setTimeout(updateCountdown, 1000);
        } else {
            resendBtn.disabled = false;
            countdown.textContent = '';
        }
    }
    if (resendBtn) updateCountdown();

    // Xử lý gửi lại OTP bằng JS, không cần nhập mã OTP
    function resendOTP() {
        document.getElementById('resend_otp_field').value = '1';
        document.getElementById('otpForm').submit();
    }
    <?php endif; ?>
</script>
</body>
</html>