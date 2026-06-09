<?php
// Nếu cần phân quyền, có thể thêm session_start() và kiểm tra session ở đây
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Thông tin phần mềm</title>
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
</head>
<body class="bg-slate-50/50 min-h-screen text-slate-800 font-sans antialiased flex flex-col">
<?php include '../includes/header.php'; ?>

<main class="flex-1 max-w-4xl w-full mx-auto p-4 sm:p-6 lg:p-8 animate-[fadeInUp_0.5s_ease-out]">
    
    <div class="mb-6 flex items-center gap-3">
        <div class="w-12 h-12 rounded-xl bg-red-100 text-red-600 flex items-center justify-center shadow-sm">
            <i class="bi bi-info-circle-fill text-2xl"></i>
        </div>
        <div>
            <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">THÔNG TIN PHẦN MỀM</h2>
            <p class="text-sm font-medium text-slate-500">Các thông tin hỗ trợ và liên hệ kỹ thuật</p>
        </div>
    </div>

    <div class="bg-white rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.06)] border border-slate-100 overflow-hidden">
        <!-- Decoration header -->
        <div class="h-2 bg-gradient-to-r from-red-500 to-rose-500"></div>
        
        <div class="p-6 sm:p-8">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <tbody class="divide-y divide-slate-100">
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <th class="py-4 pr-4 font-bold text-slate-700 w-1/3 sm:w-1/4 uppercase tracking-wider text-xs">Tên phần mềm</th>
                            <td class="py-4 font-semibold text-slate-800 text-base">Hệ Thống Điểm Danh - CLB Kỹ năng Đoàn Hội</td>
                        </tr>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <th class="py-4 pr-4 font-bold text-slate-700 uppercase tracking-wider text-xs">Phiên bản</th>
                            <td class="py-4 font-semibold text-primary-600 text-base">BETA 0.1.0</td>
                        </tr>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <th class="py-4 pr-4 font-bold text-slate-700 uppercase tracking-wider text-xs">Đơn vị phát triển & hỗ trợ</th>
                            <td class="py-4 font-medium text-slate-600">
                                Ban Hậu Cần Kỹ Thuật (Ban HK - CLB Kỹ năng Đoàn - Hội)<br>
                                <a href="mailto:contact@domain.com" class="text-primary-600 hover:text-primary-800 transition-colors inline-flex items-center gap-1 mt-1">
                                    <i class="bi bi-envelope-fill"></i> liên hệ qua Fanpage
                                </a>
                            </td>
                        </tr>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <th class="py-4 pr-4 font-bold text-slate-700 uppercase tracking-wider text-xs">Fan Page</th>
                            <td class="py-4 font-medium">
                                <a href="https://www.facebook.com/clbkynangdoan.ltk" target="_blank" class="text-primary-600 hover:text-primary-800 transition-colors inline-flex items-center gap-2 font-semibold">
                                    <i class="bi bi-facebook text-blue-600"></i> facebook.com/clbkynangdoan.ltk
                                </a>
                            </td>
                        </tr>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <th class="py-4 pr-4 font-bold text-slate-700 uppercase tracking-wider text-xs">Đơn vị hỗ trợ thông tin</th>
                            <td class="py-4 font-medium text-slate-700">Trường THPT Lý Thường Kiệt</td>
                        </tr>
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <th class="py-4 pr-4 font-bold text-slate-700 uppercase tracking-wider text-xs">Trình duyệt hỗ trợ tốt nhất</th>
                            <td class="py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center text-xl shadow-sm" title="Chrome">
                                        <i class="bi bi-browser-chrome text-green-600"></i>
                                    </div>
                                    <div class="w-10 h-10 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center text-xl shadow-sm" title="Firefox">
                                        <i class="bi bi-browser-firefox text-orange-500"></i>
                                    </div>
                                    <div class="w-10 h-10 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center text-xl shadow-sm" title="Safari">
                                        <i class="bi bi-browser-safari text-blue-400"></i>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include '../includes/footer.php'; ?>

<style>
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>
</body>
</html>