<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
$event_id = $_GET['event_id'] ?? 0;
if (!$event_id) {
    header("Location: attendance.php");
    exit();
}

// Lấy thông tin sự kiện
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();
if (!$event) {
    echo "Không tìm thấy sự kiện!";
    exit();
}

$checkMsg = '';
$checkType = '';

// Thêm học sinh mới
/*if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $add_full_name = trim($_POST['add_full_name'] ?? '');
    $add_class = trim($_POST['add_class'] ?? '');
    if ($add_full_name && $add_class) {
        // Tách họ tên
        $parts = explode(' ', trim(preg_replace('/\s+/', ' ', $add_full_name)));
        $ten = array_pop($parts);
        $ho = implode(' ', $parts);
        $stmt = $pdo->prepare("INSERT INTO students (full_name, ho, ten, class) VALUES (?, ?, ?, ?)");
        $stmt->execute([$add_full_name, $ho, $ten, $add_class]);
        $sid = $pdo->lastInsertId();
        $student_code = "HS" . date('YmdHis') . $sid;
        $stmt = $pdo->prepare("UPDATE students SET student_code=? WHERE id=?");
        $stmt->execute([$student_code, $sid]);
        $checkMsg = "Đã thêm học sinh mới thành công!";
        $checkType = "success";
        // Reload danh sách
        header("Location: attendance_admin.php?event_id=" . $event_id); exit();
    } else {
        $checkMsg = "Vui lòng nhập đủ họ tên và lớp!";
        $checkType = "danger";
    }
}
*/
// Lấy lại danh sách học sinh và tình trạng điểm danh
$stmt = $pdo->query("SELECT * FROM students ORDER BY class, full_name");
$students = $stmt->fetchAll();
$stmt = $pdo->prepare("SELECT student_id FROM attendance WHERE event_id = ?");
$stmt->execute([$event_id]);
$checked_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Xử lý lưu điểm danh đồng loạt (checkbox)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_checkin'])) {
    $checked_students = $_POST['checked_students'] ?? [];
    // Lấy danh sách đã điểm danh rồi
    $stmt = $pdo->prepare("SELECT student_id FROM attendance WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $checked_before = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Thêm mới những học sinh được tick mà chưa có
    $count_add = 0;
    foreach ($checked_students as $student_id) {
        if (!in_array($student_id, $checked_before)) {
            // Kiểm tra student_id có tồn tại trong bảng students không (chống tick nhầm id rỗng)
            $stmtTest = $pdo->prepare("SELECT id, student_code, full_name, class FROM students WHERE id=?");
            $stmtTest->execute([$student_id]);
            $student = $stmtTest->fetch();
            if ($student) {
                // Nếu thiếu mã học sinh thì sinh mã mới, tách họ tên nếu cần
                if (empty($student['student_code'])) {
                    $parts = explode(' ', trim(preg_replace('/\s+/', ' ', $student['full_name'])));
                    $ten = array_pop($parts);
                    $ho = implode(' ', $parts);
                    $student_code = "HS" . date('YmdHis') . $student['id'];
                    $stmtUpdate = $pdo->prepare("UPDATE students SET student_code=?, ho=?, ten=? WHERE id=?");
                    $stmtUpdate->execute([$student_code, $ho, $ten, $student['id']]);
                }
                $stmtIns = $pdo->prepare("INSERT INTO attendance (event_id, student_id, checkin_method) VALUES (?, ?, 'manual')");
                $stmtIns->execute([$event_id, $student_id]);
                $count_add++;
            }
        }
    }
    // Xóa những học sinh đã điểm danh nhưng đã bị bỏ tick
    $count_del = 0;
    foreach ($checked_before as $student_id) {
        if (!in_array($student_id, $checked_students)) {
            $stmtDel = $pdo->prepare("DELETE FROM attendance WHERE event_id = ? AND student_id = ?");
            $stmtDel->execute([$event_id, $student_id]);
            $count_del++;
        }
    }
    // Xóa lock của user này trong bảng tạm khi lưu xong
    $stmt = $pdo->prepare("DELETE FROM attendance_checking_tmp WHERE event_id=? AND user_id=?");
    $stmt->execute([$event_id, $_SESSION['user_id']]);

    $checkMsg = "Đã lưu điểm danh! Thêm mới: $count_add, Gỡ: $count_del";
    $checkType = "success";

    // Lấy lại danh sách checked_ids sau khi điểm danh
    $stmt = $pdo->prepare("SELECT student_id FROM attendance WHERE event_id = ?");
    $stmt->execute([$event_id]);
    $checked_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Kiểm tra trạng thái: có đang ở chế độ chỉnh sửa điểm danh không?
$editCheckin = isset($_POST['start_checkin']) || (isset($_POST['save_checkin']) && !$checkMsg);

$pageTitle = "Điểm danh - " . htmlspecialchars($event['title']);
$full_name = $_SESSION['full_name'] ?? '';
include '../includes/header.php';
?>
<main class="ml-0 lg:ml-64 pt-4 min-h-screen bg-slate-50/50 transition-all duration-300 ease-in-out p-4 sm:p-6 lg:p-8">
    <div class="max-w-7xl mx-auto pb-12">
        <!-- Back Link & Actions -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <a href="attendance.php" class="text-slate-500 hover:text-primary-600 transition-colors flex items-center gap-1.5 text-sm font-medium bg-white px-3 py-1.5 rounded-full border border-slate-200 shadow-sm hover:shadow">
                <i class="bi bi-arrow-left"></i> Sự kiện khác
            </a>
            
            <div class="flex items-center gap-3">
                <a href="attendance_qr.php?event_id=<?= $event_id ?>" class="flex items-center gap-2 bg-white hover:bg-slate-50 text-slate-700 px-4 py-2 rounded-lg font-semibold transition-all shadow-sm border border-slate-200 text-sm">
                    <i class="bi bi-qr-code-scan text-primary-600"></i> Mã QR
                </a>
                <a href="export_attendance.php?event_id=<?= $event_id ?>" class="flex items-center gap-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 px-4 py-2 rounded-lg font-semibold transition-all shadow-sm border border-emerald-200 text-sm">
                    <i class="bi bi-file-earmark-spreadsheet text-emerald-600"></i> Xuất CSV
                </a>
            </div>
        </div>
        
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-primary-100 text-primary-600 flex items-center justify-center shadow-sm">
                    <i class="bi bi-clipboard-check text-2xl"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-extrabold text-slate-800 tracking-tight">ĐIỂM DANH</h2>
                    <p class="text-sm font-medium text-slate-500 mt-1"><?= htmlspecialchars($event['title']) ?></p>
                </div>
            </div>
            
            <?php if (!$editCheckin): ?>
            <form method="post">
                <button type="submit" name="start_checkin" class="flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-5 py-2.5 rounded-lg font-semibold transition-all shadow-sm text-sm">
                    <i class="bi bi-pencil-square"></i> Sửa / Bắt đầu điểm danh
                </button>
            </form>
            <?php endif; ?>
        </div>

        <!-- Alerts -->
        <?php if ($checkMsg): ?>
            <div class="mb-6 flex items-center justify-between p-4 bg-<?= $checkType == 'success' ? 'emerald' : 'red' ?>-50 border-l-4 border-<?= $checkType == 'success' ? 'emerald' : 'red' ?>-500 text-<?= $checkType == 'success' ? 'emerald' : 'red' ?>-700 rounded-r-lg shadow-sm">
                <div class="flex items-center gap-2">
                    <i class="bi bi-<?= $checkType == 'success' ? 'check-circle-fill' : 'exclamation-circle-fill' ?> text-lg"></i>
                    <span class="font-medium"><?= htmlspecialchars($checkMsg) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$editCheckin): // View Mode ?>
        <div class="bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-100 overflow-hidden mb-6">
            <div class="p-5 border-b border-slate-100 bg-slate-50/50">
                <h3 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                    <i class="bi bi-list-ul text-primary-500"></i> Trạng thái điểm danh
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase text-xs tracking-wider">
                        <tr>
                            <th class="px-5 py-4">Mã HS</th>
                            <th class="px-5 py-4">Họ và tên</th>
                            <th class="px-5 py-4">Lớp</th>
                            <th class="px-5 py-4">Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php foreach ($students as $st): ?>
                    <tr class="hover:bg-slate-50/80 transition-colors <?= in_array($st['id'], $checked_ids) ? 'bg-emerald-50/30' : '' ?>">
                        <td class="px-5 py-3.5">
                            <span class="font-mono text-xs font-bold text-slate-500 bg-slate-100 px-2 py-1 rounded"><?= htmlspecialchars($st['student_code']) ?></span>
                        </td>
                        <td class="px-5 py-3.5 font-bold text-slate-800">
                            <?= htmlspecialchars($st['full_name']) ?>
                        </td>
                        <td class="px-5 py-3.5 text-slate-600">
                            <?= htmlspecialchars($st['class']) ?>
                        </td>
                        <td class="px-5 py-3.5">
                            <?php if (in_array($st['id'], $checked_ids)): ?>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold border border-emerald-200">
                                    <i class="bi bi-check-circle-fill"></i> Đã điểm danh
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 text-slate-500 text-xs font-bold border border-slate-200">
                                    Chưa điểm danh
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php else: // Edit Mode ?>
        <form method="POST" id="bulkCheckinForm">
            <div class="bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-primary-200 overflow-hidden mb-6 ring-4 ring-primary-500/10">
                <div class="p-5 border-b border-slate-100 bg-primary-50/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-bold text-primary-800 flex items-center gap-2">
                            <i class="bi bi-pencil-square text-primary-600"></i> Đang chỉnh sửa điểm danh
                        </h3>
                        <p class="text-sm text-primary-600/80 mt-1">Tích chọn học sinh rồi nhấn "Lưu điểm danh".</p>
                    </div>
                    <button type="submit" name="save_checkin" class="flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-5 py-2.5 rounded-xl font-semibold transition-all shadow-sm text-sm">
                        <i class="bi bi-save"></i> Lưu điểm danh
                    </button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase text-xs tracking-wider">
                            <tr>
                                <th class="px-5 py-4 w-12 text-center">
                                    <input type="checkbox" id="selectAll" class="w-4 h-4 text-primary-600 border-slate-300 rounded focus:ring-primary-500 cursor-pointer" title="Chọn tất cả">
                                </th>
                                <th class="px-5 py-4">Mã HS</th>
                                <th class="px-5 py-4">Họ và tên</th>
                                <th class="px-5 py-4">Lớp</th>
                                <th class="px-5 py-4">Trạng thái hiện tại</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        <?php foreach ($students as $st): ?>
                        <tr class="hover:bg-slate-50/80 transition-colors <?= in_array($st['id'], $checked_ids) ? 'bg-emerald-50/30' : '' ?>" id="tr-<?= $st['id'] ?>">
                            <td class="px-5 py-3.5 text-center">
                                <input type="checkbox" class="student-checkbox w-4 h-4 text-primary-600 border-slate-300 rounded focus:ring-primary-500 cursor-pointer"
                                    name="checked_students[]"
                                    value="<?= $st['id'] ?>"
                                    data-student="<?= $st['id'] ?>"
                                    <?= in_array($st['id'], $checked_ids) ? 'checked' : '' ?>>
                            </td>
                            <td class="px-5 py-3.5">
                                <span class="font-mono text-xs font-bold text-slate-500 bg-slate-100 px-2 py-1 rounded"><?= htmlspecialchars($st['student_code']) ?></span>
                            </td>
                            <td class="px-5 py-3.5 font-bold text-slate-800">
                                <?= htmlspecialchars($st['full_name']) ?>
                            </td>
                            <td class="px-5 py-3.5 text-slate-600">
                                <?= htmlspecialchars($st['class']) ?>
                            </td>
                            <td class="px-5 py-3.5">
                                <?php if (in_array($st['id'], $checked_ids)): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded text-emerald-700 text-xs font-medium">
                                        <i class="bi bi-check2"></i> Đã điểm danh
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded text-slate-500 text-xs font-medium">
                                        Chưa điểm danh
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="p-5 border-t border-slate-200 bg-slate-50 flex justify-end">
                    <button type="submit" name="save_checkin" class="flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white px-6 py-3 rounded-xl font-bold transition-all shadow-sm text-sm">
                        <i class="bi bi-save text-lg"></i> Lưu điểm danh
                    </button>
                </div>
            </div>
        </form>
        <script>
        // Chọn tất cả / Bỏ chọn tất cả
        document.getElementById('selectAll').onclick = function() {
            let checkboxes = document.querySelectorAll('.student-checkbox');
            for(const cb of checkboxes) { if(!cb.disabled) cb.checked = this.checked; }
        };

        // ======= Đồng bộ chọn tạm thời giữa các admin ========
        const eventId = <?= (int)$event_id ?>;
        let lockedStudents = new Set();

        function syncLockStatus() {
            fetch('attendance_checking_api.php?event_id=' + eventId)
              .then(resp => resp.json())
              .then(data => {
                lockedStudents = new Set(data.map(item => item.student_id));
                document.querySelectorAll('.student-checkbox').forEach(cb => {
                    let sid = cb.getAttribute('data-student');
                    let tr = cb.closest('tr');
                    if (lockedStudents.has(Number(sid))) {
                        cb.disabled = true;
                        tr.classList.add('bg-amber-50');
                        tr.classList.add('opacity-75');
                    } else {
                        cb.disabled = false;
                        tr.classList.remove('bg-amber-50');
                        tr.classList.remove('opacity-75');
                    }
                });
              });
        }
        
        // Khi tick/untick thì cập nhật trạng thái cho mọi người
        document.querySelectorAll('.student-checkbox').forEach(cb => {
            cb.addEventListener('change', function() {
                let sid = this.getAttribute('data-student');
                let action = this.checked ? 'check' : 'uncheck';
                
                // Cập nhật background cho dòng hiện tại
                let tr = this.closest('tr');
                if(this.checked) tr.classList.add('bg-emerald-50/30');
                else tr.classList.remove('bg-emerald-50/30');

                fetch('attendance_checking_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `event_id=${eventId}&student_id=${sid}&action=${action}`
                });
            });
        });
        
        // Đồng bộ mỗi 2 giây
        setInterval(syncLockStatus, 2000);
        window.addEventListener('focus', syncLockStatus);
        syncLockStatus();
        </script>
        <?php endif; ?>
    </div>
</main>
<?php include '../includes/footer.php'; ?>