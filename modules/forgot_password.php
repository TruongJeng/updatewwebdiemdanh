<?php
// Cấu hình email admin nhận yêu cầu quên mật khẩu
$admin_email = 'admin@yourdomain.com';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $role = $_POST['role'] ?? '';

    if ($full_name && $username && $role) {
        // Chuẩn bị nội dung email
        $subject = "Yêu cầu quên mật khẩu từ hệ thống CLB";
        $roles = [
            'admin' => 'Quản trị viên',
            'teacher' => 'Giáo viên/Giảng viên',
            'club_leader' => 'Ban chủ nhiệm',
            'student' => 'Học sinh'
        ];
        $role_label = $roles[$role] ?? $role;
        $body = "Có yêu cầu lấy lại mật khẩu:\n"
              . "Họ và tên: $full_name\n"
              . "Tên đăng nhập: $username\n"
              . "Chức vụ: $role_label\n"
              . "Thời gian: " . date('d/m/Y H:i');
        $headers = "From: no-reply@yourdomain.com\r\n";
        // Gửi mail (hoặc lưu file nếu server chưa cấu hình mail)
        if (mail($admin_email, $subject, $body, $headers)) {
            $success = "Đã gửi yêu cầu thành công! Vui lòng chờ quản trị viên liên hệ lại.";
        } else {
            // Nếu thử nghiệm trên localhost chưa gửi được mail, bạn có thể hiển thị thông tin này:
            $success = "Yêu cầu đã được ghi nhận (demo)!<br>Nội dung gửi:<br>" . nl2br(htmlspecialchars($body));
        }
    } else {
        $error = "Vui lòng điền đầy đủ thông tin!";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Quên mật khẩu - Hệ thống điểm danh</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Tailwind CSS (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { primary: { 50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd', 400: '#60a5fa', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8', 800: '#1e40af', 900: '#1e3a8a' } }
                }
            }
        }
    </script>
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
</head>
<body class="font-sans min-h-screen flex items-center justify-center p-4 sm:p-6 bg-gradient-to-br from-slate-50 via-primary-50 to-primary-100 text-slate-800 relative overflow-hidden">
    
    <!-- Background Orbs -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute -top-40 -left-40 w-[500px] h-[500px] rounded-full bg-gradient-to-br from-primary-200 to-primary-400 opacity-30 blur-[80px]"></div>
        <div class="absolute -bottom-60 -right-40 w-[600px] h-[600px] rounded-full bg-gradient-to-br from-primary-300 to-primary-600 opacity-20 blur-[80px]"></div>
    </div>

    <div class="w-full max-w-md bg-white/80 backdrop-blur-xl rounded-[2rem] p-8 shadow-[0_30px_60px_-15px_rgba(37,99,235,0.15)] border border-white relative z-10 animate-[fadeInUp_0.4s_ease-out]">
        
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-primary-100 text-primary-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-inner">
                <i class="bi bi-question-circle-fill text-3xl"></i>
            </div>
            <h2 class="text-2xl font-black text-slate-800 tracking-tight">Quên Mật Khẩu</h2>
            <p class="text-sm font-medium text-slate-500 mt-1">Gửi yêu cầu cấp lại mật khẩu tới Admin</p>
        </div>

        <?php if ($success): ?>
            <div class="flex items-start gap-3 p-4 mb-6 text-emerald-800 bg-emerald-50 border-l-4 border-emerald-500 rounded-r-lg">
                <i class="bi bi-check-circle-fill text-xl mt-0.5"></i>
                <div class="font-medium text-sm"><?= $success ?></div>
            </div>
        <?php elseif ($error): ?>
            <div class="flex items-center gap-3 p-4 mb-6 text-red-800 bg-red-50 border-l-4 border-red-500 rounded-r-lg">
                <i class="bi bi-x-circle-fill text-xl"></i>
                <span class="font-medium text-sm"><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if(!$success): ?>
        <form method="post" autocomplete="off" class="space-y-5">
            <!-- Full Name -->
            <div class="relative group">
                <label for="full_name" class="block text-sm font-semibold text-slate-700 mb-2">Họ và tên</label>
                <div class="relative">
                    <i class="bi bi-person absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg transition-colors group-focus-within:text-primary-500"></i>
                    <input type="text" id="full_name" name="full_name" required
                           class="w-full h-12 pl-12 pr-4 bg-white/50 border-2 border-slate-200 rounded-xl text-slate-800 font-medium focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 outline-none transition-all">
                </div>
            </div>

            <!-- Username -->
            <div class="relative group">
                <label for="username" class="block text-sm font-semibold text-slate-700 mb-2">Tên đăng nhập</label>
                <div class="relative">
                    <i class="bi bi-person-badge absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg transition-colors group-focus-within:text-primary-500"></i>
                    <input type="text" id="username" name="username" required
                           class="w-full h-12 pl-12 pr-4 bg-white/50 border-2 border-slate-200 rounded-xl text-slate-800 font-medium focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 outline-none transition-all">
                </div>
            </div>

            <!-- Role -->
            <div class="relative group">
                <label for="role" class="block text-sm font-semibold text-slate-700 mb-2">Chức vụ</label>
                <div class="relative">
                    <i class="bi bi-briefcase absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg z-10 transition-colors group-focus-within:text-primary-500"></i>
                    <select id="role" name="role" required class="appearance-none w-full h-12 pl-12 pr-10 bg-white/50 border-2 border-slate-200 rounded-xl text-slate-800 font-medium focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 outline-none transition-all cursor-pointer">
                        <option value="">-- Chọn chức vụ --</option>
                        <option value="admin">Quản trị viên</option>
                        <option value="teacher">Giáo viên/Giảng viên</option>
                        <option value="club_leader">Ban chủ nhiệm</option>
                        <option value="student">Học sinh</option>
                    </select>
                    <i class="bi bi-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                </div>
            </div>

            <button type="submit" class="w-full h-12 mt-4 bg-gradient-to-r from-primary-600 to-primary-500 hover:from-primary-700 hover:to-primary-600 text-white font-bold text-base rounded-xl shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2">
                <i class="bi bi-send-fill"></i> Gửi Yêu Cầu
            </button>
        </form>
        <?php endif; ?>

        <div class="mt-6 text-center">
            <a href="../index.php" class="text-sm font-semibold text-primary-600 hover:text-primary-700 transition-colors inline-flex items-center gap-1.5">
                <i class="bi bi-arrow-left"></i> Quay lại Đăng nhập
            </a>
        </div>
        
    </div>

    <style>
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</body>
</html></select>
        </div>
        <button class="btn btn-forgot w-100 mt-2" type="submit">Gửi yêu cầu</button>
        <a href="../index.php" class="back-link">Về trang đăng nhập</a>
    </form>
</body>
</html>