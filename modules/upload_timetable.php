<?php
// require_once __DIR__ . '/../config/session.php';
// Kiểm tra quyền nếu cần
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['timetable_file'])) {
    $file = $_FILES['timetable_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    // Chỉ cho phép Excel
    if (($ext == 'xlsx' || $ext == 'xls') && $file['size'] < 5*1024*1024) {
        $target = '../uploads/timetable.xlsx'; // Ghi đè file cũ
        if (move_uploaded_file($file['tmp_name'], $target)) {
            $msg = '<div class="mb-6 flex items-center justify-between p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 rounded-r-lg shadow-sm"><div class="flex items-center gap-2"><i class="bi bi-check-circle-fill text-lg"></i><span class="font-medium">Tải file thành công!</span></div></div>';
        } else {
            $msg = '<div class="mb-6 flex items-center justify-between p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-r-lg shadow-sm"><div class="flex items-center gap-2"><i class="bi bi-exclamation-circle-fill text-lg"></i><span class="font-medium">Lỗi khi lưu file lên server.</span></div></div>';
        }
    } else {
        $msg = '<div class="mb-6 flex items-center justify-between p-4 bg-amber-50 border-l-4 border-amber-500 text-amber-700 rounded-r-lg shadow-sm"><div class="flex items-center gap-2"><i class="bi bi-exclamation-triangle-fill text-lg"></i><span class="font-medium">Chỉ chấp nhận file Excel (.xlsx, .xls) nhỏ hơn 5MB.</span></div></div>';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Upload thời khóa biểu</title>
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
    <!-- Alpine.js (optional for interaction) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-50/50 text-slate-800 font-sans antialiased flex flex-col min-h-screen">
<?php 
// Giả định admin layout 
include '../includes/header.php'; 
// include '../includes/sidebar.php'; // Nếu cần
?>

<main class="flex-1 max-w-2xl w-full mx-auto p-4 sm:p-6 lg:p-8 mt-4 animate-[fadeInUp_0.5s_ease-out]">
    
    <div class="mb-6 flex items-center gap-3">
        <a href="../dashboard.php" class="text-slate-500 hover:text-primary-600 transition-colors flex items-center gap-1.5 text-sm font-medium bg-white px-3 py-1.5 rounded-full border border-slate-200 shadow-sm hover:shadow">
            <i class="bi bi-arrow-left"></i> Về Trang chủ
        </a>
    </div>

    <div class="bg-white rounded-3xl shadow-[0_8px_30px_rgb(0,0,0,0.06)] border border-slate-100 overflow-hidden relative">
        <!-- Decoration -->
        <div class="absolute top-0 right-0 p-8 opacity-10 pointer-events-none">
            <i class="bi bi-cloud-arrow-up text-9xl text-primary-500"></i>
        </div>
        
        <div class="p-8 relative z-10">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-14 h-14 rounded-2xl bg-primary-50 text-primary-600 flex items-center justify-center shadow-inner">
                    <i class="bi bi-file-earmark-excel-fill text-3xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-black text-slate-800 tracking-tight">Tải lên Thời khóa biểu</h2>
                    <p class="text-sm font-medium text-slate-500">Cập nhật dữ liệu từ file Excel (.xlsx, .xls)</p>
                </div>
            </div>

            <?= $msg ?>

            <form method="post" enctype="multipart/form-data" class="space-y-6" x-data="{ fileName: '' }">
                
                <div class="relative group cursor-pointer">
                    <input type="file" name="timetable_file" id="timetable_file" accept=".xlsx,.xls" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-20" required @change="fileName = $event.target.files[0]?.name || ''">
                    <div class="border-2 border-dashed border-primary-200 bg-primary-50/50 group-hover:bg-primary-50 group-hover:border-primary-400 rounded-2xl p-10 text-center transition-all duration-300">
                        <i class="bi bi-cloud-upload text-5xl text-primary-400 group-hover:text-primary-500 transition-colors mb-4 block group-hover:-translate-y-1 transform"></i>
                        <h4 class="text-lg font-bold text-slate-700 mb-1" x-text="fileName ? fileName : 'Chọn file hoặc kéo thả vào đây'"></h4>
                        <p class="text-sm font-medium text-slate-500" x-show="!fileName">Hỗ trợ Excel nhỏ hơn 5MB</p>
                        <div class="mt-4 inline-flex items-center gap-2 bg-white px-4 py-2 rounded-xl text-primary-600 font-semibold border border-primary-100 shadow-sm group-hover:shadow group-hover:border-primary-300 transition-all">
                            <i class="bi bi-folder2-open"></i> <span x-text="fileName ? 'Thay đổi file' : 'Duyệt file'"></span>
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-bold text-lg py-4 px-6 rounded-xl shadow-md hover:shadow-lg transition-all transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                    <i class="bi bi-upload"></i> XÁC NHẬN TẢI LÊN
                </button>
            </form>
            
            <div class="mt-8 bg-slate-50 border border-slate-200 rounded-xl p-5">
                <h5 class="text-sm font-bold text-slate-700 mb-2 flex items-center gap-2">
                    <i class="bi bi-info-circle-fill text-primary-500"></i> Hướng dẫn upload
                </h5>
                <ul class="text-sm text-slate-600 space-y-2 font-medium list-disc list-inside">
                    <li>File sẽ được lưu vào hệ thống và <strong class="text-red-500 bg-red-50 px-1 rounded">ghi đè</strong> nếu đã có file cũ.</li>
                    <li>Chỉ hỗ trợ định dạng Excel chuẩn (<code class="bg-white border px-1 rounded">.xlsx</code> hoặc <code class="bg-white border px-1 rounded">.xls</code>), dung lượng dưới 5MB.</li>
                    <li>File mẫu: <b>TKB LOP</b> dạng bảng ngang phân bổ theo từng lớp.</li>
                </ul>
            </div>
            
        </div>
    </div>
</main>

<style>
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<?php include '../includes/footer.php'; ?>
</body>
</html>