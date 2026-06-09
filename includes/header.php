<?php
if (!isset($pageTitle)) $pageTitle = 'CLB Kỹ Năng Đoàn Hội Trường THPT Lý Thường Kiệt';
if (!isset($full_name)) $full_name = $_SESSION['full_name'] ?? 'Người dùng';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="/hethongdiemdanh/assets/logo_CLB.png">
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons (Retained for compatibility) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb', 
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Alpine.js for interactions -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        /* Base styles & Utility overrides */
        body { 
            background-color: #f0f4f8; 
            padding-top: 64px; 
            font-family: 'Inter', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        /* Utility class to hide element visually but keep it accessible if needed */
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="text-slate-800 antialiased selection:bg-primary-200 selection:text-primary-900 min-h-screen flex flex-col">

<!-- Topbar -->
<header class="fixed top-0 left-0 right-0 h-16 bg-gradient-to-r from-primary-700 via-primary-600 to-primary-500 shadow-md z-[1200] px-4 sm:px-6 flex items-center justify-between transition-all duration-300">
    
    <!-- Logo Area -->
    <div class="flex items-center">
        <a href="/hethongdiemdanh/dashboard.php" class="flex items-center gap-3 text-white hover:text-primary-50 transition-colors group focus:outline-none focus:ring-2 focus:ring-white/50 rounded-lg pr-2">
            <div class="bg-white/10 p-1 rounded-full group-hover:bg-white/20 transition-colors">
                <img src="/hethongdiemdanh/assets/logo_CLB.png" alt="Logo" class="h-8 w-8 object-contain drop-shadow-sm group-hover:scale-105 transition-transform duration-300">
            </div>
            <span class="font-semibold text-sm sm:text-base tracking-wide hidden sm:block"><?= htmlspecialchars($pageTitle) ?></span>
            <span class="font-semibold text-sm tracking-wide sm:hidden">CLB Kỹ Năng Đoàn</span>
        </a>
    </div>

    <!-- User Menu (Alpine.js) -->
    <div class="relative" x-data="{ open: false }" @click.away="open = false" @close.stop="open = false">
        <button @click="open = !open" class="flex items-center gap-2 text-white hover:bg-white/10 px-2 sm:px-3 py-1.5 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-white/50" :aria-expanded="open">
            <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center border border-white/30 overflow-hidden shadow-sm">
                <i class="bi bi-person-fill text-lg"></i>
            </div>
            <span class="font-medium text-sm hidden md:block max-w-[150px] truncate"><?= htmlspecialchars($full_name) ?></span>
            <i class="bi bi-chevron-down text-[10px] opacity-70 transition-transform duration-300" :class="{'rotate-180': open}"></i>
        </button>

        <!-- Dropdown Menu -->
        <div x-show="open" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-2"
             class="absolute right-0 mt-2 w-56 bg-white/95 backdrop-blur-md rounded-xl shadow-[0_10px_40px_-10px_rgba(0,0,0,0.15)] ring-1 ring-slate-900/5 overflow-hidden z-[2000] focus:outline-none"
             style="display: none;"
             x-cloak
             role="menu">
            <div class="p-2">
                <!-- User Info Mobile -->
                <div class="md:hidden px-3 py-2.5 bg-slate-50/80 rounded-lg mb-2">
                    <p class="text-[11px] text-slate-500 font-medium uppercase tracking-wider">Tài khoản</p>
                    <p class="text-sm font-bold text-slate-800 truncate mt-0.5"><?= htmlspecialchars($full_name) ?></p>
                </div>

                <a href="/hethongdiemdanh/password/change_password.php" class="flex items-center gap-3 px-3 py-2.5 text-sm text-slate-700 font-medium rounded-lg hover:bg-primary-50 hover:text-primary-700 transition-colors group" role="menuitem">
                    <div class="w-7 h-7 rounded-md bg-slate-100 group-hover:bg-primary-100 text-slate-500 group-hover:text-primary-600 flex items-center justify-center transition-colors">
                        <i class="bi bi-key text-base"></i>
                    </div>
                    Đổi mật khẩu
                </a>
                
                <div class="h-px bg-slate-100 my-1 mx-2"></div>
                
                <a href="/hethongdiemdanh/logout.php" class="flex items-center gap-3 px-3 py-2.5 text-sm text-red-600 font-medium rounded-lg hover:bg-red-50 transition-colors group" role="menuitem">
                    <div class="w-7 h-7 rounded-md bg-red-50 group-hover:bg-red-100 flex items-center justify-center transition-colors">
                        <i class="bi bi-box-arrow-right text-base"></i>
                    </div>
                    Đăng xuất
                </a>
            </div>
        </div>
    </div>
</header>
<!-- Nội dung trang bắt đầu -->