<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
session_start();
require_once '../includes/db.php';
require_once __DIR__ . '/../mails/send_otp_mail.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); exit();
}
if (isset($_SESSION['first_login']) && $_SESSION['first_login'] == 0) {
    header("Location: dashboard.php"); exit();
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
?>

<?php
$pageTitle = "Đổi mật khẩu lần đầu";
$full_name = $_SESSION['full_name'] ?? '';
include '../includes/header.php';
?>

<main class="min-h-screen bg-slate-50/50 flex flex-col pt-14 sm:pt-16 pb-12 transition-all duration-300">
    <div class="flex-grow flex items-center justify-center p-4 sm:p-6 w-full">
        <div class="w-full max-w-md">
            
            <!-- Thông báo Lỗi / Thành công -->
            <?php if(!empty($error)): ?>
            <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-r-lg shadow-sm">
                <div class="flex items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill text-lg"></i>
                    <span class="font-medium text-sm"><?=htmlspecialchars($error)?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if(!empty($msg)): ?>
            <div class="mb-6 p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 rounded-r-lg shadow-sm">
                <div class="flex items-center gap-2">
                    <i class="bi bi-check-circle-fill text-lg"></i>
                    <span class="font-medium text-sm"><?=htmlspecialchars($msg)?></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Card -->
            <div class="bg-white/80 backdrop-blur-xl rounded-[2rem] p-8 shadow-[0_30px_60px_-15px_rgba(28,150,101,0.15)] border border-white relative z-10">
                
                <div class="text-center mb-8">
                    <div class="w-16 h-16 bg-primary-100 rounded-2xl flex items-center justify-center mx-auto mb-4 text-primary-600 shadow-inner">
                        <img src="/hethongdiemdanh/assets/logo_CLB.png" class="w-10 h-10 object-contain drop-shadow-sm" alt="Logo">
                    </div>
                    <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">Đổi mật khẩu lần đầu</h2>
                    <p class="text-sm text-slate-500 mt-2 font-medium">Tạo mật khẩu mới để bảo mật tài khoản của bạn</p>
                </div>

                <?php if($show_otp_section): ?>
                
                <div class="mb-6 p-3 bg-primary-50/80 border border-primary-100 text-primary-700 rounded-xl text-sm text-center" id="otpInfo">
                    <i class="bi bi-envelope-check-fill mr-1"></i> Mã OTP đã gửi về email của bạn.<br>
                    <b class="text-xs">Vui lòng kiểm tra cả hộp thư rác (Spam) nếu không thấy!</b>
                </div>

                <form method="post" autocomplete="off" id="otpForm" class="space-y-5">
                    <input type="hidden" name="resend_otp" id="resend_otp_field" value="">
                    
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1.5">Mã OTP</label>
                        <input type="text" name="otp" class="w-full text-center text-xl tracking-[0.3em] font-black py-3 px-4 bg-slate-50 border-2 border-slate-200 rounded-xl text-slate-800 focus:border-primary-500 focus:ring-4 focus:ring-primary-500/20 outline-none transition-all placeholder:text-slate-300 placeholder:tracking-normal placeholder:font-medium placeholder:text-sm" maxlength="6" pattern="[0-9]{6}" placeholder="••••••" autofocus required>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1.5">Mật khẩu mới</label>
                        <input type="password" name="newpass" class="w-full bg-slate-50 border-2 border-slate-200 rounded-xl px-4 py-3 text-slate-800 outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-500/20 transition-all font-medium text-sm" placeholder="Nhập mật khẩu mới" required minlength="5">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1.5">Nhập lại mật khẩu mới</label>
                        <input type="password" name="renewpass" class="w-full bg-slate-50 border-2 border-slate-200 rounded-xl px-4 py-3 text-slate-800 outline-none focus:border-primary-500 focus:ring-4 focus:ring-primary-500/20 transition-all font-medium text-sm" placeholder="Nhập lại mật khẩu" required minlength="5">
                        
                        <div class="flex items-center justify-end mt-3 text-sm">
                            <button type="button" id="resendBtn" class="font-bold text-primary-600 hover:text-primary-700 disabled:text-slate-400 disabled:cursor-not-allowed transition-colors" onclick="resendOTP()">Gửi lại mã OTP</button>
                            <span class="text-slate-400 font-medium ml-1" id="countdown"></span>
                        </div>
                    </div>

                    <button type="submit" class="w-full flex justify-center items-center gap-2 bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white py-3.5 rounded-xl font-bold transition-all shadow-lg shadow-primary-500/30 hover:shadow-primary-500/50 hover:-translate-y-0.5 mt-2">
                        <i class="bi bi-shield-check"></i> Xác nhận & Đổi mật khẩu
                    </button>
                </form>
                <?php endif; ?>

                <?php if($show_back_btn): ?>
                    <a href="/hethongdiemdanh/index.php" class="flex justify-center items-center gap-2 w-full bg-slate-100 hover:bg-slate-200 text-slate-700 py-3.5 rounded-xl font-bold transition-all border border-slate-200 mt-4">
                        <i class="bi bi-arrow-left"></i> Quay lại đăng nhập
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</main>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        var otpInfo = document.getElementById('otpInfo');
        if (otpInfo) {
            setTimeout(function() {
                otpInfo.style.opacity = '0';
                otpInfo.style.transition = 'opacity 0.5s ease';
                setTimeout(() => otpInfo.style.display = 'none', 500);
            }, 8000);
        }
    });

    <?php if($show_otp_section): ?>
    let cooldown = <?= isset($_SESSION['last_otp_time']) ? max(0, $otp_cooldown - (time() - $_SESSION['last_otp_time'])) : 0 ?>;
    const resendBtn = document.getElementById('resendBtn');
    const countdown = document.getElementById('countdown');
    
    function updateCountdown() {
        if (cooldown > 0) {
            resendBtn.disabled = true;
            countdown.textContent = `(${cooldown}s)`;
            cooldown--;
            setTimeout(updateCountdown, 1000);
        } else {
            resendBtn.disabled = false;
            countdown.textContent = '';
        }
    }
    if (resendBtn) updateCountdown();

    function resendOTP() {
        if(resendBtn.disabled) return;
        document.getElementById('resend_otp_field').value = '1';
        document.getElementById('otpForm').submit();
    }
    <?php endif; ?>
</script>