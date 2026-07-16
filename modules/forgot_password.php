<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/csrf.php';

$admin_email = 'admin@yourdomain.com';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Rate limiting: tối đa 3 lần gửi trong 10 phút
    $max_attempts = 3;
    $lockout_time = 600; // 10 phút
    if (!isset($_SESSION['forgot_pwd_attempts'])) $_SESSION['forgot_pwd_attempts'] = 0;
    if (!isset($_SESSION['forgot_pwd_lockout'])) $_SESSION['forgot_pwd_lockout'] = 0;

    if ($_SESSION['forgot_pwd_attempts'] >= $max_attempts && (time() - $_SESSION['forgot_pwd_lockout']) < $lockout_time) {
        $remaining = $lockout_time - (time() - $_SESSION['forgot_pwd_lockout']);
        $error = "Quá nhiều lần gửi yêu cầu. Vui lòng đợi " . ceil($remaining / 60) . " phút.";
    } else {
        if ($_SESSION['forgot_pwd_attempts'] >= $max_attempts) {
            $_SESSION['forgot_pwd_attempts'] = 0;
        }

        $full_name = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $role      = $_POST['role'] ?? '';

    if ($full_name && $username && $role) {
        $roles = [
            'admin'        => 'Quản trị viên',
            'teacher'      => 'Giáo viên/Giảng viên',
            'club_leader'  => 'Ban chủ nhiệm',
            'student'      => 'Học sinh'
        ];
        $role_label = $roles[$role] ?? $role;
        $subject = "Yêu cầu quên mật khẩu từ hệ thống CLB";
        $body    = "Có yêu cầu lấy lại mật khẩu:\n"
                 . "Họ và tên: $full_name\n"
                 . "Tên đăng nhập: $username\n"
                 . "Chức vụ: $role_label\n"
                 . "Thời gian: " . date('d/m/Y H:i');
        $headers = "From: no-reply@yourdomain.com\r\n";

        if (mail($admin_email, $subject, $body, $headers)) {
            $_SESSION['forgot_pwd_attempts']++;
            $_SESSION['forgot_pwd_lockout'] = time();
            $success = "Đã gửi yêu cầu thành công! Vui lòng chờ quản trị viên liên hệ lại.";
        } else {
            $_SESSION['forgot_pwd_attempts']++;
            $_SESSION['forgot_pwd_lockout'] = time();
            $success = "Yêu cầu đã được ghi nhận!<br><small class='text-slate-500'>Nội dung: " . nl2br(htmlspecialchars($body)) . "</small>";
        }
    } else {
        $error = "Vui lòng điền đầy đủ thông tin!";
    }
}
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quên mật khẩu | CLB Kỹ năng Đoàn</title>
    <meta name="theme-color" content="#1c9665">
    <link rel="icon" type="image/png" href="/hethongdiemdanh/assets/logo_CLB.png">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        primary: {
                            50:  '#f0fdf8',
                            100: '#d4f7eb',
                            200: '#b0eed2',
                            300: '#7de0b8',
                            400: '#48ce99',
                            500: '#27b87e',
                            600: '#1c9665',
                            700: '#177850',
                            800: '#165f40',
                            900: '#144e35',
                        }
                    },
                    animation: {
                        'float': 'float 20s infinite alternate',
                        'float-reverse': 'float 25s infinite alternate-reverse',
                    },
                    keyframes: {
                        float: {
                            '0%': { transform: 'translate(0, 0) scale(1)' },
                            '100%': { transform: 'translate(5%, 5%) scale(1.1)' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { -webkit-tap-highlight-color: transparent; }
        * { touch-action: manipulation; }
    </style>
</head>
<body class="font-sans min-h-screen flex items-center justify-center p-5 relative overflow-hidden bg-gradient-to-br from-slate-50 via-primary-50 to-primary-100 text-slate-800 selection:bg-primary-200 selection:text-primary-900">

    <!-- Background Orbs -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute -top-40 -left-40 w-[500px] h-[500px] rounded-full bg-gradient-to-br from-primary-200 to-primary-400 opacity-30 blur-[80px] animate-float"></div>
        <div class="absolute -bottom-60 -right-40 w-[600px] h-[600px] rounded-full bg-gradient-to-br from-primary-300 to-primary-600 opacity-20 blur-[80px] animate-float-reverse"></div>
    </div>

    <!-- Main Card -->
    <main class="w-full max-w-sm relative z-10">
        <div class="bg-white/85 backdrop-blur-xl rounded-2xl p-7 shadow-[0_20px_50px_-15px_rgba(28,150,101,0.18)] border border-white/80 transition-all duration-300">

            <!-- Brand -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary-100 mb-4 shadow-inner">
                    <img src="/hethongdiemdanh/assets/logo_CLB.png" alt="Logo" class="w-12 h-12 object-contain rounded-xl">
                </div>
                <h1 class="text-xl font-extrabold text-slate-800 tracking-tight">Quên mật khẩu?</h1>
                <p class="text-xs text-slate-400 font-medium mt-1">Gửi yêu cầu cấp lại mật khẩu cho Admin</p>
            </div>

            <!-- Alerts -->
            <?php if ($success): ?>
            <div class="flex items-start gap-2 p-3 mb-5 text-emerald-800 bg-emerald-50 border-l-4 border-emerald-400 rounded-r-lg text-sm">
                <i class="bi bi-check-circle-fill flex-shrink-0 mt-0.5"></i>
                <div class="font-medium"><?= $success ?></div>
            </div>
            <?php elseif ($error): ?>
            <div class="flex items-center gap-2 p-3 mb-5 text-red-800 bg-red-50 border-l-4 border-red-400 rounded-r-lg text-sm">
                <i class="bi bi-x-circle-fill flex-shrink-0"></i>
                <span class="font-medium"><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <?php if (!$success): ?>
            <form method="post" autocomplete="off" class="space-y-4">
                <?= csrf_field() ?>

                <div class="relative group">
                    <i class="bi bi-person absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-base transition-colors group-focus-within:text-primary-500 pointer-events-none"></i>
                    <input type="text" name="full_name" required
                           placeholder="Họ và tên"
                           class="w-full h-11 pl-10 pr-4 bg-slate-50 border-2 border-slate-200 rounded-xl text-slate-800 text-sm font-medium placeholder:text-slate-400 placeholder:font-normal focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 outline-none transition-all">
                </div>

                <div class="relative group">
                    <i class="bi bi-person-badge absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-base transition-colors group-focus-within:text-primary-500 pointer-events-none"></i>
                    <input type="text" name="username" required
                           placeholder="Tên đăng nhập"
                           class="w-full h-11 pl-10 pr-4 bg-slate-50 border-2 border-slate-200 rounded-xl text-slate-800 text-sm font-medium placeholder:text-slate-400 placeholder:font-normal focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 outline-none transition-all">
                </div>

                <div class="relative group">
                    <i class="bi bi-briefcase absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-base z-10 transition-colors group-focus-within:text-primary-500 pointer-events-none"></i>
                    <select name="role" required
                            class="appearance-none w-full h-11 pl-10 pr-8 bg-slate-50 border-2 border-slate-200 rounded-xl text-slate-800 text-sm font-medium focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 outline-none transition-all cursor-pointer">
                        <option value="">-- Chọn chức vụ --</option>
                        <option value="admin">Quản trị viên</option>
                        <option value="teacher">Giáo viên / Giảng viên</option>
                        <option value="club_leader">Ban chủ nhiệm</option>
                        <option value="student">Học sinh</option>
                    </select>
                    <i class="bi bi-chevron-down absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                </div>

                <button type="submit"
                        class="w-full h-11 bg-gradient-to-r from-primary-600 to-primary-500 hover:from-primary-700 hover:to-primary-600 text-white font-bold rounded-xl shadow-[0_4px_14px_rgba(28,150,101,0.35)] hover:shadow-[0_6px_20px_rgba(28,150,101,0.45)] active:scale-[0.98] transition-all flex items-center justify-center gap-2">
                    <i class="bi bi-send-fill"></i> Gửi yêu cầu
                </button>
            </form>
            <?php endif; ?>

            <!-- Back link -->
            <div class="mt-5 pt-4 border-t border-slate-100 text-center">
                <a href="../index" class="text-xs font-semibold text-primary-600 hover:text-primary-700 transition-colors inline-flex items-center gap-1.5">
                    <i class="bi bi-arrow-left"></i> Quay lại đăng nhập
                </a>
            </div>
        </div>
    </main>

</body>
</html>