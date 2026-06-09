<?php
require_once __DIR__ . '/../../config/session.php';
if (!in_array($_SESSION['role'], ['admin','club_leader'])) {
    die('Không có quyền');
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../../PHPSpreadsheet/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
/* ===== XOÁ MỀM ===== */
if (isset($_GET['disable'])) {
    $stmt = $pdo->prepare("UPDATE campers SET is_active = 0 WHERE student_code = ?");
    $stmt->execute([$_GET['disable']]);
    header("Location: manage_campers.php");
    exit;
}
if (isset($_GET['restore'])) {
    $student_code = $_GET['restore'];

    $stmt = $pdo->prepare("
        UPDATE campers 
        SET is_active = 1 
        WHERE student_code = ?
    ");
    $stmt->execute([$student_code]);

    header("Location: manage_campers.php?msg=restored");
    exit;
}

/* =======================
   XOÁ VĨNH VIỄN (ADMIN)
======================= */
if (isset($_GET['delete_forever']) && $_SESSION['role'] === 'admin') {
    $student_code = $_GET['delete_forever'];

    $pdo->beginTransaction();
    try {
        // Lấy student_id
        $stmt = $pdo->prepare("SELECT id FROM campers WHERE student_code = ?");
        $stmt->execute([$student_code]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($student) {
            $student_id = $student['id'];

            // Xoá lịch sử điểm danh
            $stmt = $pdo->prepare("
                DELETE FROM attendance_logs 
                WHERE student_id = ?
            ");
            $stmt->execute([$student_id]);

            // Xoá trại sinh
            $stmt = $pdo->prepare("
                DELETE FROM campers 
                WHERE id = ?
            ");
            $stmt->execute([$student_id]);
        }

        $pdo->commit();
        header("Location: manage_campers.php?msg=deleted");
        exit;

    } catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Lỗi xoá vĩnh viễn: " . $e->getMessage());
}
}

// ==========================
// XOÁ TẤT CẢ TRẠI SINH (ADMIN)
// ==========================
if (
    isset($_POST['delete_all']) &&
    isset($_SESSION['role']) &&
    $_SESSION['role'] === 'admin'
) {
    try {
        $pdo->beginTransaction();

        $pdo->exec("DELETE FROM attendance_logs");
        $pdo->exec("DELETE FROM campers");

        $pdo->commit();

        // reset auto increment – chạy riêng
        $pdo->exec("ALTER TABLE campers AUTO_INCREMENT = 1");
        $pdo->exec("ALTER TABLE attendance_logs AUTO_INCREMENT = 1");

        $deleteMsg = "✅ Đã xoá TOÀN BỘ trại sinh và lịch sử điểm danh!";
        $deleteMsgType = "success";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die('Lỗi xoá toàn bộ trại sinh: ' . $e->getMessage());

    }
}

/* ===== THÊM 1 ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_one'])) {

    // Lấy dữ liệu an toàn
    $student_code  = $_POST['student_code'] ?? null;
    $full_name     = $_POST['full_name'] ?? null;
    $class         = $_POST['class'] ?? null;
    $phone         = $_POST['phone'] ?? null;
    $phone_parent  = $_POST['phone_parent'] ?? null;
    $email = isset($_POST['email']) ? trim($_POST['email']) : null;
    $profile_photo = $_POST['profile_photo'] ?? null;

    // Check dữ liệu bắt buộc
    if (!$student_code || !$full_name) {
        die("Thiếu mã học sinh hoặc họ tên");
    }

    // Check trùng student_code
    $check = $pdo->prepare("SELECT 1 FROM campers WHERE student_code = ?");
    $check->execute([$student_code]);

    if ($check->fetch()) {
        die("Mã học sinh đã tồn tại");
    }

    // Insert
    $stmt = $pdo->prepare("
        INSERT INTO campers
        (student_code, full_name, class, phone, phone_parent, email, profile_photo, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
    ");

    $stmt->execute([
        $student_code,
        $full_name,
        $class,
        $phone,
        $phone_parent,
        $email,
        $profile_photo
    ]);
}
use PhpOffice\PhpSpreadsheet\IOFactory;

/* ===== IMPORT CSV ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_excel'])) {

    if (is_uploaded_file($_FILES['excel']['tmp_name'])) {

        $filePath = $_FILES['excel']['tmp_name'];

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            $pdo->beginTransaction();

            foreach ($rows as $index => $row) {

                // Bỏ dòng header (dòng 1)
                if ($index === 1) continue;

                $student_code  = trim($row['A'] ?? '');
                $full_name     = trim($row['B'] ?? '');
                $class         = trim($row['C'] ?? '');
                $phone         = trim($row['D'] ?? '');
                $phone_parent  = trim($row['E'] ?? '');
                $email         = trim($row['F'] ?? '');
                $profile_photo = trim($row['G'] ?? '');

                if ($student_code === '' || $full_name === '') continue;

                $stmt = $pdo->prepare("
                    INSERT IGNORE INTO campers
                    (student_code, full_name, class, phone, phone_parent, email, profile_photo, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ");

                $stmt->execute([
                    $student_code,
                    $full_name,
                    $class,
                    $phone,
                    $phone_parent,
                    $email,
                    $profile_photo
                ]);
            }

            $pdo->commit();

        } catch (Exception $e) {
            $pdo->rollBack();
            die('Lỗi import Excel: ' . $e->getMessage());
        }
    }
}
/* ===== DANH SÁCH ===== */
$campers = $pdo->query("
    SELECT student_code, full_name, phone, phone_parent, class, email, profile_photo, is_active
    FROM campers
    ORDER BY is_active DESC, full_name
")->fetchAll(PDO::FETCH_ASSOC);

// 1️⃣ Kiểm tra dữ liệu thiếu
foreach ($campers as &$c) {

    $missing = [];

    if (!isset($c['full_name']) || trim($c['full_name']) === '')
        $missing[] = 'Họ tên';

    if (!isset($c['class']) || trim($c['class']) === '')
        $missing[] = 'Lớp';

    if (!isset($c['phone']) || trim($c['phone']) === '')
        $missing[] = 'SĐT';

    if (!isset($c['phone_parent']) || trim($c['phone_parent']) === '')
        $missing[] = 'SĐT phụ huynh';

    if (!isset($c['email']) || trim($c['email']) === '')
        $missing[] = 'Email';

    if (
        !isset($c['profile_photo']) ||
        !filter_var(trim($c['profile_photo']), FILTER_VALIDATE_URL)
    ) {
        $missing[] = 'Ảnh đại diện';
    }

    $c['data_status']    = empty($missing) ? 'DU' : 'THIEU';
    $c['missing_fields'] = $missing;
    $c['missing_text']   = implode(', ', $missing);
    $c['missing_count']  = count($missing);
}

unset($c); // QUAN TRỌNG khi dùng &


// 2️⃣ Sắp xếp danh sách
usort($campers, function ($a, $b) {

    // Ưu tiên đủ dữ liệu trước
    if ($a['data_status'] !== $b['data_status']) {
        return $a['data_status'] === 'DU' ? -1 : 1;
    }

    // Hàm parse lớp học
    $parse = function ($class) {
        if (is_string($class) && preg_match('/^(\d+)([A-Z]+)(\d+)$/i', trim($class), $m)) {
            return [
                'grade'  => (int)$m[1],               // Khối
                'letter' => strtoupper($m[2]),        // A, B, C
                'num'    => (int)$m[3]                // 1, 10
            ];
        }
        // Dữ liệu lỗi đẩy xuống cuối
        return ['grade' => 0, 'letter' => 'Z', 'num' => 999];
    };

    $ca = $parse($a['class'] ?? '');
    $cb = $parse($b['class'] ?? '');

    // 1️⃣ Khối: 12 → 11 → 10
    if ($ca['grade'] !== $cb['grade']) {
        return $cb['grade'] <=> $ca['grade'];
    }

    // 2️⃣ Chữ lớp: A → B → C
    if ($ca['letter'] !== $cb['letter']) {
        return strcmp($ca['letter'], $cb['letter']);
    }

    // 3️⃣ Số lớp: 1 → 10
    return $ca['num'] <=> $cb['num'];
});

?>
<?php
$pageTitle = "Quản lý trại sinh";
$full_name = $_SESSION['full_name'] ?? '';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="ml-0 lg:ml-64 pt-4 min-h-screen bg-slate-50/50 transition-all duration-300 ease-in-out p-4 sm:p-6 lg:p-8" x-data="{ searchQuery: '' }">
    <div class="max-w-7xl mx-auto pb-12">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-primary-100 text-primary-600 flex items-center justify-center shadow-sm">
                    <i class="bi bi-people-fill text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">QUẢN LÝ TRẠI SINH</h2>
                    <p class="text-sm font-medium text-slate-500 mt-1">Quản lý danh sách, import, chỉnh sửa thông tin</p>
                </div>
            </div>
            
            <div class="flex items-center gap-3 w-full sm:w-auto">
                <div class="relative w-full sm:w-64">
                    <i class="bi bi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" x-model="searchQuery" placeholder="Tìm theo mã hoặc tên..." class="w-full pl-10 pr-4 py-2 bg-white border border-slate-200 rounded-lg text-sm outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 shadow-sm transition-all">
                </div>
                <a href="api/export_campers_excel.php" class="flex items-center gap-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 px-4 py-2 rounded-lg font-semibold transition-all shadow-sm whitespace-nowrap text-sm">
                    <i class="bi bi-file-earmark-excel"></i> Xuất Excel
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Thêm 1 trại sinh -->
            <div class="bg-white rounded-2xl p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 relative overflow-hidden">
                <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2 mb-4">
                    <i class="bi bi-person-plus-fill text-primary-500"></i> Thêm trại sinh
                </h3>
                <form method="post" class="space-y-4">
                    <input type="hidden" name="add_one">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <input class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none" name="student_code" placeholder="Mã trại sinh" required>
                        <input class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none" name="full_name" placeholder="Họ tên" required>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <input class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none" name="class" placeholder="Lớp">
                        <input class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none" name="phone" placeholder="Số điện thoại">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <input class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none" name="phone_parent" placeholder="SĐT phụ huynh">
                        <input class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none" name="email" placeholder="Email">
                    </div>
                    <div>
                        <input class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none" name="profile_photo" placeholder="Đường dẫn ảnh (tuỳ chọn)">
                    </div>
                    <button class="w-full bg-primary-600 hover:bg-primary-700 text-white font-bold py-2.5 rounded-lg transition-all shadow-sm flex items-center justify-center gap-2">
                        <i class="bi bi-plus-circle"></i> Thêm mới
                    </button>
                </form>
            </div>

            <!-- Import Excel -->
            <div class="bg-white rounded-2xl p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 flex flex-col justify-between">
                <div>
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                            <i class="bi bi-file-earmark-arrow-up-fill text-primary-500"></i> Import Excel
                        </h3>
                        <a href="uploads/DanhsachTraisinhmau.xlsx" class="text-xs text-primary-600 hover:text-primary-700 font-semibold flex items-center gap-1" download>
                            <i class="bi bi-download"></i> Tải file mẫu
                        </a>
                    </div>
                    <p class="text-sm text-slate-500 mb-4">Nhập danh sách trại sinh hàng loạt từ file Excel. File Excel phải có cấu trúc giống file mẫu.</p>
                </div>
                
                <form method="post" enctype="multipart/form-data" class="mt-auto">
                    <input type="hidden" name="import_excel">
                    <div class="flex flex-col sm:flex-row gap-3">
                        <input type="file" name="excel" accept=".xls,.xlsx" class="flex-1 px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-700 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100" required>
                        <button class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-2 rounded-lg font-bold transition-all shadow-sm whitespace-nowrap">
                            <i class="bi bi-cloud-upload"></i> Import
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Danh sách -->
        <div class="bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 overflow-hidden">
            <div class="p-5 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-800">
                    Danh sách trại sinh
                </h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase text-xs tracking-wider">
                        <tr>
                            <th class="px-5 py-4">Mã</th>
                            <th class="px-5 py-4">Họ tên / Lớp</th>
                            <th class="px-5 py-4">Liên hệ</th>
                            <th class="px-5 py-4">Trạng thái dữ liệu</th>
                            <th class="px-5 py-4">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($campers as $c): ?>
                        <tr class="hover:bg-slate-50/80 transition-colors" x-show="searchQuery === '' || '<?= strtolower($c['full_name']) ?>'.includes(searchQuery.toLowerCase()) || '<?= strtolower($c['student_code']) ?>'.includes(searchQuery.toLowerCase()) || '<?= strtolower($c['class']) ?>'.includes(searchQuery.toLowerCase())">
                            <td class="px-5 py-3.5"><span class="font-mono text-xs font-bold text-slate-500 bg-slate-100 px-2 py-1 rounded"><?= $c['student_code'] ?></span></td>
                            <td class="px-5 py-3.5">
                                <div class="font-bold text-slate-800"><?= $c['full_name'] ?></div>
                                <div class="text-xs text-slate-500 mt-0.5">Lớp: <?= $c['class'] ?></div>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="text-xs text-slate-600"><i class="bi bi-telephone text-slate-400"></i> <?= $c['phone'] ?></div>
                                <div class="text-xs text-slate-500 mt-0.5" title="SĐT Phụ huynh"><i class="bi bi-person-badge text-slate-400"></i> <?= $c['phone_parent'] ?></div>
                            </td>
                            <td class="px-5 py-3.5">
                                <?php if ($c['data_status'] === 'DU'): ?>
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <i class="bi bi-check-circle-fill mr-1 text-emerald-500"></i> Đủ dữ liệu
                                    </span>
                                <?php else: ?>
                                    <div class="group relative inline-block">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200 cursor-help">
                                            <i class="bi bi-exclamation-triangle-fill mr-1 text-amber-500"></i> Thiếu dữ liệu
                                        </span>
                                        <!-- Tooltip -->
                                        <div class="opacity-0 invisible group-hover:opacity-100 group-hover:visible absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-max bg-slate-800 text-white text-xs px-3 py-1.5 rounded-lg transition-all z-10">
                                            Thiếu: <?= implode(', ', $c['missing_fields']) ?>
                                            <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-800"></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3.5">
                                <?php if ($c['is_active']): ?>
                                    <div class="flex items-center gap-2">
                                        <button @click="$dispatch('open-edit-modal', <?= htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8') ?>)" class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 hover:bg-amber-500 hover:text-white flex items-center justify-center transition-colors shadow-sm border border-amber-100" title="Sửa">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <a href="?disable=<?= $c['student_code'] ?>" onclick="return confirm('Xoá trại sinh này?')" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-500 hover:text-white flex items-center justify-center transition-colors shadow-sm border border-red-100" title="Xóa tạm">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                        <?php if ($_SESSION['role'] === 'admin'): ?>
                                        <a href="?delete_forever=<?= $c['student_code'] ?>" onclick="return confirm('⚠️ XOÁ VĨNH VIỄN trại sinh này?\nDữ liệu điểm danh sẽ bị xoá hết!')" class="w-8 h-8 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-800 hover:text-white flex items-center justify-center transition-colors shadow-sm border border-slate-200" title="Xóa vĩnh viễn">
                                            <i class="bi bi-x-octagon-fill"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <a href="?restore=<?= $c['student_code'] ?>" class="text-xs bg-slate-100 hover:bg-slate-200 text-slate-600 px-3 py-1.5 rounded-lg font-semibold transition-colors">Khôi phục</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($_SESSION['role'] === 'admin'): ?>
            <div class="p-5 border-t border-slate-100 bg-slate-50 flex justify-end">
                <form method="post" onsubmit="return confirm('⚠️ XOÁ TOÀN BỘ TRẠI SINH?\n\n• Xoá tất cả trại sinh\n• Xoá toàn bộ lịch sử CHECK IN / OUT\n• KHÔNG THỂ KHÔI PHỤC\n\nBạn có chắc chắn không?');">
                    <button type="submit" name="delete_all" class="flex items-center gap-2 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 px-4 py-2 rounded-lg font-semibold transition-all shadow-sm text-sm">
                        <i class="bi bi-trash3"></i> Xoá tất cả trại sinh
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Alpine.js Edit Modal -->
<div x-data="{ open: false, data: {} }" 
     @open-edit-modal.window="open = true; data = $event.detail"
     x-show="open" 
     class="fixed inset-0 z-[2000] overflow-y-auto" 
     aria-labelledby="modal-title" 
     role="dialog" 
     aria-modal="true"
     x-cloak>
    
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div x-show="open" 
             x-transition:enter="ease-out duration-300" 
             x-transition:enter-start="opacity-0" 
             x-transition:enter-end="opacity-100" 
             x-transition:leave="ease-in duration-200" 
             x-transition:leave-start="opacity-100" 
             x-transition:leave-end="opacity-0" 
             class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" 
             @click="open = false"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div x-show="open" 
             x-transition:enter="ease-out duration-300" 
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" 
             x-transition:leave="ease-in duration-200" 
             x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" 
             x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" 
             class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full">
            
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 border-b border-slate-100 flex justify-between items-center">
                <h3 class="text-lg leading-6 font-bold text-slate-800" id="modal-title">
                    Sửa thông tin trại sinh
                </h3>
                <button @click="open = false" class="text-slate-400 hover:text-slate-500 focus:outline-none">
                    <i class="bi bi-x-lg text-lg"></i>
                </button>
            </div>
            
            <form id="editForm" @submit.prevent="
                const formData = new FormData($event.target);
                fetch('api/update_camper.php', { method: 'POST', body: formData })
                .then(r=>r.json())
                .then(res=>{
                    if(res.success){
                        location.reload();
                    } else {
                        alert(res.message || 'Lỗi');
                    }
                })">
                <div class="px-4 py-5 sm:p-6 space-y-4">
                    <input type="hidden" name="student_code" x-bind:value="data.student_code">
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Họ tên</label>
                        <input class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none" name="full_name" x-bind:value="data.full_name" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Lớp</label>
                        <input class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none" name="class" x-bind:value="data.class">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Số điện thoại</label>
                        <input class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none" name="phone" x-bind:value="data.phone">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">SĐT Phụ huynh</label>
                        <input class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none" name="phone_parent" x-bind:value="data.phone_parent">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                        <input class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none" name="email" x-bind:value="data.email">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Ảnh đại diện (Link/Cloudinary)</label>
                        <input class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none" name="profile_photo" x-bind:value="data.profile_photo">
                    </div>
                </div>
                
                <div class="bg-slate-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-slate-100">
                    <button type="submit" class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                        Lưu thay đổi
                    </button>
                    <button @click="open = false" type="button" class="mt-3 w-full inline-flex justify-center rounded-lg border border-slate-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-slate-700 hover:bg-slate-50 focus:outline-none sm:mt-0 sm:w-auto sm:text-sm transition-colors">
                        Huỷ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>