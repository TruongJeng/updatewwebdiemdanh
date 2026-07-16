<?php
require_once __DIR__ . '/config/session.php';
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

require 'includes/db.php';

$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Rate limiting: tối đa 5 lần thử trong 5 phút
    $max_attempts = 5;
    $lockout_time = 300; // 5 phút
    if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
    if (!isset($_SESSION['login_lockout'])) $_SESSION['login_lockout'] = 0;

    if ($_SESSION['login_attempts'] >= $max_attempts && (time() - $_SESSION['login_lockout']) < $lockout_time) {
        $remaining = $lockout_time - (time() - $_SESSION['login_lockout']);
        $error = "Quá nhiều lần thử đăng nhập. Vui lòng đợi " . ceil($remaining / 60) . " phút.";
    } else {
        if ($_SESSION['login_attempts'] >= $max_attempts) {
            // Reset sau khi hết thời gian lockout
            $_SESSION['login_attempts'] = 0;
        }

        $username = $_POST["username"] ?? '';
        $password = $_POST["password"] ?? '';

        $user = check_login($username, $password);

        if ($user) {
            // Reset login attempts
            $_SESSION['login_attempts'] = 0;
            unset($_SESSION['login_lockout']);

            // Chống session fixation
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['first_login'] = $user['first_login'];

            if ($user['first_login']) {
                header("Location: password/new_password.php");
                exit();
            } else {
                header("Location: dashboard.php");
                exit();
            }
        } else {
            $_SESSION['login_attempts']++;
            $_SESSION['login_lockout'] = time();
            $error = "Sai tên đăng nhập hoặc mật khẩu!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    
    <!-- ========== SEO TỐI ƯU VỚI THÔNG TIN CLB ========== -->
    <!-- Primary Meta Tags -->
    <title>Đăng nhập | CLB Kỹ năng Đoàn - THPT Lý Thường Kiệt</title>
    <meta name="title" content="Đăng nhập hệ thống quản lý điểm danh CLB Kỹ năng Đoàn">
    <meta name="description" content="Đăng nhập vào hệ thống quản lý điểm danh và theo dõi hoạt động của CLB Kỹ năng Đoàn trường THPT Lý Thường Kiệt. Website chính thức: clbkynangdoanhoiltk.io.vn">
    <meta name="keywords" content="CLB Kỹ năng Đoàn, THPT Lý Thường Kiệt, đăng nhập, điểm danh, quản lý hoạt động, clbkynangdoanhoiltk.io.vn, Facebook CLB Kỹ năng Đoàn">
    <meta name="author" content="CLB Kỹ năng Đoàn - THPT Lý Thường Kiệt">
    <meta name="robots" content="index, follow">
    <meta name="language" content="Vietnamese">
    <meta name="revisit-after" content="1 days">
    
    <!-- Open Graph / Facebook - Tối ưu với Fanpage -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://clbkynangdoanhoiltk.io.vn/">
    <meta property="og:title" content="Đăng nhập hệ thống CLB Kỹ năng Đoàn">
    <meta property="og:description" content="Hệ thống quản lý điểm danh thông minh dành cho CLB Kỹ năng Đoàn trường THPT Lý Thường Kiệt">
    <meta property="og:image" content="https://clbkynangdoanhoiltk.io.vn/assets/images/og-image.jpg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="CLB Kỹ năng Đoàn">
    <meta property="og:locale" content="vi_VN">
    
    <!-- Facebook Page Meta -->
    <meta property="fb:app_id" content="YOUR_FB_APP_ID"> <!-- Thêm App ID nếu có -->
    <meta property="fb:pages" content="clbkynangdoan.ltk">
    
    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="https://clbkynangdoanhoiltk.io.vn/">
    <meta name="twitter:title" content="Đăng nhập hệ thống CLB Kỹ năng Đoàn">
    <meta name="twitter:description" content="Hệ thống quản lý điểm danh thông minh CLB Kỹ năng Đoàn">
    <meta name="twitter:image" content="https://clbkynangdoanhoiltk.io.vn/assets/images/twitter-image.jpg">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="https://clbkynangdoanhoiltk.io.vn/">
    
    <!-- Favicon & Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="/hethongdiemdanh/assets/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/hethongdiemdanh/assets/favicon/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/hethongdiemdanh/assets/favicon/apple-touch-icon.png">
    <link rel="manifest" href="/hethongdiemdanh/assets/favicon/site.webmanifest">
    <meta name="msapplication-TileColor" content="#1c9665">
    <meta name="theme-color" content="#1c9665">
    
    <!-- Preconnect & Preload -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- JSON-LD Structured Data với thông tin CLB -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "CLB Kỹ năng Đoàn - THPT Lý Thường Kiệt",
        "alternateName": "CLB Kỹ năng Đoàn LTK",
        "url": "https://clbkynangdoanhoiltk.io.vn/",
        "logo": "https://clbkynangdoanhoiltk.io.vn/assets/logo_CLB.png",
        "sameAs": [
            "https://www.facebook.com/clbkynangdoan.ltk"
        ],
        "description": "Câu lạc bộ Kỹ năng Đoàn trường THPT Lý Thường Kiệt - Nơi phát triển kỹ năng và phẩm chất Đoàn viên",
        "address": {
            "@type": "PostalAddress",
            "addressLocality": "Hồ Chí Minh",
            "addressCountry": "VN"
        }
    }
    </script>
    
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
                        'pulse-slow': 'pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'slideIn': 'slideIn 0.3s ease-out'
                    },
                    keyframes: {
                        float: {
                            '0%': { transform: 'translate(0, 0) scale(1)' },
                            '100%': { transform: 'translate(5%, 5%) scale(1.1)' },
                        },
                        slideIn: {
                            '0%': { opacity: 0, transform: 'translateY(-10px)' },
                            '100%': { opacity: 1, transform: 'translateY(0)' }
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <style>
        body { -webkit-tap-highlight-color: transparent; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="font-sans min-h-screen flex items-center justify-center p-5 relative overflow-hidden bg-gradient-to-br from-slate-50 via-primary-50 to-primary-100 text-slate-800 selection:bg-primary-200 selection:text-primary-900">

    <!-- Schema.org hidden data -->
    <div class="hidden" itemscope itemtype="https://schema.org/WebPage">
        <meta itemprop="name" content="Đăng nhập CLB Kỹ năng Đoàn - Hội Trường THPT Lý Thường Kiệt">
        <meta itemprop="url" content="https://clbkynangdoanhoiltk.io.vn/">
        <link itemprop="sameAs" href="https://www.facebook.com/clbkynangdoan.ltk">
    </div>

    <!-- Background Orbs -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute -top-40 -left-40 w-[500px] h-[500px] rounded-full bg-gradient-to-br from-primary-200 to-primary-400 opacity-30 blur-[80px] animate-float"></div>
        <div class="absolute -bottom-60 -right-40 w-[600px] h-[600px] rounded-full bg-gradient-to-br from-primary-300 to-primary-600 opacity-20 blur-[80px] animate-float-reverse"></div>
    </div>

    <!-- Main Container -->
    <main class="w-full max-w-sm relative z-10" x-data="loginForm()">
        <div class="bg-white/85 backdrop-blur-xl rounded-2xl p-7 shadow-[0_20px_50px_-15px_rgba(28,150,101,0.18)] border border-white/80 transition-all duration-300">
            
            <!-- Brand -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary-100 mb-4 shadow-inner">
                    <img src="assets/logo_CLB.png" alt="Logo" class="w-12 h-12 object-contain rounded-xl">
                </div>
                <h1 class="text-xl font-extrabold text-slate-800 tracking-tight">Chào mừng đồng chí</h1>
                <p class="text-xs text-slate-400 font-medium mt-1">đến với Hệ thống điểm danh dành cho BTC</p>
            </div>

            <!-- Alerts -->
            <?php if(isset($_GET['timeout'])): ?>
            <div class="flex items-center gap-2 p-3 mb-5 text-amber-800 bg-amber-50 border-l-4 border-amber-400 rounded-r-lg text-sm">
                <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                <span class="font-medium">Phiên đăng nhập đã hết hạn.</span>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="flex items-center gap-2 p-3 mb-5 text-red-800 bg-red-50 border-l-4 border-red-400 rounded-r-lg text-sm">
                <i class="bi bi-x-circle-fill flex-shrink-0"></i>
                <span class="font-medium"><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="post" @submit="isSubmitting = true" class="space-y-4">

                <!-- Username -->
                <div class="relative group">
                    <i class="bi bi-person absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-base transition-colors group-focus-within:text-primary-500 pointer-events-none"></i>
                    <input type="text" id="username" name="username" x-model="username" required
                           placeholder="Tên đăng nhập" autocomplete="username"
                           class="w-full h-11 pl-10 pr-4 bg-slate-50 border-2 border-slate-200 rounded-xl text-slate-800 text-sm font-medium placeholder:text-slate-400 placeholder:font-normal focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 outline-none transition-all">
                </div>

                <!-- Password -->
                <div class="relative group">
                    <i class="bi bi-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-base transition-colors group-focus-within:text-primary-500 pointer-events-none"></i>
                    <input :type="showPass ? 'text' : 'password'" id="password" name="password" required
                           placeholder="Mật khẩu" autocomplete="current-password"
                           class="w-full h-11 pl-10 pr-11 bg-slate-50 border-2 border-slate-200 rounded-xl text-slate-800 text-sm font-medium placeholder:text-slate-400 placeholder:font-normal focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 outline-none transition-all">
                    <button type="button" @click="showPass = !showPass" class="absolute right-1.5 top-1/2 -translate-y-1/2 w-8 h-8 rounded-lg text-slate-400 hover:text-primary-600 hover:bg-primary-50 flex items-center justify-center transition-colors">
                        <i class="bi text-base" :class="showPass ? 'bi-eye' : 'bi-eye-slash'"></i>
                    </button>
                </div>

                <!-- Options row -->
                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer group">
                        <div class="relative flex items-center">
                            <input type="checkbox" name="remember" x-model="remember" @change="saveSettings"
                                   class="peer appearance-none w-4 h-4 border-2 border-slate-300 rounded checked:bg-primary-500 checked:border-primary-500 transition-colors focus:ring-2 focus:ring-primary-500/20 outline-none cursor-pointer">
                            <i class="bi bi-check2 absolute text-white text-xs left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 opacity-0 peer-checked:opacity-100 pointer-events-none transition-opacity"></i>
                        </div>
                        <span class="text-xs font-medium text-slate-500 group-hover:text-slate-700 transition-colors">Ghi nhớ</span>
                    </label>
                    <a href="modules/forgot_password.php" class="text-xs font-semibold text-primary-600 hover:text-primary-700 transition-colors">
                        Quên mật khẩu?
                    </a>
                </div>

                <!-- Submit -->
                <button type="submit" :disabled="isSubmitting"
                        class="w-full h-11 bg-gradient-to-r from-primary-600 to-primary-500 hover:from-primary-700 hover:to-primary-600 text-white font-bold rounded-xl shadow-[0_4px_14px_rgba(28,150,101,0.35)] hover:shadow-[0_6px_20px_rgba(28,150,101,0.45)] active:scale-[0.98] transition-all flex items-center justify-center gap-2 disabled:opacity-70 disabled:cursor-not-allowed">
                    <template x-if="!isSubmitting">
                        <div class="flex items-center gap-2">
                            <i class="bi bi-box-arrow-in-right"></i>
                            <span>Đăng nhập</span>
                        </div>
                    </template>
                    <template x-if="isSubmitting">
                        <div class="flex items-center gap-2">
                            <i class="bi bi-arrow-repeat animate-spin"></i>
                            <span>Đang xử lý...</span>
                        </div>
                    </template>
                </button>
            </form>

            <!-- Footer -->
            <div class="mt-5 pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-400">
                <span>&copy;  2023 CLB Kỹ năng Đoàn - THPT Lý Thường Kiệt</span>
                <div class="flex gap-3">
                    <a href="https://clbkynangdoanhoiltk.io.vn/" target="_blank" class="hover:text-primary-600 transition-colors">Website</a>
                    <a href="https://www.facebook.com/clbkynangdoan.ltk" target="_blank" class="hover:text-primary-600 transition-colors">Facebook</a>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('loginForm', () => ({
                showPass: false,
                remember: false,
                username: '',
                isSubmitting: false,
                init() {
                    const savedUsername = localStorage.getItem('clb_username');
                    if (savedUsername) {
                        this.username = savedUsername;
                        this.remember = true;
                    }
                },
                saveSettings() {
                    if (this.remember) {
                        localStorage.setItem('clb_username', this.username);
                    } else {
                        localStorage.removeItem('clb_username');
                    }
                }
            }))
        })
    </script>
</body>
</html>