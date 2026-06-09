<?php
require_once __DIR__ . '/../config/session.php';
require __DIR__ . '/../includes/db.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
// Chỉ cho phép admin, giáo viên, ban chủ nhiệm truy cập
if (!in_array($_SESSION['role'], ['admin', 'teacher','club_leader'])) {
    header("Location: ../dashboard.php");
    exit("Bạn không có quyền truy cập chức năng này!");
}
$addMsg = '';
$addMsgType = '';
$editMsg = '';
$editMsgType = '';
$deleteMsg = '';
$deleteMsgType = '';

// Xử lý thêm học sinh (Mã học sinh tự động hoặc import)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_student'])) {
    // Tạo mã học sinh tự động: 2 số cuối năm + 4 số tăng dần
    $year = date('y');
    $stmt = $pdo->prepare("SELECT student_code FROM students WHERE LEFT(student_code,2)=? ORDER BY student_code DESC LIMIT 1");
    $stmt->execute([$year]);
    $last_code = $stmt->fetchColumn();
    if ($last_code) {
        $last_num = (int)substr($last_code, 2, 4);
    } else {
        $last_num = 0;
    }
    $next_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
    $student_code = $year . $next_num;

    $ho = trim($_POST['ho'] ?? '');
    $ten = trim($_POST['ten'] ?? '');
    $class = trim($_POST['class'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($ho && $ten) {
        $stmt = $pdo->prepare("SELECT id FROM students WHERE student_code = ?");
        $stmt->execute([$student_code]);
        if ($stmt->fetch()) {
            $addMsg = "Mã học sinh đã tồn tại!";
            $addMsgType = "danger";
        } else {
            $full_name = trim("$ho $ten");
            $stmt = $pdo->prepare("INSERT INTO students (student_code, ho, ten, full_name, class, phone, email, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$student_code, $ho, $ten, $full_name, $class, $phone, $email, $address]);
            $addMsg = "Thêm học sinh thành công! Mã: $student_code";
            $addMsgType = "success";
        }
    } else {
        $addMsg = "Vui lòng nhập đầy đủ thông tin!";
        $addMsgType = "danger";
    }
}

// Xử lý xóa học sinh
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM attendance WHERE student_id=?");
    $stmt->execute([$delete_id]);
    $stmt = $pdo->prepare("DELETE FROM students WHERE id=?");
    $stmt->execute([$delete_id]);
    $deleteMsg = "Đã xóa học sinh!"; $deleteMsgType = "success";
}

// Xử lý xóa tất cả học sinh
if (isset($_POST['delete_all'])) {
    $pdo->exec("DELETE FROM attendance");
    $pdo->exec("DELETE FROM students");
    $deleteMsg = "Đã xóa tất cả học sinh!";
    $deleteMsgType = "success";
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

        // 1️⃣ Xoá toàn bộ lịch sử điểm danh
        $pdo->exec("DELETE FROM attendance_logs");

        // 2️⃣ Xoá toàn bộ trại sinh
        $pdo->exec("DELETE FROM campers");

        // (tuỳ chọn) reset AUTO_INCREMENT
        $pdo->exec("ALTER TABLE campers AUTO_INCREMENT = 1");
        $pdo->exec("ALTER TABLE attendance_logs AUTO_INCREMENT = 1");

        $pdo->commit();

        $deleteMsg = "✅ Đã xoá TOÀN BỘ trại sinh và lịch sử điểm danh!";
        $deleteMsgType = "success";

    } catch (Exception $e) {
        $pdo->rollBack();
        $deleteMsg = "❌ Lỗi xoá dữ liệu: " . $e->getMessage();
        $deleteMsgType = "danger";
    }
}

// Xử lý sửa học sinh
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_student'])) {
    $edit_id = $_POST['edit_id'];
    $student_code = trim($_POST['student_code'] ?? '');
    $ho = trim($_POST['ho'] ?? '');
    $ten = trim($_POST['ten'] ?? '');
    $class = trim($_POST['class'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $full_name = trim("$ho $ten");
    $stmt = $pdo->prepare("UPDATE students SET student_code=?, ho=?, ten=?, full_name=?, class=?, phone=?, email=?, address=? WHERE id=?");
    $stmt->execute([$student_code, $ho, $ten, $full_name, $class, $phone, $email, $address, $edit_id]);
    $editMsg = "Sửa thông tin thành công!";
    $editMsgType = "success";
}

// Xử lý import CSV (tự sinh mã học sinh nếu thiếu)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['import_csv'])) {
    if (is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
        $handle = fopen($_FILES['csv_file']['tmp_name'], "r");
        $row = 0;
        $added = 0;
        $skipped = 0;
        $year = date('y');
        // Lấy mã lớn nhất hiện tại
        $stmtmax = $pdo->prepare("SELECT student_code FROM students WHERE LEFT(student_code,2)=? ORDER BY student_code DESC LIMIT 1");
        $stmtmax->execute([$year]);
        $max = 0;
        if ($rowmax = $stmtmax->fetch(PDO::FETCH_ASSOC)) {
            $code_num = (int)substr($rowmax['student_code'],2,4);
            if ($code_num > $max) $max = $code_num;
        }
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if ($row == 0) { $row++; continue; }
            $student_code = trim($data[0] ?? '');
            $ho = trim($data[1] ?? '');
            $ten = trim($data[2] ?? '');
            $class = trim($data[3] ?? '');
            $phone = trim($data[4] ?? '');
            $email = trim($data[5] ?? '');
            $address = trim($data[6] ?? '');
            $note = trim($data[7] ?? '');

            // Nếu mã học sinh trống, tự động sinh mã
            if (!$student_code || strlen($student_code) < 3) {
                $max++;
                $student_code = $year . str_pad($max, 4, '0', STR_PAD_LEFT);
            }

            if ($ten) {
                // Kiểm tra trùng mã
                $stmt = $pdo->prepare("SELECT id FROM students WHERE student_code = ?");
                $stmt->execute([$student_code]);
                if (!$stmt->fetch()) {
                    $full_name = trim("$ho $ten");
                    $stmt = $pdo->prepare("INSERT INTO students (student_code, ho, ten, class, phone, email, address, note, full_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$student_code, $ho, $ten, $class, $phone, $email, $address, $note, $full_name]);
                    $added++;
                } else {
                    $skipped++;
                }
            } else {
                $skipped++;
            }
            $row++;
        }
        fclose($handle);
        $addMsg = "Import thành công: $added dòng mới, $skipped dòng bị bỏ qua (trùng mã hoặc thiếu tên).";
        $addMsgType = "success";
    } else {
        $addMsg = "Lỗi upload file!";
        $addMsgType = "danger";
    }
}

// Xử lý export CSV
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=students.csv');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF"); // BOM UTF-8
    fputcsv($output, array('Mã học sinh', 'Họ', 'Tên', 'Lớp', 'Số điện thoại', 'Email', 'Địa chỉ', 'Ghi chú'));
    $stmt = $pdo->query("SELECT * FROM students ORDER BY class, ho, ten");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Thêm dấu nháy đơn để số điện thoại không bị mất số 0 khi mở bằng Excel
        $phone = $row['phone'];
        if ($phone !== "" && $phone[0] === '0') $phone = "'" . $phone;
        fputcsv($output, array(
            $row['student_code'],
            $row['ho'],
            $row['ten'],
            $row['class'],
            $phone,
            $row['email'],
            $row['address'],
            $row['note']
        ));
    }
    fclose($output);
    exit();
}

// Lấy danh sách học sinh
$where = [];
$params = [];

if (!empty($_GET['q'])) {
    $q = "%".trim($_GET['q'])."%";
    $where[] = "(student_code LIKE ? OR full_name LIKE ? OR ho LIKE ? OR ten LIKE ? OR class LIKE ? OR phone LIKE ? OR email LIKE ?)";
    $params = array_merge($params, array_fill(0,7,$q));
}
if (!empty($_GET['class'])) {
    $where[] = "class = ?";
    $params[] = $_GET['class'];
}
$where_sql = $where ? "WHERE ".implode(" AND ", $where) : "";

// Phân trang
$limit = 20; // Số học sinh trên mỗi trang
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// Lấy tổng số học sinh
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM students $where_sql");
$stmt_count->execute($params);
$total_records = $stmt_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

$offset = ($page - 1) * $limit;

// Truy vấn lấy dữ liệu theo trang
$stmt = $pdo->prepare("SELECT * FROM students $where_sql ORDER BY class, ho, ten LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$students = $stmt->fetchAll();

// Nếu sửa, lấy thông tin học sinh cần sửa
$editStudent = null;
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$edit_id]);
    $editStudent = $stmt->fetch();
}
?>
<?php
$pageTitle = "QUẢN LÝ HỌC SINH";
$full_name = $_SESSION['full_name'] ?? '';
include '../includes/header.php';
include '../includes/sidebar.php';
?>

<main class="ml-0 lg:ml-64 pt-4 min-h-screen bg-slate-50/50 transition-all duration-300 ease-in-out p-4 sm:p-6 lg:p-8">
    <div class="max-w-7xl mx-auto pb-12">
        <div class="flex items-center gap-3 mb-6">
            <a href="../dashboard.php" class="text-slate-500 hover:text-primary-600 transition-colors flex items-center gap-1.5 text-sm font-medium bg-white px-3 py-1.5 rounded-full border border-slate-200 shadow-sm hover:shadow">
                <i class="bi bi-arrow-left"></i> Về Trang chủ
            </a>
        </div>
        
        <div class="flex items-center gap-3 mb-8">
            <div class="w-12 h-12 rounded-xl bg-primary-100 text-primary-600 flex items-center justify-center shadow-sm">
                <i class="bi bi-people text-2xl"></i>
            </div>
            <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">QUẢN LÝ HỌC SINH</h2>
        </div>
        
        <!-- Alerts -->
        <?php if ($addMsg): ?>
            <div class="mb-4 flex items-center justify-between p-4 bg-<?= $addMsgType == 'success' ? 'emerald' : 'red' ?>-50 border-l-4 border-<?= $addMsgType == 'success' ? 'emerald' : 'red' ?>-500 text-<?= $addMsgType == 'success' ? 'emerald' : 'red' ?>-700 rounded-r-lg shadow-sm">
                <div class="flex items-center gap-2">
                    <i class="bi bi-<?= $addMsgType == 'success' ? 'check-circle-fill' : 'exclamation-circle-fill' ?> text-lg"></i>
                    <span class="font-medium"><?= htmlspecialchars($addMsg) ?></span>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($editMsg): ?>
            <div class="mb-4 flex items-center justify-between p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 rounded-r-lg shadow-sm">
                <div class="flex items-center gap-2">
                    <i class="bi bi-check-circle-fill text-lg"></i>
                    <span class="font-medium"><?= htmlspecialchars($editMsg) ?></span>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($deleteMsg): ?>
            <div class="mb-4 flex items-center justify-between p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 rounded-r-lg shadow-sm">
                <div class="flex items-center gap-2">
                    <i class="bi bi-check-circle-fill text-lg"></i>
                    <span class="font-medium"><?= htmlspecialchars($deleteMsg) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Alpine State for Forms -->
        <div x-data="{ showAddForm: false }">
            
            <!-- Add Student Button & Bulk Actions -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                <div>
                    <!-- Bulk Xoa tat ca form -->
                    <form method="post" onsubmit="return confirm('Bạn chắc chắn muốn xóa TẤT CẢ học sinh?');" class="inline-block">
                        <button type="submit" name="delete_all" class="flex items-center gap-2 bg-red-50 text-red-600 hover:bg-red-100 px-4 py-2 rounded-lg font-semibold transition-colors shadow-sm text-sm">
                            <i class="bi bi-trash3"></i> Xóa tất cả
                        </button>
                    </form>
                </div>
                
                <button x-show="!showAddForm" @click="showAddForm = true; $nextTick(() => $refs.inputHo.focus())" class="flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-5 py-2.5 rounded-lg font-semibold transition-all shadow-sm hover:shadow-md text-sm">
                    <i class="bi bi-plus-circle"></i> Thêm học sinh
                </button>
            </div>

            <!-- Add Student Form -->
            <div x-show="showAddForm" 
                 x-transition:enter="transition ease-out duration-300 origin-top"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-200 origin-top"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 x-cloak class="mb-8">
                <div class="bg-white rounded-2xl p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100">
                    <h3 class="text-lg font-bold text-slate-800 mb-5 flex items-center gap-2">
                        <i class="bi bi-person-plus text-primary-500"></i> Thêm học sinh mới
                    </h3>
                    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-5" x-data="{ ho: '', ten: '' }">
                        <!-- Code -->
                        <div class="lg:col-span-1">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Mã HS</label>
                            <?php
                            $year = date('y');
                            $stmt = $pdo->prepare("SELECT student_code FROM students WHERE LEFT(student_code,2)=? ORDER BY student_code DESC LIMIT 1");
                            $stmt->execute([$year]);
                            $max = 0;
                            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $code_num = (int)substr($row['student_code'],2,4);
                                if ($code_num > $max) $max = $code_num;
                            }
                            $next_num = str_pad($max+1, 4, '0', STR_PAD_LEFT);
                            $auto_code = $year . $next_num;
                            ?>
                            <input type="text" name="student_code" value="<?= $auto_code ?>" readonly class="w-full px-3 py-2 bg-slate-100 border border-slate-200 rounded-lg text-slate-500 text-sm font-medium outline-none cursor-not-allowed">
                        </div>
                        
                        <!-- Ho -->
                        <div class="lg:col-span-2">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Họ</label>
                            <input type="text" name="ho" x-model="ho" x-ref="inputHo" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all">
                        </div>

                        <!-- Ten -->
                        <div class="lg:col-span-1">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Tên</label>
                            <input type="text" name="ten" x-model="ten" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all">
                        </div>

                        <!-- Class -->
                        <div class="lg:col-span-1">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Lớp</label>
                            <input type="text" name="class" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all">
                        </div>

                        <!-- Phone -->
                        <div class="lg:col-span-1">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Số ĐT</label>
                            <input type="text" name="phone" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all">
                        </div>

                        <!-- Email -->
                        <div class="lg:col-span-2">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Email</label>
                            <input type="email" name="email" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all">
                        </div>

                        <!-- Address -->
                        <div class="lg:col-span-4">
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Địa chỉ</label>
                            <input type="text" name="address" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 outline-none transition-all">
                        </div>
                        
                        <!-- Fullname preview -->
                        <div class="lg:col-span-6 mt-2 pt-4 border-t border-slate-100 flex items-center justify-between flex-wrap gap-4">
                            <div class="text-sm font-medium text-slate-500">
                                Họ và tên: <span class="font-bold text-primary-600" x-text="(ho + ' ' + ten).trim() || '...'"></span>
                            </div>
                            
                            <div class="flex items-center gap-3">
                                <button type="button" @click="showAddForm = false" class="px-5 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-colors">Hủy</button>
                                <button type="submit" name="add_student" class="px-5 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-sm font-semibold shadow-sm transition-all flex items-center gap-2">
                                    <i class="bi bi-plus-circle"></i> Thêm học sinh
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Form -->
        <?php if ($editStudent): ?>
        <div class="mb-8 bg-white rounded-2xl p-6 shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-primary-100 ring-2 ring-primary-500/20">
            <h3 class="text-lg font-bold text-slate-800 mb-5 flex items-center gap-2">
                <i class="bi bi-pencil-square text-primary-500"></i> Sửa thông tin học sinh
            </h3>
            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-5">
                <input type="hidden" name="edit_id" value="<?= $editStudent['id'] ?>">
                
                <div class="lg:col-span-1">
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Mã HS</label>
                    <input type="text" name="student_code" value="<?= htmlspecialchars($editStudent['student_code']) ?>" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 outline-none">
                </div>
                
                <div class="lg:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Họ</label>
                    <input type="text" name="ho" value="<?= htmlspecialchars($editStudent['ho']) ?>" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 outline-none">
                </div>

                <div class="lg:col-span-1">
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Tên</label>
                    <input type="text" name="ten" value="<?= htmlspecialchars($editStudent['ten']) ?>" required class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 outline-none">
                </div>

                <div class="lg:col-span-1">
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Lớp</label>
                    <input type="text" name="class" value="<?= htmlspecialchars($editStudent['class']) ?>" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 outline-none">
                </div>

                <div class="lg:col-span-1">
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Số ĐT</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($editStudent['phone']) ?>" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 outline-none">
                </div>

                <div class="lg:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($editStudent['email']) ?>" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 outline-none">
                </div>

                <div class="lg:col-span-4">
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-2">Địa chỉ</label>
                    <input type="text" name="address" value="<?= htmlspecialchars($editStudent['address']) ?>" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg text-slate-800 text-sm focus:border-primary-500 focus:ring-2 outline-none">
                </div>
                
                <div class="lg:col-span-6 mt-2 pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                    <a href="students.php" class="px-5 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-colors">Hủy</a>
                    <button type="submit" name="edit_student" class="px-5 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-sm font-semibold shadow-sm transition-all flex items-center gap-2">
                        <i class="bi bi-save"></i> Lưu thay đổi
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Import / Export Controls -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4 bg-white rounded-2xl p-5 shadow-[0_2px_10px_rgb(0,0,0,0.02)] border border-slate-100 mb-6">
            <div class="md:col-span-6 lg:col-span-5">
                <form method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row items-end sm:items-center gap-3">
                    <div class="w-full flex-1">
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase">Import từ CSV</label>
                        <input type="file" name="csv_file" accept=".csv" required class="block w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 transition-all border border-slate-200 rounded-lg cursor-pointer bg-slate-50">
                    </div>
                    <button type="submit" name="import_csv" class="w-full sm:w-auto mt-2 sm:mt-0 whitespace-nowrap bg-indigo-50 text-indigo-700 hover:bg-indigo-100 px-5 py-2.5 rounded-lg font-semibold transition-colors flex items-center justify-center gap-2 text-sm border border-indigo-200">
                        <i class="bi bi-upload"></i> Import
                    </button>
                </form>
            </div>
            
            <div class="md:col-span-6 lg:col-span-7 flex flex-col sm:flex-row items-end sm:items-center justify-end gap-3 pt-2 sm:pt-6">
                <a href="../assets/download.php?file=student.csv" class="w-full sm:w-auto bg-slate-50 text-slate-700 hover:bg-slate-100 px-5 py-2.5 rounded-lg font-semibold transition-colors flex items-center justify-center gap-2 text-sm border border-slate-200">
                    <i class="bi bi-download"></i> Tải file mẫu
                </a>
                
                <form method="GET" class="w-full sm:w-auto">
                    <button type="submit" name="export_csv" value="1" class="w-full sm:w-auto bg-emerald-50 text-emerald-700 hover:bg-emerald-100 px-5 py-2.5 rounded-lg font-semibold transition-colors flex items-center justify-center gap-2 text-sm border border-emerald-200">
                        <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
                    </button>
                </form>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="bg-white rounded-t-2xl p-5 border-b border-slate-100 shadow-[0_-2px_10px_rgb(0,0,0,0.02)]">
            <h3 class="text-base font-bold text-slate-800 mb-4">Danh sách học sinh</h3>
            <form method="get" class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-6 lg:col-span-5 relative">
                    <i class="bi bi-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($_GET['q']??'') ?>" placeholder="Tìm mã, tên, lớp, SĐT..." class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:bg-white focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all">
                </div>
                <div class="md:col-span-4 lg:col-span-4">
                    <select name="class" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm outline-none focus:bg-white focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-all text-slate-600 appearance-none bg-[url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2394a3b8%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[position:right_1rem_center] bg-[length:10px_10px] pr-10">
                        <option value="">Tất cả các lớp</option>
                        <?php
                        $classList = $pdo->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL AND class!='' ORDER BY class")->fetchAll();
                        foreach($classList as $c) {
                            $sel = (($_GET['class']??'')==$c['class']) ? 'selected' : '';
                            echo "<option value='".htmlspecialchars($c['class'])."' $sel>".htmlspecialchars($c['class'])."</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="md:col-span-2 lg:col-span-3">
                    <button type="submit" class="w-full bg-slate-800 hover:bg-slate-900 text-white px-5 py-2.5 rounded-xl font-semibold transition-all shadow-sm flex items-center justify-center gap-2 text-sm">
                        Lọc kết quả
                    </button>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-b-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase text-xs tracking-wider">
                        <tr>
                            <th class="px-5 py-4 text-center">Mã HS</th>
                            <th class="px-5 py-4">Họ và tên</th>
                            <th class="px-5 py-4 text-center">Lớp</th>
                            <th class="px-5 py-4 text-center">Số ĐT</th>
                            <th class="px-5 py-4">Email</th>
                            <th class="px-5 py-4">Địa chỉ</th>
                            <th class="px-5 py-4 text-center w-28">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php if (count($students) > 0): ?>
                        <?php foreach ($students as $student): ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-5 py-3.5 text-center">
                                    <span class="inline-flex items-center px-2 py-1 rounded bg-slate-100 text-slate-600 text-xs font-bold font-mono tracking-wider">
                                        <?= htmlspecialchars($student['student_code']) ?>
                                    </span>
                                </td>
                                <td class="px-5 py-3.5">
                                    <span class="font-bold text-slate-800"><?= htmlspecialchars(trim($student['ho'] . ' ' . $student['ten'])) ?></span>
                                </td>
                                <td class="px-5 py-3.5 text-center text-slate-600 font-medium"><?= htmlspecialchars($student['class']) ?></td>
                                <td class="px-5 py-3.5 text-center text-slate-600"><?= htmlspecialchars($student['phone']) ?></td>
                                <td class="px-5 py-3.5 text-slate-600 truncate max-w-[150px]"><?= htmlspecialchars($student['email']) ?></td>
                                <td class="px-5 py-3.5 text-slate-500 text-xs truncate max-w-[200px]"><?= htmlspecialchars($student['address']) ?></td>
                                <td class="px-5 py-3.5 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <a href="students.php?edit_id=<?= $student['id'] ?>" class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-600 hover:text-white flex items-center justify-center transition-colors" title="Sửa">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <a href="students.php?delete_id=<?= $student['id'] ?>" onclick="return confirm('Bạn chắc chắn muốn xóa?')" class="w-8 h-8 rounded-lg bg-red-50 text-red-600 hover:bg-red-600 hover:text-white flex items-center justify-center transition-colors" title="Xóa">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-slate-500 bg-slate-50/50">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="bi bi-inbox text-4xl mb-3 text-slate-300"></i>
                                    <p>Không tìm thấy học sinh nào!</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="flex justify-center mt-6">
            <nav class="inline-flex rounded-xl shadow-sm bg-white border border-slate-200 p-1">
                <?php
                $qs = $_GET; unset($qs['page']);
                $query_string = http_build_query($qs);
                $query_prefix = $query_string ? "&" . $query_string : "";
                
                // Prev
                if ($page <= 1) {
                    echo '<span class="px-3 py-1.5 text-slate-300 cursor-not-allowed"><i class="bi bi-chevron-left"></i></span>';
                } else {
                    $prev = $page - 1;
                    echo "<a href='?page=$prev$query_prefix' class='px-3 py-1.5 text-slate-500 hover:text-primary-600 hover:bg-slate-50 rounded-lg transition-colors'><i class='bi bi-chevron-left'></i></a>";
                }

                // Numbers
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1) {
                    echo "<a href='?page=1$query_prefix' class='px-3.5 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50 rounded-lg transition-colors'>1</a>";
                    if ($start_page > 2) echo '<span class="px-2 py-1.5 text-slate-400">...</span>';
                }

                for ($i = $start_page; $i <= $end_page; $i++) {
                    if ($i == $page) {
                        echo "<span class='px-3.5 py-1.5 text-sm font-bold bg-primary-50 text-primary-600 rounded-lg'>$i</span>";
                    } else {
                        echo "<a href='?page=$i$query_prefix' class='px-3.5 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50 rounded-lg transition-colors'>$i</a>";
                    }
                }

                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span class="px-2 py-1.5 text-slate-400">...</span>';
                    echo "<a href='?page=$total_pages$query_prefix' class='px-3.5 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-50 rounded-lg transition-colors'>$total_pages</a>";
                }

                // Next
                if ($page >= $total_pages) {
                    echo '<span class="px-3 py-1.5 text-slate-300 cursor-not-allowed"><i class="bi bi-chevron-right"></i></span>';
                } else {
                    $next = $page + 1;
                    echo "<a href='?page=$next$query_prefix' class='px-3 py-1.5 text-slate-500 hover:text-primary-600 hover:bg-slate-50 rounded-lg transition-colors'><i class='bi bi-chevron-right'></i></a>";
                }
                ?>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</main>
<?php include '../includes/footer.php'; ?>