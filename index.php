<?php
require_once __DIR__ . '/config/session.php';
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

require 'includes/db.php';

$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"] ?? '';
    $password = $_POST["password"] ?? '';

    $user = check_login($username, $password);

    if ($user) {
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
        $error = "Sai tên đăng nhập hoặc mật khẩu!";
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
    <meta name="msapplication-TileColor" content="#3178c6">
    <meta name="theme-color" content="#3178c6">
    
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
                            50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe',
                            300: '#93c5fd', 400: '#60a5fa', 500: '#3b82f6',
                            600: '#2563eb', 700: '#1d4ed8', 800: '#1e40af', 900: '#1e3a8a',
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
        <meta itemprop="name" content="Đăng nhập CLB Kỹ năng Đoàn">
        <meta itemprop="url" content="https://clbkynangdoanhoiltk.io.vn/">
        <link itemprop="sameAs" href="https://www.facebook.com/clbkynangdoan.ltk">
    </div>

    <!-- Background Orbs -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0">
        <div class="absolute -top-40 -left-40 w-[500px] h-[500px] rounded-full bg-gradient-to-br from-primary-200 to-primary-400 opacity-30 blur-[80px] animate-float"></div>
        <div class="absolute -bottom-60 -right-40 w-[600px] h-[600px] rounded-full bg-gradient-to-br from-primary-300 to-primary-600 opacity-20 blur-[80px] animate-float-reverse"></div>
    </div>

    <!-- Main Container -->
    <main class="w-full max-w-[480px] relative z-10" x-data="loginForm()">
        <div class="bg-white/80 backdrop-blur-xl rounded-[2rem] p-8 sm:p-10 shadow-[0_30px_60px_-15px_rgba(37,99,235,0.15)] border border-white transition-all duration-300 hover:shadow-[0_40px_80px_-20px_rgba(37,99,235,0.25)] hover:-translate-y-1">
            
            <!-- Brand Section -->
            <div class="text-center mb-8">
                <div class="inline-block relative mb-5 group">
                    <div class="absolute inset-[-8px] rounded-full bg-gradient-to-br from-primary-200 to-primary-500 opacity-40 blur-md animate-pulse-slow"></div>
                    <div class="relative z-10 w-24 h-24 rounded-full bg-white p-1.5 shadow-xl transition-transform duration-300 group-hover:scale-105 flex items-center justify-center overflow-hidden">
                        <img src="/hethongdiemdanh/assets/logo_CLB.png" alt="CLB Kỹ năng Đoàn" class="w-full h-full rounded-full object-cover border-2 border-white">
                    </div>
                </div>
                
                <h1 class="text-2xl sm:text-3xl font-extrabold bg-clip-text text-transparent bg-gradient-to-br from-slate-800 to-primary-700 tracking-tight mb-2">Chào mừng trở lại</h1>
                <p class="text-slate-500 font-medium flex items-center justify-center gap-2 text-sm sm:text-base">
                    <i class="bi bi-shield-check text-primary-500"></i>
                    Hệ thống điểm danh thông minh
                </p>
                
                <!-- Social Links -->
                <div class="flex items-center justify-center gap-4 mt-5">
                    <a href="https://clbkynangdoanhoiltk.io.vn/" target="_blank" class="flex items-center gap-2 px-4 py-2 bg-slate-50 border border-slate-200 rounded-full text-slate-600 font-semibold text-xs sm:text-sm hover:bg-white hover:text-primary-600 hover:border-primary-200 hover:shadow-md hover:-translate-y-0.5 transition-all">
                        <i class="bi bi-globe2 text-base"></i> <span class="hidden sm:inline">Website</span>
                    </a>
                    <a href="https://www.facebook.com/clbkynangdoan.ltk" target="_blank" class="flex items-center gap-2 px-4 py-2 bg-slate-50 border border-slate-200 rounded-full text-[#1877f2] font-semibold text-xs sm:text-sm hover:bg-white hover:border-[#1877f2]/30 hover:shadow-md hover:-translate-y-0.5 transition-all">
                        <i class="bi bi-facebook text-base"></i> <span class="hidden sm:inline">Facebook</span>
                    </a>
                </div>
            </div>

            <!-- Alerts -->
            <?php if(isset($_GET['timeout'])): ?>
                <div class="flex items-center gap-3 p-4 mb-6 text-amber-800 bg-amber-50 border-l-4 border-amber-500 rounded-r-lg animate-slideIn">
                    <i class="bi bi-exclamation-triangle-fill text-xl"></i>
                    <span class="font-medium text-sm">Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.</span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="flex items-center gap-3 p-4 mb-6 text-red-800 bg-red-50 border-l-4 border-red-500 rounded-r-lg animate-slideIn">
                    <i class="bi bi-x-circle-fill text-xl"></i>
                    <span class="font-medium text-sm"><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="post" @submit="isSubmitting = true">
                <!-- Username -->
                <div class="mb-5 relative group">
                    <label for="username" class="block text-sm font-semibold text-slate-700 mb-2">
                        <i class="bi bi-person-circle text-primary-500 mr-1.5"></i> Tên đăng nhập
                    </label>
                    <div class="relative">
                        <i class="bi bi-person absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg transition-colors group-focus-within:text-primary-500"></i>
                        <input type="text" id="username" name="username" x-model="username" required
                               placeholder="Nhập tên đăng nhập" autocomplete="username"
                               class="w-full h-14 pl-12 pr-4 bg-white/50 border-2 border-slate-200 rounded-xl text-slate-800 font-medium placeholder:text-slate-400 placeholder:font-normal focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 outline-none transition-all">
                    </div>
                </div>

                <!-- Password -->
                <div class="mb-5 relative group">
                    <label for="password" class="block text-sm font-semibold text-slate-700 mb-2">
                        <i class="bi bi-key text-primary-500 mr-1.5"></i> Mật khẩu
                    </label>
                    <div class="relative">
                        <i class="bi bi-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg transition-colors group-focus-within:text-primary-500"></i>
                        <input :type="showPass ? 'text' : 'password'" id="password" name="password" required
                               placeholder="Nhập mật khẩu" autocomplete="current-password"
                               class="w-full h-14 pl-12 pr-12 bg-white/50 border-2 border-slate-200 rounded-xl text-slate-800 font-medium placeholder:text-slate-400 placeholder:font-normal focus:bg-white focus:border-primary-500 focus:ring-4 focus:ring-primary-500/10 outline-none transition-all">
                        <button type="button" @click="showPass = !showPass" class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full text-slate-400 hover:text-primary-600 hover:bg-primary-50 flex items-center justify-center transition-colors focus:outline-none">
                            <i class="bi text-[20px]" :class="showPass ? 'bi-eye' : 'bi-eye-slash'"></i>
                        </button>
                    </div>
                </div>

                <!-- Options -->
                <div class="flex items-center justify-between mb-8 flex-wrap gap-4">
                    <label class="flex items-center gap-2.5 cursor-pointer group">
                        <div class="relative flex items-center">
                            <input type="checkbox" name="remember" id="remember" x-model="remember" @change="saveSettings"
                                   class="peer appearance-none w-5 h-5 border-2 border-slate-300 rounded-md checked:bg-primary-500 checked:border-primary-500 transition-colors focus:ring-4 focus:ring-primary-500/20 outline-none cursor-pointer">
                            <i class="bi bi-check2 absolute text-white text-lg left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 opacity-0 peer-checked:opacity-100 pointer-events-none transition-opacity"></i>
                        </div>
                        <span class="text-sm font-medium text-slate-600 group-hover:text-slate-800 transition-colors">Ghi nhớ đăng nhập</span>
                    </label>
                    
                    <a href="modules/forgot_password.php" class="text-sm font-semibold text-primary-600 hover:text-primary-700 hover:bg-primary-50 px-3 py-1.5 rounded-full transition-colors flex items-center gap-1.5">
                        <i class="bi bi-question-circle"></i> Quên mật khẩu?
                    </a>
                </div>

                <!-- Submit -->
                <button type="submit" :disabled="isSubmitting" 
                        class="relative w-full h-14 bg-gradient-to-r from-primary-600 to-primary-500 hover:from-primary-700 hover:to-primary-600 text-white font-bold text-lg rounded-xl shadow-[0_8px_20px_rgba(37,99,235,0.3)] hover:shadow-[0_10px_25px_rgba(37,99,235,0.4)] active:scale-[0.98] transition-all flex items-center justify-center gap-3 overflow-hidden group disabled:opacity-70 disabled:cursor-not-allowed">
                    <span class="absolute w-0 h-0 transition-all duration-500 ease-out bg-white rounded-full group-hover:w-56 group-hover:h-56 opacity-10"></span>
                    
                    <template x-if="!isSubmitting">
                        <div class="flex items-center gap-2">
                            <i class="bi bi-box-arrow-in-right text-xl"></i>
                            <span>Đăng nhập</span>
                        </div>
                    </template>
                    
                    <template x-if="isSubmitting">
                        <div class="flex items-center gap-2">
                            <i class="bi bi-arrow-repeat text-xl animate-spin"></i>
                            <span>Đang xử lý...</span>
                        </div>
                    </template>
                </button>

                <!-- Security Badge -->
                <div class="mt-6 py-3 px-4 bg-slate-50 rounded-full flex items-center justify-center gap-2 text-slate-600 text-xs font-semibold border border-slate-100">
                    <i class="bi bi-shield-lock-fill text-green-500 text-base"></i>
                    <span>Hệ thống bảo mật &bull; Dữ liệu an toàn</span>
                </div>
            </form>
            
            <!-- Footer -->
            <div class="mt-8 pt-6 border-t border-slate-100 text-center">
                <p class="text-xs text-slate-400 font-medium mb-3">&copy; <?= date('Y') ?> CLB Kỹ năng Đoàn - THPT Lý Thường Kiệt</p>
                <div class="flex items-center justify-center gap-4 flex-wrap">
                    <a href="https://clbkynangdoanhoiltk.io.vn/" class="text-xs font-semibold text-slate-500 hover:text-primary-600 transition-colors">Website</a>
                    <a href="https://www.facebook.com/clbkynangdoan.ltk" class="text-xs font-semibold text-slate-500 hover:text-primary-600 transition-colors">Facebook</a>
                    <a href="#" class="text-xs font-semibold text-slate-500 hover:text-primary-600 transition-colors">Điều khoản</a>
                    <a href="#" class="text-xs font-semibold text-slate-500 hover:text-primary-600 transition-colors">Bảo mật</a>
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