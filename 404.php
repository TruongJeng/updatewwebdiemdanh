<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 – Không tìm thấy trang | CLB Kỹ năng Đoàn</title>
    <meta name="theme-color" content="#1c9665">
    <link rel="icon" type="image/png" href="/hethongdiemdanh/assets/logo_CLB.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        primary: {
                            50: '#f0fdf8', 100: '#d4f7eb', 200: '#b0eed2',
                            300: '#7de0b8', 400: '#48ce99', 500: '#27b87e',
                            600: '#1c9665', 700: '#177850', 800: '#165f40', 900: '#144e35',
                        }
                    },
                    animation: {
                        'float': 'float 20s infinite alternate',
                        'float-reverse': 'float 25s infinite alternate-reverse',
                        'bounce-slow': 'bounce 3s infinite',
                    },
                    keyframes: {
                        float: {
                            '0%': { transform: 'translate(0,0) scale(1)' },
                            '100%': { transform: 'translate(5%,5%) scale(1.1)' },
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

    <!-- Card -->
    <main class="w-full max-w-sm relative z-10 text-center">
        <div class="bg-white/85 backdrop-blur-xl rounded-2xl p-8 shadow-[0_20px_50px_-15px_rgba(28,150,101,0.18)] border border-white/80">

            <!-- Error Number -->
            <div class="text-8xl font-black text-primary-200 leading-none mb-2 select-none animate-bounce-slow">404</div>

            <!-- Icon -->
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-amber-50 text-amber-500 mb-5 shadow-inner border border-amber-100">
                <i class="bi bi-map text-3xl"></i>
            </div>

            <h1 class="text-xl font-extrabold text-slate-800 tracking-tight mb-2">Không tìm thấy trang</h1>
            <p class="text-sm text-slate-500 font-medium mb-7">
                Trang bạn đang tìm kiếm không tồn tại hoặc đã bị di chuyển.
            </p>

            <div class="flex flex-col gap-3">
                <a href="/hethongdiemdanh/index"
                   class="w-full h-11 bg-gradient-to-r from-primary-600 to-primary-500 hover:from-primary-700 hover:to-primary-600 text-white font-bold rounded-xl shadow-[0_4px_14px_rgba(28,150,101,0.35)] hover:shadow-[0_6px_20px_rgba(28,150,101,0.45)] active:scale-[0.98] transition-all flex items-center justify-center gap-2">
                    <i class="bi bi-box-arrow-in-right"></i> Quay lại Đăng nhập
                </a>
                <button onclick="history.back()"
                        class="w-full h-11 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl border border-slate-200 active:scale-[0.98] transition-all flex items-center justify-center gap-2">
                    <i class="bi bi-arrow-left"></i> Trang trước
                </button>
            </div>

            <p class="mt-6 text-[11px] text-slate-400">&copy; <?= date('Y') ?> CLB Kỹ năng Đoàn – THPT Lý Thường Kiệt</p>
        </div>
    </main>

</body>
</html>
