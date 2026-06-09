<?php
session_start();
require '../includes/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php"); exit();
}

$error = '';
$msg = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $newpass = $_POST['newpass'] ?? '';
    $renewpass = $_POST['renewpass'] ?? '';
    if (!$newpass || !$renewpass) {
        $error = "Vui lòng nhập đầy đủ thông tin!";
    } elseif ($newpass !== $renewpass) {
        $error = "Mật khẩu nhập lại không khớp!";
    } elseif (strlen($newpass) < 5) {
        $error = "Mật khẩu phải từ 5 ký tự!";
    } else {
        $hash = password_hash($newpass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?");
        $stmt->execute([$hash, $_SESSION['user_id']]);
        $msg = "Đổi mật khẩu thành công! Bạn sẽ được chuyển về trang chính.";
        header("refresh:2;url=../dashboard.php");
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Đổi mật khẩu</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
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
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="font-sans min-h-screen flex items-center justify-center p-4 sm:p-6 bg-gradient-to-br from-slate-50 via-primary-50 to-primary-100 text-slate-800 relative overflow-hidden" x-data="{ showPass1: false, showPass2: false }">
    
    <!-- Background Orbs -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute -top-40 -left-40 w-[500px] h-[500px] rounded-full bg-gradient-to-br from-primary-200 to-primary-400 opacity-30 blur-[80px]"></div>
        <div class="absolute -bottom-60 -right-40 w-[600px] h-[600px] rounded-full bg-gradient-to-br from-primary-300 to-primary-600 opacity-20 blur-[80px]"></div>
    </div>

    <div class="w-full max-w-md bg-white/80 backdrop-blur-xl rounded-[2rem] p-8 shadow-[0_30px_60px_-15px_rgba(37,99,235,0.15)] border border-white relative z-10 animate-[fadeInUp_0.4s_ease-out]">
        
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-primary-100 text-primary-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-inner">
                <i class="bi bi-shield-lock-fill text-3xl"></i>
            </div>
            <h2 class="text-2xl font-black text-slate-800 tracking-tight">Đổi Mật Khẩu</h2>
            <p class="text-sm font-medium text-slate-500 mt-1">Vui lòng cập nhật mật khẩu mới của bạn</p>
        </div>

        <?php if($error): ?>
            <div class="flex items-center gap-3 p-4 mb-6 text-red-800 bg-red-50 border-l-4 border-red-500 rounded-r-lg">
                <i class="bi bi-x-circle-fill text-xl"></i>
                <span class="font-medium text-sm"><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if($msg): ?>
            <div class="flex items-center gap-3 p-4 mb-6 text-emerald-800 bg-emerald-50 border-l-4 border-emerald-500 rounded-r-lg">
                <i class="bi bi-check-circle-fill text-xl"></i>
                <span class="font-medium text-sm"><?= htmlspecialchars($msg) ?></span>
            </div>
            <div class="flex justify-center mt-4">
                <div class="w-6 h-6 border-2 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
            </div>
        <?php endif; ?>

        <?php if(!$msg): ?>
        <form method="post" class="space-y-5">
            <!-- New Password -->
            <div class="relative group">
                <label class="block text-sm font-semibold text-slate-700 mb-2">Mật khẩu mới</label>
                <div class="relative">
                    <i class="bi bi-key absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg transition-colors group-focus-within:text-primary-500"></i>
                    <input :type="showPass1 ? 'text' : 'password'" name="newpass" required minlength="5"
                           class="w-full h-12 pl-12 pr-12 bg-white/50 border-2 border-slate-200 rounded-xl text-slate-800 font-medium focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 outline-none transition-all">
                    <button type="button" @click="showPass1 = !showPass1" class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-10 flex items-center justify-center text-slate-400 hover:text-primary-600 focus:outline-none">
                        <i class="bi text-lg" :class="showPass1 ? 'bi-eye' : 'bi-eye-slash'"></i>
                    </button>
                </div>
            </div>
            
            <!-- Retype Password -->
            <div class="relative group">
                <label class="block text-sm font-semibold text-slate-700 mb-2">Nhập lại mật khẩu mới</label>
                <div class="relative">
                    <i class="bi bi-key-fill absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg transition-colors group-focus-within:text-primary-500"></i>
                    <input :type="showPass2 ? 'text' : 'password'" name="renewpass" required minlength="5"
                           class="w-full h-12 pl-12 pr-12 bg-white/50 border-2 border-slate-200 rounded-xl text-slate-800 font-medium focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 outline-none transition-all">
                    <button type="button" @click="showPass2 = !showPass2" class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-10 flex items-center justify-center text-slate-400 hover:text-primary-600 focus:outline-none">
                        <i class="bi text-lg" :class="showPass2 ? 'bi-eye' : 'bi-eye-slash'"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="w-full h-12 mt-4 bg-gradient-to-r from-primary-600 to-primary-500 hover:from-primary-700 hover:to-primary-600 text-white font-bold text-base rounded-xl shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2">
                Cập nhật mật khẩu
            </button>
        </form>
        <?php endif; ?>
        
    </div>

    <style>
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</body>
</html>